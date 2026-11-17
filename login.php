
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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Resident Login | Barangay Chrysanthemum</title>

    <link
        rel="stylesheet"
        href="login.css?v=<?= filemtime(__DIR__ . '/login.css') ?>"
    >
</head>

<body>

<div class="login-page">

    <main class="login-card">

        <!-- LEFT BRANDING PANEL -->
        <section class="branding-panel">

            <div class="branding-content">

                <div class="brand-seal">
                    <img
                        src="images/barangay_logo_updated.png"
                        alt="Barangay Chrysanthemum Official Seal"
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

                <h2>Resident Portal</h2>

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

                <div class="login-icon" aria-hidden="true">
                    <svg
                        width="28"
                        height="28"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>

                <h2 class="welcome-title">
                    Welcome Back
                </h2>

                <p class="welcome-description">
                    Sign in to report incidents and
                    track your service requests.
                </p>

                <form
                    id="login-form"
                    action="api/login.php"
                    method="POST"
                >

                    <!-- CSRF Protection -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $_SESSION["login_csrf_token"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >

                    <!-- EMAIL ADDRESS -->
                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>

                        <div class="input-wrapper">

                            <span class="input-icon" aria-hidden="true">
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <rect x="3" y="5" width="18" height="14" rx="2"/>
                                    <path d="m3 7 9 6 9-6"/>
                                </svg>
                            </span>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="Enter your email address"
                                autocomplete="username"
                                required
                            >

                        </div>

                    </div>

                    <!-- PASSWORD -->
                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <div class="input-wrapper">

                            <span class="input-icon" aria-hidden="true">
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <rect x="5" y="10" width="14" height="11" rx="2"/>
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                </svg>
                            </span>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                id="password-toggle"
                                aria-controls="password"
                                aria-label="Show password"
                                aria-pressed="false"
                            >
                                Show
                            </button>

                        </div>

                    </div>

                    <!-- LOGIN ERROR / STATUS MESSAGE -->
                    <p
                        id="login-message"
                        role="status"
                        aria-live="polite"
                    ></p>

                    <!-- LOGIN BUTTON -->
                    <button
                        type="submit"
                        id="login-button"
                    >
                        Sign In to Resident Portal
                    </button>

                </form>

                <!-- REGISTRATION LINK -->
                <p class="register-link">
                    Don't have an account?
                    <a href="register.php">
                        Register here
                    </a>
                </p>

                <div class="security-notice">
                    <span aria-hidden="true">🔒</span>

                    <span>
                        Your account is intended for
                        accessing resident services and
                        tracking your submitted reports.
                    </span>
                </div>

                <p class="footer-text">
                    Barangay Chrysanthemum Resident Access
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

<script src="login.js?v=2"></script>

<!-- PASSWORD VISIBILITY -->
<script>
"use strict";

const passwordToggle = document.getElementById(
    "password-toggle"
);

const passwordInput = document.getElementById(
    "password"
);

if (passwordToggle && passwordInput) {

    passwordToggle.addEventListener(
        "click",
        function() {

            const isHidden =
                passwordInput.type === "password";

            passwordInput.type = isHidden
                ? "text"
                : "password";

            passwordToggle.textContent = isHidden
                ? "Hide"
                : "Show";

            passwordToggle.setAttribute(
                "aria-label",
                isHidden
                    ? "Hide password"
                    : "Show password"
            );

            passwordToggle.setAttribute(
                "aria-pressed",
                String(isHidden)
            );

        }
    );

}
</script>

</body>
</html>
