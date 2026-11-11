<?php

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["authenticated" => false]);
    exit;
}


/* CHECK SESSION INACTIVITY */

$inactivityLimit = 30 * 60; // 30 minutes
$currentTime = time();
$lastActivity = $_SESSION["last_activity"] ?? null;

if (
    !is_int($lastActivity) ||
    ($currentTime - $lastActivity) >= $inactivityLimit
) {
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

    echo json_encode(["authenticated" => false]);
    exit;
}


$stmt = $pdo->prepare(
    "SELECT user_id, first_name, role
     FROM users
     WHERE user_id = ?
       AND account_status = 'active'
     LIMIT 1"
);

$stmt->execute([$_SESSION["user_id"]]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION = [];
    session_destroy();

    echo json_encode(["authenticated" => false]);
    exit;
}

echo json_encode([
    "authenticated" => true,
    "user" => [
        "first_name" => $user["first_name"],
        "role" => $user["role"]
    ]
]);
