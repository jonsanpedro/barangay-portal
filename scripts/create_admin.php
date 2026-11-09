
<?php

// This script can only run through the terminal.
if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit("Forbidden");
}

require_once __DIR__ . "/../config/database.php";

function ask(string $prompt): string {
    echo $prompt;
    return trim(fgets(STDIN));
}

echo "=== Barangay Admin Account Setup ===\n";

$username = ask("Admin username: ");

if (!preg_match('/^[A-Za-z0-9_]{4,50}$/', $username)) {
    exit("Username must be 4-50 letters, numbers, or underscores.\n");
}

$check = $pdo->prepare(
    "SELECT user_id FROM users WHERE username = ? LIMIT 1"
);
$check->execute([$username]);

if ($check->fetch()) {
    exit("Username already exists.\n");
}

$password = ask("Admin password (visible): ");
$confirmPassword = ask("Confirm password (visible): ");

if (strlen($password) < 12) {
    exit("Password must have at least 12 characters.\n");
}

if ($password !== $confirmPassword) {
    exit("Passwords do not match.\n");
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO users
         (first_name, last_name, email, username,
          password_hash, role, account_status)
         VALUES (?, ?, NULL, ?, ?, 'admin', 'active')"
    );

    $stmt->execute([
        "Barangay",
        "Administrator",
        $username,
        $passwordHash
    ]);

    echo "\nAdmin account created successfully!\n";

} catch (PDOException $e) {
    error_log($e->getMessage());
    exit("Unable to create admin account.\n");
}
