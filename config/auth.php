<?php

require_once __DIR__ . "/session.php";
require_once __DIR__ . "/database.php";

function requireRole(string $requiredRole): array
{
    global $pdo;

    if (!isset($_SESSION["user_id"])) {
        http_response_code(401);
        exit("Unauthorized. Please log in.");
    }

/* AUTHENTICATED SESSION INACTIVITY TIMEOUT */

$inactivityLimit = 30 * 60; // 30 minutes
$currentTime = time();

if (isset($_SESSION["last_activity"])) {
    $inactiveSeconds = $currentTime - $_SESSION["last_activity"];

    if ($inactiveSeconds >= $inactivityLimit) {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();

            setcookie(session_name(), "", [
                "expires" => time() - 3600,
                "path" => $params["path"],
                "domain" => $params["domain"],
                "secure" => $params["secure"],
                "httponly" => $params["httponly"],
                "samesite" => $params["samesite"]
            ]);
        }

        session_destroy();

        http_response_code(401);
        exit("Session expired due to inactivity. Please log in again.");
    }
}

$_SESSION["last_activity"] = $currentTime;


    $stmt = $pdo->prepare(
        "SELECT user_id, first_name, last_name, email, role
         FROM users
         WHERE user_id = ?
           AND account_status = 'active'
         LIMIT 1"
    );

    $stmt->execute([$_SESSION["user_id"]]);
    $user = $stmt->fetch();

    if (!$user || $user["role"] !== $requiredRole) {
        http_response_code(403);
        exit("Access denied.");
    }

    return $user;
}
