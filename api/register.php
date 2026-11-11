
<?php
header("Content-Type: application/json; charset=utf-8");

function respond($status, $message) {
    http_response_code($status);
    echo json_encode(["message" => $message]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, "Method not allowed.");
}

require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/database.php";

// Verify registration CSRF token.
$submittedToken = $_POST["csrf_token"] ?? "";
$sessionToken = $_SESSION["registration_csrf_token"] ?? "";

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === "" ||
    !hash_equals($sessionToken, $submittedToken)
) {
    respond(403, "Invalid security token. Please refresh the page.");
}


/*BASIC REGISTRATION RATE LIMITING*/

$maxAttempts = 5;
$timeWindow = 15 * 60; // 15 minutes
$currentTime = time();

// Create the attempt history if it does not exist.
if (!isset($_SESSION["registration_attempts"])) {
    $_SESSION["registration_attempts"] = [];
}

// Remove attempts older than 15 minutes.
$_SESSION["registration_attempts"] = array_values(
    array_filter(
        $_SESSION["registration_attempts"],
        function ($attemptTime) use ($currentTime, $timeWindow) {
            return $attemptTime > ($currentTime - $timeWindow);
        }
    )
);

// Block requests that exceed the limit.
if (count($_SESSION["registration_attempts"]) >= $maxAttempts) {
    http_response_code(429);
    header("Retry-After: 900");

    echo json_encode([
        "message" => "Too many registration attempts. Please try again later."
    ]);
    exit;
}

// Record this registration attempt.
$_SESSION["registration_attempts"][] = $currentTime;


$firstName = trim($_POST["first_name"] ?? "");
$lastName = trim($_POST["last_name"] ?? "");
$email = strtolower(trim($_POST["email"] ?? ""));
$contact = trim($_POST["contact_number"] ?? "");
$address = trim($_POST["address"] ?? "");
$password = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirm_password"] ?? "";

if (
    $firstName === "" ||
    $lastName === "" ||
    $email === "" ||
    $contact === "" ||
    $address === "" ||
    $password === ""
) {
    respond(422, "Please complete all required fields.");
}

if (
    mb_strlen($firstName, "UTF-8") > 100 ||
    mb_strlen($lastName, "UTF-8") > 100
) {
    respond(422, "First name and last name must not exceed 100 characters.");
}

if (mb_strlen($email, "UTF-8") > 255) {
    respond(422, "Email address must not exceed 255 characters.");
}

if (mb_strlen($address, "UTF-8") > 255) {
    respond(422, "Address must not exceed 255 characters.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, "Invalid email address.");
}

if (!preg_match("/^09[0-9]{9}$/", $contact)) {
    respond(422, "Enter a valid 11-digit mobile number.");
}

if (strlen($password) < 8) {
    respond(422, "Password must have at least 8 characters.");
}

if ($password !== $confirmPassword) {
    respond(422, "Passwords do not match.");
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO users
         (first_name, last_name, email, contact_number,
          address, password_hash, role)
         VALUES (?, ?, ?, ?, ?, ?, 'resident')"
    );

    $stmt->execute([
        $firstName,
        $lastName,
        $email,
        $contact,
        $address,
        $passwordHash
    ]);

    respond(201, "Registration successful! You can now log in.");

} catch (PDOException $e) {

    $mysqlErrorCode = (int) ($e->errorInfo[1] ?? 0);

    if ($mysqlErrorCode === 1062) {
        respond(409, "An account with this email or username may already exist.");
    }

    error_log($e->getMessage());

    respond(500, "Registration failed. Please try again.");
}
