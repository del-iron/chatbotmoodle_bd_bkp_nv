<?php
session_start();

// Remover todas as variáveis da sessão
$_SESSION = [];

// Invalidar o cookie da sessão (se estiver sendo usado)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, 
        $params["path"], $params["domain"], 
        $params["secure"], $params["httponly"]
    );
}

// Destruir a sessão
session_unset();
session_destroy();

echo "Sessão encerrada!";
exit;
?>