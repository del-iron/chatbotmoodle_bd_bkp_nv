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
        paramentros::send_response("Opção inválida. Por favor, escolha uma opção válida.");
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
        paramentros::send_response("Opção inválida. Por favor, escolha uma opção válida.");
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
        LIMIT 5
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

        // Retorna os contextos para o usuário
        return "Vamos analisar... achei alguns contextos que podem te ajudar! Escolha um deles:\n\n" . implode("\n\n", $contextos) . "\n\nPor favor, escolha uma opção (1, 2, etc.).";
    }

    // Retorna o único contexto encontrado ou null
    return $results[0]['contexto'] ?? null;
}

function buscar_resposta_por_contexto($pdo, $contexto) {
    $sql = "SELECT resposta FROM perguntas_respostas WHERE contexto = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$contexto]);
    $result = $stmt->fetch();
    return $result['resposta'] ?? null;
}

function buscar_resposta($pdo, $message) {
    $sql = "
        SELECT DISTINCT pr.resposta, 
               MATCH(pk.palavra) AGAINST(? IN NATURAL LANGUAGE MODE) AS relevancia
        FROM perguntas_respostas pr
        INNER JOIN palavras_chave pk ON pr.id = pk.pergunta_id
        WHERE MATCH(pk.palavra) AGAINST(? IN NATURAL LANGUAGE MODE)
        ORDER BY relevancia DESC
        LIMIT 5
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

        // Retorna as opções para o usuário
        return "Ah, meu bem... achei várias respostas que podem te ajudar! Você quis dizer algo assim?\n\n" . implode("\n\n", $respostas) . "\n\nPor favor, escolha uma opção (1, 2, etc.).";
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
            paramentros::send_response($resposta);
            exit;
    }
}

// Simula resposta humana
usleep(rand(2000000, 4000000)); // Entre 2 e 4 segundos

// Envia os contextos ou a resposta ao usuário
paramentros::send_response($resposta);
?>