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

/* LOGIN CSRF TOKEN VERIFICATION */

$submittedToken = $_POST["csrf_token"] ?? "";
$sessionToken = $_SESSION["login_csrf_token"] ?? "";

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === "" ||
    !hash_equals($sessionToken, $submittedToken)
) {
    respond(403, [
        "message" => "Invalid login security token. Please refresh the page."
    ]);
}


$emailInput = $_POST["email"] ?? "";
$password = $_POST["password"] ?? "";

if (!is_string($emailInput) || !is_string($password)) {
    respond(422, [
        "message" => "Invalid login input."
    ]);
}

$email = strtolower(trim($emailInput));

if (
    $email === "" ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 254 ||
    $password === "" ||
    strlen($password) > 1024
) {
    respond(422, [
        "message" => "Please enter a valid email and password."
    ]);
}


/* LOGIN RATE LIMIT CHECK */

$maxAttempts = 5;

$ipAddress = $_SERVER["REMOTE_ADDR"] ?? "unknown";

try {
    $rateLimitStmt = $pdo->prepare(
        "SELECT COUNT(*)
        FROM login_attempts
        WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        AND email = ?"
    );

    $rateLimitStmt->execute([$email]);

    $failedAttempts = (int) $rateLimitStmt->fetchColumn();

    if ($failedAttempts >= $maxAttempts) {
        header("Retry-After: 900");

        respond(429, [
            "message" => "Too many failed login attempts. Please try again later."
        ]);
    }

} catch (PDOException $e) {
    error_log($e->getMessage());

    respond(500, [
        "message" => "Unable to verify login availability."
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

    // Record the failed login attempt.
    $attemptStmt = $pdo->prepare(
        "INSERT INTO login_attempts
         (email, ip_address)
         VALUES (?, ?)"
    );

    $attemptStmt->execute([$email, $ipAddress]);

    respond(401, [
        "message" => "Invalid email or password."
    ]);
}

    if ($user["account_status"] !== "active") {
        respond(403, [
            "message" => "This account is disabled."
        ]);
    }


/* Clear failed attempts for this account
   after successful password verification. */

    $clearAttemptsStmt = $pdo->prepare(
        "DELETE FROM login_attempts
        WHERE email = ?"
    );

    $clearAttemptsStmt->execute([$email]);

    if (!session_regenerate_id(true)) {
    error_log("Failed to regenerate session ID.");

    respond(500, [
        "message" => "Unable to establish a secure login session."
    ]);
}

unset($_SESSION["incident_csrf_token"]);
unset($_SESSION["login_csrf_token"]);

$_SESSION["user_id"] = (int) $user["user_id"];
$_SESSION["role"] = $user["role"];
$_SESSION["last_activity"] = time();


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
