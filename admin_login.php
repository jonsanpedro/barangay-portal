
<?php

require_once __DIR__ . "/config/session.php";

// Redirect administrators who are already logged in.
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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Admin Login | Barangay Chrysanthemum
    </title>

    <link rel="stylesheet" href="admin_login.css">

</head>

<body>

<div class="login-page">

    <!-- MAIN LOGIN CARD -->
    <main class="login-card">

        <!-- LEFT BRANDING PANEL -->
        <section class="branding-panel">

            <div class="branding-content">

                <div class="brand-seal">
                    <img
                        src="images/barangay_logo_updated.png"
                        alt="Barangay Chrysanthemum Official Logo"
                        class="barangay-logo"
                    >
                </div>

                <p class="brand-eyebrow">
                    OFFICIAL BARANGAY PORTAL
                </p>

                <h1>
                    BARANGAY
                    <span>CHRYSANTHEMUM</span>
                </h1>

                <div class="brand-divider"></div>

                <h2>
                    Administration Portal
                </h2>

                <p class="brand-description">
                    Incident Reporting and Public Service
                    Request Management System
                </p>

            </div>

            <div class="branding-footer">
                <span class="footer-dot"></span>
                Serving Our Community Through Technology
            </div>

        </section>

        <!-- RIGHT LOGIN PANEL -->
        <section class="form-panel">

            <div class="form-content">

                <div class="login-icon">
                    <svg
                        width="30"
                        height="30"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <rect
                            x="4"
                            y="10"
                            width="16"
                            height="11"
                            rx="2"
                        />
                        <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                        <circle cx="12" cy="15.5" r="1"/>
                    </svg>
                </div>

                <h2 class="welcome-title">
                    Welcome Back
                </h2>

                <p class="welcome-description">
                    Sign in to access your administrator dashboard.
                </p>

                <!-- LOGIN FORM -->
                <form id="admin-login-form">

                    <div class="form-group">

                        <label for="admin-username">
                            Username
                        </label>

                        <div class="input-wrapper">

                            <span class="input-icon" aria-hidden="true">
                                <svg
                                    width="19"
                                    height="19"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <circle cx="12" cy="8" r="4"/>
                                    <path
                                        d="M4 21v-2a8 8 0 0 1 16 0v2"
                                    />
                                </svg>
                            </span>

                            <input
                                type="text"
                                id="admin-username"
                                name="username"
                                placeholder="Enter your username"
                                autocomplete="username"
                                required
                            >

                        </div>

                    </div>

                    <div class="form-group">

                        <label for="admin-password">
                            Password
                        </label>

                        <div class="input-wrapper">

                            <span class="input-icon" aria-hidden="true">
                                <svg
                                    width="19"
                                    height="19"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <rect
                                        x="4"
                                        y="10"
                                        width="16"
                                        height="11"
                                        rx="2"
                                    />
                                    <path
                                        d="M8 10V7a4 4 0 0 1 8 0v3"
                                    />
                                </svg>
                            </span>

                            <input
                                type="password"
                                id="admin-password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                id="password-toggle"
                                aria-label="Show password"
                                aria-pressed="false"
                            >
                                Show
                            </button>

                        </div>

                    </div>

                    <p
                        id="login-message"
                        role="alert"
                        aria-live="polite"
                    ></p>

                    <button
                        type="submit"
                        id="login-button"
                    >
                        Sign In to Dashboard
                    </button>

                </form>

                <div class="security-notice">

                    <span aria-hidden="true">🔒</span>

                    <span>
                        This portal is restricted to authorized
                        barangay administrators only.
                    </span>

                </div>

                <p class="footer-text">
                    Authorized Barangay Administrators Only
                </p>

            </div>

        </section>

    </main>

    <footer class="page-footer">
        &copy; <?= date("Y") ?>
        Barangay Chrysanthemum.
        All Rights Reserved.
    </footer>

</div>

<!-- PASSWORD VISIBILITY -->
<script>
"use strict";

const passwordInput = document.getElementById(
    "admin-password"
);

const passwordToggle = document.getElementById(
    "password-toggle"
);

passwordToggle.addEventListener("click", function() {

    const isHidden = passwordInput.type === "password";

    passwordInput.type = isHidden ? "text" : "password";

    passwordToggle.textContent = isHidden ? "Hide" : "Show";

    passwordToggle.setAttribute(
        "aria-label",
        isHidden ? "Hide password" : "Show password"
    );

    passwordToggle.setAttribute(
        "aria-pressed",
        String(isHidden)
    );

});
</script>

<!-- EXISTING LOGIN AUTHENTICATION -->
<script src="admin_login.js"></script>

</body>
</html>
