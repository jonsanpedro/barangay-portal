<?php

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/session.php";

function respond($status, $data) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, ["message" => "Method not allowed."]);
}

$email = strtolower(trim($_POST["email"] ?? ""));
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") {
    respond(422, [
        "message" => "Please enter your email and password."
    ]);
}

try {
    $stmt = $pdo->prepare(
    "SELECT user_id, first_name, email,
            password_hash, role, account_status
     FROM users
     WHERE email = ?
       AND role = 'resident'
     LIMIT 1"
);

    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user ||
        !password_verify($password, $user["password_hash"])) {
        respond(401, [
            "message" => "Invalid email or password."
        ]);
    }

    if ($user["account_status"] !== "active") {
        respond(403, [
            "message" => "This account is disabled."
        ]);
    }

    session_regenerate_id(true);

    $_SESSION["user_id"] = (int) $user["user_id"];
    $_SESSION["role"] = $user["role"];

    respond(200, [
        "message" => "Login successful!",
        "user" => [
            "first_name" => $user["first_name"],
            "role" => $user["role"]
        ]
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());

    respond(500, [
        "message" => "Unable to log in right now."
    ]);
}
