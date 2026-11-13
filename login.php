
<?php
require_once __DIR__ . "/config/session.php";

// Generate login CSRF token.
if (empty($_SESSION["login_csrf_token"])) {
    $_SESSION["login_csrf_token"] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Resident Login | Barangay Chrysanthemum</title>

    <link
        rel="stylesheet"
        href="login.css?v=<?= filemtime(__DIR__ . '/login.css') ?>"
    >
</head>
<body>

    <main class="login-container">

        <h1>Resident Login</h1>

        <p>Sign in to report incidents and track your requests.</p>

        <form
            id="login-form"
            action="api/login.php"
            method="POST"
        >

            <!-- Login CSRF protection -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION["login_csrf_token"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <div class="form-group">
                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" id="login-button">
                Login
            </button>

            <p
                id="login-message"
                role="status"
                aria-live="polite"
            ></p>

        </form>

        <p class="register-link">
            Don't have an account?
            <a href="register.php">Register here</a>
        </p>

    </main>

    <script src="login.js?v=2"></script>

</body>
</html>
