<?php

$isLocal = (
    getenv('APP_ENV') === 'local'
);

if (!$isLocal) {
    require __DIR__ . '/database.production.php';
    return;
}

$host = "127.0.0.1";
$port = "3307";
$dbname = "barangay_portal_db";
$username = "root";
$password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("Database connection failed.");
}