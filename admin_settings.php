
<?php

require_once __DIR__ . "/config/auth.php";

$admin = requireRole("admin");

function e($value): string {
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// ==================================
// CSRF PROTECTION
// ==================================
if (empty($_SESSION["settings_csrf"])) {
    $_SESSION["settings_csrf"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "";

// Flash message after successful update.
if (isset($_SESSION["settings_flash"])) {

    $message = $_SESSION["settings_flash"]["message"];
    $messageType = $_SESSION["settings_flash"]["type"];

    unset($_SESSION["settings_flash"]);
}

// ==================================
// HANDLE FORM SUBMISSIONS
// ==================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = (string) ($_POST["csrf_token"] ?? "");
    $action = (string) ($_POST["action"] ?? "");

    if (!hash_equals($_SESSION["settings_csrf"], $token)) {

        $message = "Invalid security token. Please refresh the page.";
        $messageType = "error";

    } else {

        try {

            // Get latest administrator account.
            $stmt = $pdo->prepare(
                "SELECT user_id, password_hash
                 FROM users
                 WHERE user_id = ?
                   AND role = 'admin'
                   AND account_status = 'active'
                 LIMIT 1"
            );

            $stmt->execute([$admin["user_id"]]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$account) {
                http_response_code(403);
                exit("Administrator account is unavailable.");
            }

            // ==================================
            // UPDATE ADMIN PROFILE
            // ==================================
            if ($action === "update_profile") {

                $firstName = trim(
                    (string) ($_POST["first_name"] ?? "")
                );

                $lastName = trim(
                    (string) ($_POST["last_name"] ?? "")
                );

                $currentPassword = (string) (
                    $_POST["current_password"] ?? ""
                );

                if (
                    $firstName === "" ||
                    $lastName === "" ||
                    $currentPassword === ""
                ) {
                    throw new RuntimeException(
                        "Please complete all required fields."
                    );
                }

                if (
                    mb_strlen($firstName) > 100 ||
                    mb_strlen($lastName) > 100
                ) {
                    throw new RuntimeException(
                        "First name and last name must not exceed 100 characters."
                    );
                }

                if (!password_verify(
                    $currentPassword,
                    $account["password_hash"]
                )) {
                    throw new RuntimeException(
                        "Current password is incorrect."
                    );
                }

                // Update names only.
                // Administrator email remains unchanged.
                $update = $pdo->prepare(
                    "UPDATE users
                     SET first_name = ?,
                         last_name = ?
                     WHERE user_id = ?
                       AND role = 'admin'
                       AND account_status = 'active'"
                );

                $update->execute([
                    $firstName,
                    $lastName,
                    $admin["user_id"]
                ]);

                $_SESSION["settings_flash"] = [
                    "message" => "Admin profile updated successfully.",
                    "type" => "success"
                ];

            // ==================================
            // CHANGE ADMIN PASSWORD
            // ==================================
            } elseif ($action === "change_password") {

                $currentPassword = (string) (
                    $_POST["current_password"] ?? ""
                );

                $newPassword = (string) (
                    $_POST["new_password"] ?? ""
                );

                $confirmPassword = (string) (
                    $_POST["confirm_password"] ?? ""
                );

                if (
                    $currentPassword === "" ||
                    $newPassword === "" ||
                    $confirmPassword === ""
                ) {
                    throw new RuntimeException(
                        "Please complete all password fields."
                    );
                }

                if (!password_verify(
                    $currentPassword,
                    $account["password_hash"]
                )) {
                    throw new RuntimeException(
                        "Current password is incorrect."
                    );
                }

                if (strlen($newPassword) < 12) {
                    throw new RuntimeException(
                        "New password must contain at least 12 characters."
                    );
                }

                if (strlen($newPassword) > 72) {
                    throw new RuntimeException(
                        "New password must not exceed 72 bytes."
                    );
                }

                if ($newPassword !== $confirmPassword) {
                    throw new RuntimeException(
                        "New passwords do not match."
                    );
                }

                if (password_verify(
                    $newPassword,
                    $account["password_hash"]
                )) {
                    throw new RuntimeException(
                        "New password must be different from your current password."
                    );
                }

                $newHash = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

                $update = $pdo->prepare(
                    "UPDATE users
                     SET password_hash = ?
                     WHERE user_id = ?
                       AND role = 'admin'
                       AND account_status = 'active'"
                );

                $update->execute([
                    $newHash,
                    $admin["user_id"]
                ]);

                // Regenerate session after password change.
                session_regenerate_id(true);

                $_SESSION["settings_flash"] = [
                    "message" => "Password changed successfully.",
                    "type" => "success"
                ];

            } else {

                throw new RuntimeException(
                    "Invalid settings action."
                );
            }

            // Prevent duplicate form submissions.
            header("Location: admin_settings.php");
            exit;

        } catch (RuntimeException $exception) {

            $message = $exception->getMessage();
            $messageType = "error";

        } catch (PDOException $exception) {

            error_log(
                "Admin settings error: " .
                $exception->getMessage()
            );

            $message = "Unable to save settings. Please try again.";
            $messageType = "error";
        }
    }
}

// ==================================
// LOAD ADMIN PROFILE
// ==================================
try {

    $stmt = $pdo->prepare(
        "SELECT
            user_id,
            first_name,
            last_name,
            email,
            username,
            role,
            account_status,
            created_at
         FROM users
         WHERE user_id = ?
           AND role = 'admin'
           AND account_status = 'active'
         LIMIT 1"
    );

    $stmt->execute([$admin["user_id"]]);

    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        http_response_code(403);
        exit("Admin account not found.");
    }

} catch (PDOException $exception) {

    error_log(
        "Settings loading error: " .
        $exception->getMessage()
    );

    http_response_code(500);
    exit("Unable to load settings.");
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
        Admin Settings | Barangay Chrysanthemum
    </title>

    <link rel="stylesheet" href="admin_dashboard.css">

    <style>
        .settings-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 26px;
        }

        .settings-header h1 {
            color: #27358B;
            margin: 0 0 8px;
        }

        .settings-header p {
            color: #667085;
            margin: 0;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
            align-items: start;
        }

        .settings-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 26px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.05);
            border-top: 4px solid #F4B83F;
        }

        .settings-card h2 {
            color: #27358B;
            font-size: 20px;
            margin: 0 0 8px;
        }

        .settings-description {
            color: #667085;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #344054;
            margin-bottom: 8px;
        }

        .form-group input {
            display: block;
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            color: #1d2939;
        }

        .form-group input:focus {
            outline: none;
            border-color: #27358B;
            box-shadow: 0 0 0 3px rgba(39,53,139,0.1);
        }

        .form-group input[readonly] {
            background: #f2f4f7;
            color: #667085;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .settings-btn {
            display: inline-block;
            padding: 12px 20px;
            background: #27358B;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
        }

        .settings-btn:hover {
            background: #1C286D;
        }

        .settings-btn:focus-visible {
            outline: 3px solid #F4B83F;
            outline-offset: 3px;
        }

        .settings-alert {
            border-radius: 9px;
            padding: 15px 18px;
            margin-bottom: 22px;
            font-size: 14px;
        }

        .settings-alert.success {
            background: #dcfce7;
            color: #166534;
        }

        .settings-alert.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .settings-note {
            background: #f8f9fc;
            border-left: 4px solid #F4B83F;
            padding: 14px;
            border-radius: 6px;
            color: #475467;
            font-size: 13px;
            line-height: 1.6;
            margin-top: 20px;
        }

        .settings-security {
            margin-top: 22px;
        }

        .security-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #eaecf0;
            font-size: 14px;
        }

        .security-row span:first-child {
            color: #667085;
        }

        .security-row strong {
            color: #27358B;
        }

        @media (max-width: 1000px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .settings-card {
                padding: 18px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .security-row {
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body>

<div class="admin-layout">

    <!-- SHARED ADMIN SIDEBAR -->
    <?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <header class="settings-header">

            <div>
                <h1>Admin Settings</h1>

                <p>
                    Manage your administrator profile
                    and account security.
                </p>
            </div>

            <div class="admin-profile">
                Barangay Administrator
            </div>

        </header>

        <!-- FEEDBACK MESSAGE -->
        <?php if ($message !== ""): ?>

            <div
                class="settings-alert <?= e($messageType) ?>"
                role="alert"
            >
                <?= e($message) ?>
            </div>

        <?php endif; ?>

        <div class="settings-grid">

            <!-- ADMIN PROFILE -->
            <section class="settings-card">

                <h2>Admin Profile</h2>

                <p class="settings-description">
                    Update your administrator name.
                    Enter your current password to confirm changes.
                </p>

                <form
                    method="POST"
                    action="admin_settings.php"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION["settings_csrf"]) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="update_profile"
                    >

                    <div class="form-row">

                        <div class="form-group">

                            <label for="first_name">
                                First Name
                            </label>

                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                maxlength="100"
                                value="<?= e($profile["first_name"]) ?>"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label for="last_name">
                                Last Name
                            </label>

                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                maxlength="100"
                                value="<?= e($profile["last_name"]) ?>"
                                required
                            >

                        </div>

                    </div>

                    <div class="form-group">

                        <label for="username">
                            Username
                        </label>

                        <input
                            type="text"
                            id="username"
                            value="<?= e($profile["username"] ?: "Not configured") ?>"
                            readonly
                        >

                    </div>

                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>

                        <input
                            type="text"
                            id="email"
                            value="<?= e($profile["email"] ?: "Not configured") ?>"
                            readonly
                        >

                    </div>

                    <div class="form-group">

                        <label for="profile_password">
                            Current Password
                        </label>

                        <input
                            type="password"
                            id="profile_password"
                            name="current_password"
                            autocomplete="current-password"
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        class="settings-btn"
                    >
                        Save Profile Changes
                    </button>

                </form>

                <div class="settings-note">
                    Your administrator email is currently
                    not configured. Email editing is disabled
                    to avoid conflicts with existing resident
                    accounts. This does not affect the
                    portal's Gmail notification sender.
                </div>

            </section>

            <!-- CHANGE PASSWORD -->
            <section class="settings-card">

                <h2>Change Password</h2>

                <p class="settings-description">
                    Keep your administrator account secure
                    by using a strong and unique password.
                </p>

                <form
                    method="POST"
                    action="admin_settings.php"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION["settings_csrf"]) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="change_password"
                    >

                    <div class="form-group">

                        <label for="current_password">
                            Current Password
                        </label>

                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            autocomplete="current-password"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="new_password">
                            New Password
                        </label>

                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            minlength="12"
                            autocomplete="new-password"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm New Password
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            minlength="12"
                            autocomplete="new-password"
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        class="settings-btn"
                    >
                        Update Password
                    </button>

                </form>

                <div class="settings-note">
                    Use at least 12 characters.
                    Avoid reusing passwords from other accounts.
                    Keep your new password private.
                </div>

            </section>

        </div>

        <!-- ACCOUNT INFORMATION -->
        <section class="settings-card settings-security">

            <h2>Account Information</h2>

            <p class="settings-description">
                Your current administrator account details.
            </p>

            <div class="security-row">
                <span>Account Role</span>
                <strong>Administrator</strong>
            </div>

            <div class="security-row">

                <span>Account Status</span>

                <strong>
                    <?= e(ucfirst($profile["account_status"])) ?>
                </strong>

            </div>

            <div class="security-row">

                <span>Date Registered</span>

                <strong>
                    <?= e($profile["created_at"]) ?>
                </strong>

            </div>

            <div class="security-row">
                <span>Account Access</span>
                <strong>Admin Only</strong>
            </div>

        </section>

    </main>

</div>

<!-- ADMIN LOGOUT -->
<script>
"use strict";

const logoutForm = document.getElementById(
    "admin-logout-form"
);

if (logoutForm) {

    logoutForm.addEventListener(
        "submit",
        async function(event) {

            event.preventDefault();

            try {

                const response = await fetch(
                    "api/logout.php",
                    {
                        method: "POST",
                        credentials: "same-origin"
                    }
                );

                if (!response.ok) {
                    throw new Error("Logout failed.");
                }

                window.location.href = "index.html";

            } catch (error) {

                alert(
                    "Unable to log out. Please try again."
                );

            }
        }
    );
}
</script>

</body>
</html>
