<?php
require_once __DIR__ . "/config/session.php";

if (isset($_SESSION["user_id"])) {
    require_once __DIR__ . "/config/database.php";

    $stmt = $pdo->prepare(
        "SELECT role FROM users
         WHERE user_id = ? AND account_status = 'active'"
    );
    $stmt->execute([$_SESSION["user_id"]]);
    $user = $stmt->fetch();

    if ($user && $user["role"] === "admin") {
        header("Location: admin_dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Barangay Chrysanthemum</title>
    <link rel="stylesheet" href="admin_login.css">
</head>
<body>
    <main class="login-card">
        <div class="login-icon">BC</div>
        <h1>Barangay Chrysanthemum</h1>
        <p class="subtitle">Administration Portal</p>

        <form id="admin-login-form">
            <label for="admin-username">Username</label>
            <input
                type="text"
                id="admin-username"
                name="username"
                autocomplete="username"
                required
            >

            <label for="admin-password">Password</label>
            <input
                type="password"
                id="admin-password"
                name="password"
                autocomplete="current-password"
                required
            >

            <p id="login-message" role="alert"></p>

            <button type="submit" id="login-button">
                Sign In
            </button>
        </form>

        <p class="footer-text">
            Authorized Barangay Administrators Only
        </p>
    </main>

    <script src="admin_login.js"></script>
</body>
</html>
