<?php
header("Content-Type: text/html; charset=UTF-8");
session_start();

include __DIR__ . '/paramentros.php';

// Inicializa erro_count se ainda não estiver definido
if (!isset($_SESSION['erro_count'])) {
    $_SESSION['erro_count'] = 0;
}

// Obtém a conexão com o banco de dados (mova para o início)
$pdo = paramentros::getPDO();

if (!$pdo) {
    paramentros::send_response("Erro ao conectar ao banco de dados. Tente novamente mais tarde.");
    exit;
}

// Verifica se o chat acabou de ser aberto
if (!isset($_SESSION["chat_started"])) {
    $_SESSION["chat_started"] = true;
    usleep(1000000); // 1 segundo
    paramentros::send_response(paramentros::WELCOME_MESSAGE);
    exit;
}

// Obtém a mensagem do usuário
$message = isset($_POST["message"]) ? strtolower(trim($_POST["message"])) : "";

// Verifica se o usuário está respondendo a uma lista de contextos
if (isset($_SESSION['opcoes_contextos']) && is_numeric($message)) {
    $opcoes = $_SESSION['opcoes_contextos'];
    $indice = (int)$message - 1; // Converte a escolha do usuário para índice do array

    if (isset($opcoes[$indice])) {
        // Busca a resposta correspondente ao contexto escolhido
        $contexto = $opcoes[$indice]['contexto'];
        $resposta = buscar_resposta_por_contexto($pdo, $contexto);

        // Limpa os contextos da sessão
        unset($_SESSION['opcoes_contextos']);

        // Envia a resposta ao usuário
        paramentros::send_response($resposta);
        exit;
    } else {
        // Caso o índice seja inválido
        paramentros::send_response("Ops! Parece que essa opção não é válida. Por favor, escolha uma das opções listadas acima");
        exit;
    }
}

// Verifica se o usuário está respondendo a uma lista de opções
if (isset($_SESSION['opcoes_respostas']) && is_numeric($message)) {
    $opcoes = $_SESSION['opcoes_respostas'];
    $indice = (int)$message - 1; // Converte a escolha do usuário para índice do array

    if (isset($opcoes[$indice])) {
        // Retorna a resposta correspondente à escolha do usuário
        $resposta = $opcoes[$indice]['resposta'];

        // Limpa as opções da sessão
        unset($_SESSION['opcoes_respostas']);

        // Envia a resposta ao usuário
        paramentros::send_response($resposta);
        exit;
    } else {
        // Caso o índice seja inválido
        paramentros::send_response("Errou! Essa opção não existe... vê direitinho e tenta de novo, tá bom?");
        exit;
    }
}

// Define o nome do usuário
$user_name = paramentros::DEFAULT_USER_NAME;

function buscar_contextos($pdo, $message) {
    $sql = "
        SELECT DISTINCT pr.contexto, 
               MAX(MATCH(pk.palavra) AGAINST(? IN NATURAL LANGUAGE MODE)) AS relevancia
        FROM perguntas_respostas pr
        INNER JOIN palavras_chave pk ON pr.id = pk.pergunta_id
        WHERE MATCH(pk.palavra) AGAINST(? IN NATURAL LANGUAGE MODE)
        GROUP BY pr.contexto
        ORDER BY relevancia DESC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$message, $message]);
    $results = $stmt->fetchAll();

    if (count($results) > 1) {
        $contextos = [];
        foreach ($results as $index => $result) {
            // Adiciona cada contexto com numeração
            $contextos[] = ($index + 1) . ". " . $result['contexto'];
        }

        // Armazena os contextos na sessão para a próxima interação
        $_SESSION['opcoes_contextos'] = $results;

        // Concatena a introdução e as opções em uma única mensagem
        $response = "Olha só, revisei sua dúvida e tenho sugestões que podem ser úteis! \n\n "
            . implode("\n ", $contextos) 
            . "\n\nPor favor, escolha uma das " . count($contextos) . " opções listadas acima!";

        // Retorna a mensagem completa
        return $response;
    }

    // Retorna o único contexto encontrado ou null
    return $results[0]['contexto'] ?? null;
}

function buscar_resposta_por_contexto($pdo, $contexto) {
    $sql = "SELECT resposta FROM perguntas_respostas WHERE contexto = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$contexto]);
    $result = $stmt->fetch();

    if ($result) {
        // Envia a resposta como uma fala separada
        paramentros::send_response($result['resposta']);
        exit;
    }

    return null;
}

function buscar_resposta($pdo, $message) {
    $sql = "
        SELECT DISTINCT pr.resposta, 
               MATCH(pk.palavra) AGAINST(? IN NATURAL LANGUAGE MODE) AS relevancia
        FROM perguntas_respostas pr
        INNER JOIN palavras_chave pk ON pr.id = pk.pergunta_id
        WHERE MATCH(pk.palavra) AGAINST(? IN NATURAL LANGUAGE MODE)
        ORDER BY relevancia DESC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$message, $message]);
    $results = $stmt->fetchAll();

    if (count($results) > 1) {
        $respostas = [];
        foreach ($results as $index => $result) {
            // Adiciona cada resposta com numeração
            $respostas[] = ($index + 1) . ". " . $result['resposta'];
        }

        // Armazena as opções na sessão para a próxima interação
        $_SESSION['opcoes_respostas'] = $results;

        // Armazena a primeira resposta na sessão para futuras referências
        $_SESSION['ultima_resposta'] = $results[0]['resposta'];

        // Retorna as opções para o usuário - 3
        return "Me parece que o que você perguntou tem a ver com uma dessas possibilidades:" 
        . implode("\n", $respostas) . "\n"
        . "Diz aí o número que a gente segue em frente!";
    }

    // Retorna a única resposta encontrada ou null
    return $results[0]['resposta'] ?? null;
}

// Busca os contextos no banco de dados
$resposta = buscar_contextos($pdo, $message);

// Se nenhum contexto foi encontrado, usar resposta padrão
if ($resposta === null) {
    $_SESSION['erro_count']++;
    switch ($_SESSION['erro_count']) {
        case 1:
            $resposta = "$user_name, desculpe, não encontrei um contexto para isso. Reformule sua pergunta, por favor!";
            break;
        case 2:
            $resposta = "$user_name, não consegui entender sua solicitação. Poderia reformular de outra maneira?";
            break;
        default:
            $resposta = "$user_name, sinto muito, não consegui te entender. Encerrando o chat... Tchauuu!";
            session_unset();
            session_destroy();
            // Simula resposta humana antes de encerrar
            usleep(rand(2000000, 4000000)); // Entre 2 e 4 segundos
            paramentros::send_response($resposta);
            exit;
    }
}

// Simula resposta humana antes de enviar a resposta
usleep(rand(2000000, 4000000)); // Entre 2 e 4 segundos

// Substitui \n por \n para garantir que o frontend interprete corretamente
$resposta = str_replace("\n", "\n", $resposta);

paramentros::send_response($resposta);
?>