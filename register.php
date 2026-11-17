
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Resident Registration | Barangay Chrysanthemum</title>

    <link rel="stylesheet"
          href="register.css?v=<?= filemtime(__DIR__ . '/register.css') ?>">
</head>

<body>

<div class="register-page">

    <main class="register-card">

        <!-- LEFT BRANDING PANEL -->
        <section class="branding-panel">

            <div class="branding-content">

                <div class="brand-seal">
                    <img
                        src="images/barangay_logo_updated.png"
                        alt="Barangay Chrysanthemum Logo"
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

                <h2>Resident Registration</h2>

                <p class="brand-description">
                    Incident Reporting and Public Service Request
                    Management System
                </p>

            </div>

            <div class="branding-footer">
                <span class="footer-dot"></span>
                Serving Our Community Through Technology
            </div>

        </section>

        <!-- RIGHT REGISTRATION PANEL -->
        <section class="form-panel">

            <div class="form-content">

                <div class="register-icon" aria-hidden="true">
                    <svg width="30" height="30"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="1.8"
                         stroke-linecap="round"
                         stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M19 8v6M16 11h6"/>
                    </svg>
                </div>

                <h2 class="welcome-title">
                    Create Your Account
                </h2>

                <p class="welcome-description">
                    Register to report incidents and track your
                    service requests.
                </p>

                <form action="" method="POST"
                      id="registration-form">

                    <div class="form-grid">

                        <div class="form-group">
                            <label for="first_name">
                                First Name <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                placeholder="Enter first name"
                                autocomplete="given-name"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="last_name">
                                Last Name <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                placeholder="Enter last name"
                                autocomplete="family-name"
                                required
                            >
                        </div>

                        <div class="form-group full-width">
                            <label for="email">
                                Email Address <span>*</span>
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="Enter your email address"
                                autocomplete="email"
                                required
                            >
                        </div>

                        <div class="form-group full-width">
                            <label for="contact_number">
                                Contact Number <span>*</span>
                            </label>

                            <input
                                type="tel"
                                id="contact_number"
                                name="contact_number"
                                placeholder="09XXXXXXXXX"
                                pattern="09[0-9]{9}"
                                maxlength="11"
                                autocomplete="tel"
                                required
                            >

                            <small>
                                Enter an 11-digit Philippine mobile number.
                            </small>
                        </div>

                        <div class="form-group full-width">
                            <label for="address">
                                Barangay Address <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="address"
                                name="address"
                                placeholder="House no., street, subdivision"
                                autocomplete="street-address"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="password">
                                Password <span>*</span>
                            </label>

                            <div class="password-wrapper">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="Create a password"
                                    autocomplete="new-password"
                                    minlength="8"
                                    required
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="password"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                >
                                    Show
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">
                                Confirm Password <span>*</span>
                            </label>

                            <div class="password-wrapper">
                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    placeholder="Confirm your password"
                                    autocomplete="new-password"
                                    minlength="8"
                                    required
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="confirm_password"
                                    aria-label="Show confirm password"
                                    aria-pressed="false"
                                >
                                    Show
                                </button>
                            </div>
                        </div>

                    </div>

                    <p class="password-hint">
                        Use at least 8 characters for your password.
                    </p>

                    <p id="registration-message"
                       role="alert"
                       aria-live="polite"></p>

                    <button type="submit"
                            class="register-button">
                        Create Resident Account
                    </button>

                </form>

                <p class="login-link">
                    Already have an account?
                    <a href="login.php">Login here</a>
                </p>

                <div class="security-notice">
                    <span aria-hidden="true">🔒</span>
                    <span>
                        Your account is intended for accessing
                        resident services and tracking your
                        submitted reports.
                    </span>
                </div>

                <p class="footer-text">
                    Barangay Chrysanthemum Resident Registration
                </p>

            </div>

        </section>

    </main>

    <footer class="page-footer">
        &copy; <?= date("Y") ?> Barangay Chrysanthemum.
        All Rights Reserved.
    </footer>

</div>

<script>
"use strict";

document.querySelectorAll(".password-toggle")
    .forEach(function(button) {

        button.addEventListener("click", function() {

            const target = document.getElementById(
                this.dataset.target
            );

            if (!target) return;

            const isHidden = target.type === "password";

            target.type = isHidden ? "text" : "password";

            this.textContent = isHidden ? "Hide" : "Show";

            this.setAttribute(
                "aria-label",
                (isHidden ? "Hide " : "Show ") +
                (this.dataset.target === "confirm_password"
                    ? "confirm password"
                    : "password")
            );

            this.setAttribute(
                "aria-pressed",
                String(isHidden)
            );
        });
    });

document.getElementById("registration-form")
    .addEventListener("submit", function(event) {

        const password =
            document.getElementById("password").value;

        const confirmPassword =
            document.getElementById("confirm_password").value;

        const message =
            document.getElementById("registration-message");

        message.textContent = "";

        if (password !== confirmPassword) {

            event.preventDefault();

            message.textContent =
                "Passwords do not match. Please try again.";

            document.getElementById(
                "confirm_password"
            ).focus();
        }
    });
</script>

</body>
</html>
