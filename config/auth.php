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
