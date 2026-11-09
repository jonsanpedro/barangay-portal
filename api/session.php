<?php

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION["user_id"])) {
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
