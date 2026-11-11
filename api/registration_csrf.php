<?php

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../config/session.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    header("Allow: GET");

    echo json_encode([
        "message" => "Method not allowed."
    ]);
    exit;
}

if (empty($_SESSION["registration_csrf_token"])) {
    $_SESSION["registration_csrf_token"] =
        bin2hex(random_bytes(32));
}

echo json_encode([
    "csrf_token" => $_SESSION["registration_csrf_token"]
]);
