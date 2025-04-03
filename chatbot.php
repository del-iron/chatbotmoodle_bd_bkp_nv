<?php
header("Content-Type: text/html; charset=UTF-8");
session_start();

include __DIR__ . '/paramentros.php';

// Inicializa erro_count se ainda não estiver definido
if (!isset($_SESSION['erro_count'])) {
    $_SESSION['erro_count'] = 0;
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

// Define o nome do usuário
$user_name = paramentros::DEFAULT_USER_NAME;

// Obtém a conexão com o banco de dados
$pdo = paramentros::getPDO();

if (!$pdo) {
    paramentros::send_response("Erro ao conectar ao banco de dados. Tente novamente mais tarde.");
    exit;
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
            // Adiciona cada resposta com numeração e quebra de linha
            $respostas[] = ($index + 1) . ". " . $result['resposta'];
        }
        // Junta as respostas com uma linha em branco entre elas
        return "Ah, meu bem... achei várias respostas que podem te ajudar! Você quis dizer algo assim?\n\n" . implode("\n\n", $respostas);
    }

    // Retorna a única resposta encontrada ou null
    return $results[0]['resposta'] ?? null;
}

// Busca a resposta no banco de dados
$resposta = buscar_resposta($pdo, $message);

// Se nenhuma resposta foi encontrada, usar resposta padrão
if ($resposta === null) {
    $_SESSION['erro_count']++;
    switch ($_SESSION['erro_count']) {
        case 1:
            $resposta = "$user_name, desculpe, não encontrei uma resposta para isso. Reformule sua pergunta, por favor!";
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

// Removida a lógica de inserção na tabela mensagens

// Envia a resposta para o usuário
paramentros::send_response($resposta);
?>