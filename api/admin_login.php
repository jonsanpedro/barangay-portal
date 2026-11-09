<?php
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/session.php";

function respond($status, $message) {
    http_response_code($status);
    echo json_encode(["message" => $message]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, "Method not allowed.");
}

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

if ($username === "" || $password === "") {
    respond(422, "Enter your username and password.");
}

try {
    $stmt = $pdo->prepare(
        "SELECT user_id, password_hash
         FROM users
         WHERE username = ?
           AND role = 'admin'
           AND account_status = 'active'
         LIMIT 1"
    );

    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (
        !$admin ||
        !password_verify($password, $admin["password_hash"])
    ) {
        respond(401, "Invalid username or password.");
    }

    session_regenerate_id(true);

    $_SESSION["user_id"] = (int) $admin["user_id"];
    $_SESSION["role"] = "admin";

    respond(200, "Admin login successful!");

} catch (PDOException $e) {
    error_log($e->getMessage());
    respond(500, "Unable to log in right now.");
}
