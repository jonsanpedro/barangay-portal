
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

require_once __DIR__ . "/../config/database.php";

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
    if ($e->getCode() === "23000") {
        respond(409, "This email address is already registered.");
    }

    error_log($e->getMessage());
    respond(500, "Registration failed. Please try again.");
}
