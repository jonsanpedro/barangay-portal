
<?php

require_once __DIR__ . "/config/auth.php";

// Only administrators can access this page.
$admin = requireRole("admin");

// Generate a CSRF token for this session.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
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
        Create Announcement | Barangay Chrysanthemum
    </title>

    <link rel="stylesheet" href="admin_dashboard.css">

    <style>

        /* CREATE ANNOUNCEMENT PAGE */

        .create-announcement-page {
            font-family: Arial, sans-serif;
            color: #121B2C;
        }

        .create-announcement-page *,
        .create-announcement-page *::before,
        .create-announcement-page *::after {
            box-sizing: border-box;
        }

        .create-announcement-page .dashboard-header {
            margin-bottom: 26px;
        }

        .create-announcement-page .dashboard-header h1 {
            color: #27358B;
            font-size: 30px;
            font-weight: 700;
            margin: 0 0 8px;
        }

        .create-announcement-page .dashboard-header p {
            color: #64748B;
            font-size: 14px;
            margin: 0;
        }

        /* FORM PANEL */

        .create-announcement-page .announcement-form-panel {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 3px 12px rgba(18, 27, 44, 0.04);
        }

        .create-announcement-page .form-heading {
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid #E2E8F0;
        }

        .create-announcement-page .form-heading h2 {
            color: #121B2C;
            font-size: 21px;
            font-weight: 700;
            margin: 0 0 8px;
        }

        .create-announcement-page .form-heading p {
            color: #64748B;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        /* FORM FIELDS */

        .create-announcement-page .form-group {
            margin-bottom: 24px;
        }

        .create-announcement-page .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 10px;
        }

        .create-announcement-page .form-group input,
        .create-announcement-page .form-group textarea,
        .create-announcement-page .form-group select {
            display: block;
            width: 100%;
            max-width: 100%;
            padding: 13px 15px;
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #121B2C;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 9px;
            outline: none;
            transition: border-color 0.2s ease,
                        box-shadow 0.2s ease;
        }

        .create-announcement-page .form-group input:focus,
        .create-announcement-page .form-group textarea:focus,
        .create-announcement-page .form-group select:focus {
            border-color: #27358B;
            box-shadow: 0 0 0 3px rgba(39, 53, 139, 0.12);
        }

        .create-announcement-page .form-group textarea {
            min-height: 220px;
            resize: vertical;
        }

        .create-announcement-page .form-group select {
            cursor: pointer;
            min-height: 48px;
        }

        .create-announcement-page .field-help {
            margin-top: 8px;
            color: #64748B;
            font-size: 12px;
            line-height: 1.6;
        }

        .create-announcement-page .required-mark {
            color: #DC2626;
        }

        /* STATUS INFORMATION */

        .create-announcement-page .status-information {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 16px 18px;
            margin-bottom: 28px;
        }

        .create-announcement-page .status-information strong {
            display: block;
            color: #27358B;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .create-announcement-page .status-information p {
            font-size: 13px;
            line-height: 1.7;
            color: #64748B;
            margin: 0;
        }

        /* FORM ACTIONS */

        .create-announcement-page .form-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 22px;
            border-top: 1px solid #E2E8F0;
        }

        .create-announcement-page .create-announcement-btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 13px 22px;
            background: #27358B;
            color: #FFFFFF;
            border: 1px solid #27358B;
            border-radius: 9px;
            font-family: Arial, sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .create-announcement-page .create-announcement-btn:hover {
            background: #1C286D;
        }

        .create-announcement-page .create-announcement-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .create-announcement-page .cancel-announcement-btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 13px 22px;
            background: #FFFFFF;
            color: #475569;
            border: 1px solid #CBD5E1;
            border-radius: 9px;
            font-family: Arial, sans-serif;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .create-announcement-page .cancel-announcement-btn:hover {
            background: #F1F5F9;
            color: #27358B;
        }

        /* RESPONSIVE */

        @media (max-width: 600px) {

            .create-announcement-page .dashboard-header h1 {
                font-size: 25px;
            }

            .create-announcement-page .announcement-form-panel {
                padding: 20px 16px;
            }

            .create-announcement-page .form-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .create-announcement-page .create-announcement-btn,
            .create-announcement-page .cancel-announcement-btn {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="admin-layout">

    <!-- SHARED ADMIN SIDEBAR -->
    <?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

    <!-- MAIN CONTENT -->
    <main class="main-content create-announcement-page">

        <header class="dashboard-header">

            <div>

                <h1>Create Announcement</h1>

                <p>
                    Add a new barangay announcement.
                </p>

            </div>

            <div class="admin-profile">
                Barangay Administrator
            </div>

        </header>

        <!-- CREATE ANNOUNCEMENT FORM -->
        <section class="announcement-form-panel">

            <div class="form-heading">

                <h2>Announcement Information</h2>

                <p>
                    Fill out the details below to create
                    a new barangay announcement.
                </p>

            </div>

            <form id="create-announcement-form">

                <!-- CSRF TOKEN -->
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION["csrf_token"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >

                <!-- ANNOUNCEMENT TITLE -->
                <div class="form-group">

                    <label for="announcement-title">
                        Announcement Title
                        <span class="required-mark">*</span>
                    </label>

                    <input
                        type="text"
                        id="announcement-title"
                        name="title"
                        maxlength="200"
                        placeholder="Enter announcement title"
                        required
                    >

                    <p class="field-help">
                        Use a clear and descriptive title
                        for your announcement.
                    </p>

                </div>

                <!-- ANNOUNCEMENT CONTENT -->
                <div class="form-group">

                    <label for="announcement-content">
                        Announcement Content
                        <span class="required-mark">*</span>
                    </label>

                    <textarea
                        id="announcement-content"
                        name="content"
                        rows="8"
                        placeholder="Write the announcement details here..."
                        required
                    ></textarea>

                    <p class="field-help">
                        Include important information such as
                        dates, locations, instructions, and reminders
                        when applicable.
                    </p>

                </div>

                <!-- STATUS -->
                <div class="form-group">

                    <label for="announcement-status">
                        Announcement Status
                        <span class="required-mark">*</span>
                    </label>

                    <select
                        id="announcement-status"
                        name="status"
                        required
                    >

                        <option value="Draft">
                            Save as Draft
                        </option>

                        <option value="Published">
                            Publish Now
                        </option>

                    </select>

                </div>

                <!-- STATUS INFORMATION -->
                <div class="status-information">

                    <strong>Publishing Information</strong>

                    <p>
                        <b>Save as Draft:</b>
                        The announcement will be saved
                        without being published.
                    </p>

                    <p>
                        <b>Publish Now:</b>
                        The announcement will be published
                        and made available through the
                        public announcements feature.
                    </p>

                </div>

                <!-- FORM ACTIONS -->
                <div class="form-actions">

                    <button
                        type="submit"
                        class="create-announcement-btn"
                    >
                        Save Announcement
                    </button>

                    <a
                        href="admin_announcements.php"
                        class="cancel-announcement-btn"
                    >
                        Cancel
                    </a>

                </div>

            </form>

        </section>

    </main>

</div>

<!-- CREATE ANNOUNCEMENT SUBMISSION -->
<script>
"use strict";

document.getElementById("create-announcement-form")
    .addEventListener("submit", async function(event) {

        event.preventDefault();

        const form = event.currentTarget;

        const submitButton = form.querySelector(
            'button[type="submit"]'
        );

        const formData = new FormData(form);

        submitButton.disabled = true;
        submitButton.textContent = "Saving...";

        try {

            const response = await fetch(
                "api/create_announcement.php",
                {
                    method: "POST",
                    body: formData,
                    credentials: "same-origin"
                }
            );

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(
                    data.message ||
                    "Unable to save announcement."
                );
            }

            alert("Announcement created successfully!");

            window.location.href = "admin_announcements.php";

        } catch (error) {

            alert(error.message);

        } finally {

            submitButton.disabled = false;
            submitButton.textContent = "Save Announcement";

        }

    });
</script>

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
