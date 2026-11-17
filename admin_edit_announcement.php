
<?php

require_once __DIR__ . "/config/auth.php";

// Only administrators can access this page.
$admin = requireRole("admin");

// Generate a CSRF token if none exists.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Validate announcement ID.
$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$id) {
    http_response_code(400);
    exit("Invalid announcement ID.");
}

// Retrieve the selected announcement.
try {

    $stmt = $pdo->prepare(
        "SELECT
            announcement_id,
            title,
            content,
            status
         FROM announcements
         WHERE announcement_id = ?
         LIMIT 1"
    );

    $stmt->execute([$id]);

    $announcement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$announcement) {
        http_response_code(404);
        exit("Announcement not found.");
    }

} catch (PDOException $e) {

    error_log(
        "Edit announcement error: " .
        $e->getMessage()
    );

    http_response_code(500);
    exit("Unable to load announcement.");
}

function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
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

    <title>Edit Announcement | Barangay Chrysanthemum</title>

    <link rel="stylesheet" href="admin_dashboard.css">

    <style>

        /* EDIT ANNOUNCEMENT PAGE */

        .edit-announcement-page {
            font-family: Arial, sans-serif;
            color: #121B2C;
        }

        .edit-announcement-page *,
        .edit-announcement-page *::before,
        .edit-announcement-page *::after {
            box-sizing: border-box;
        }

        .edit-announcement-page .dashboard-header {
            margin-bottom: 26px;
        }

        .edit-announcement-page .dashboard-header h1 {
            color: #27358B;
            font-size: 30px;
            font-weight: 700;
            margin: 0 0 8px;
        }

        .edit-announcement-page .dashboard-header p {
            color: #64748B;
            font-size: 14px;
            margin: 0;
        }

        /* FORM PANEL */

        .edit-announcement-page .announcement-form-panel {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 3px 12px rgba(18, 27, 44, 0.04);
        }

        .edit-announcement-page .form-heading {
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid #E2E8F0;
        }

        .edit-announcement-page .form-heading h2 {
            color: #121B2C;
            font-size: 21px;
            font-weight: 700;
            margin: 0 0 8px;
        }

        .edit-announcement-page .form-heading p {
            color: #64748B;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        /* FORM FIELDS */

        .edit-announcement-page .form-group {
            margin-bottom: 24px;
        }

        .edit-announcement-page .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 10px;
        }

        .edit-announcement-page .form-group input,
        .edit-announcement-page .form-group textarea,
        .edit-announcement-page .form-group select {
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

        .edit-announcement-page .form-group input:focus,
        .edit-announcement-page .form-group textarea:focus,
        .edit-announcement-page .form-group select:focus {
            border-color: #27358B;
            box-shadow: 0 0 0 3px rgba(39, 53, 139, 0.12);
        }

        .edit-announcement-page .form-group textarea {
            min-height: 220px;
            resize: vertical;
        }

        .edit-announcement-page .form-group select {
            cursor: pointer;
            min-height: 48px;
        }

        .edit-announcement-page .field-help {
            margin-top: 8px;
            color: #64748B;
            font-size: 12px;
            line-height: 1.6;
        }

        .edit-announcement-page .required-mark {
            color: #DC2626;
        }

        /* STATUS INFORMATION */

        .edit-announcement-page .status-information {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 16px 18px;
            margin-bottom: 28px;
        }

        .edit-announcement-page .status-information strong {
            display: block;
            color: #27358B;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .edit-announcement-page .status-information p {
            font-size: 13px;
            line-height: 1.7;
            color: #64748B;
            margin: 0 0 5px;
        }

        .edit-announcement-page .status-information p:last-child {
            margin-bottom: 0;
        }

        /* ACTION BUTTONS */

        .edit-announcement-page .form-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 22px;
            border-top: 1px solid #E2E8F0;
        }

        .edit-announcement-page .save-changes-btn {
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

        .edit-announcement-page .save-changes-btn:hover {
            background: #1C286D;
        }

        .edit-announcement-page .save-changes-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .edit-announcement-page .cancel-announcement-btn {
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

        .edit-announcement-page .cancel-announcement-btn:hover {
            background: #F1F5F9;
            color: #27358B;
        }

        /* RESPONSIVE DESIGN */

        @media (max-width: 600px) {

            .edit-announcement-page .dashboard-header h1 {
                font-size: 25px;
            }

            .edit-announcement-page .announcement-form-panel {
                padding: 20px 16px;
            }

            .edit-announcement-page .form-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .edit-announcement-page .save-changes-btn,
            .edit-announcement-page .cancel-announcement-btn {
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
    <main class="main-content edit-announcement-page">

        <header class="dashboard-header">

            <div>

                <h1>Edit Announcement</h1>

                <p>
                    Update an existing barangay announcement.
                </p>

            </div>

            <div class="admin-profile">
                Barangay Administrator
            </div>

        </header>

        <!-- EDIT ANNOUNCEMENT FORM -->
        <section class="announcement-form-panel">

            <div class="form-heading">

                <h2>Announcement Information</h2>

                <p>
                    Modify the details below and save
                    your changes when finished.
                </p>

            </div>

            <form id="edit-announcement-form">

                <!-- ANNOUNCEMENT ID -->
                <input
                    type="hidden"
                    name="announcement_id"
                    value="<?= (int) $announcement["announcement_id"] ?>"
                >

                <!-- CSRF TOKEN -->
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escapeHtml($_SESSION["csrf_token"]) ?>"
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
                        value="<?= escapeHtml($announcement["title"]) ?>"
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
                    ><?= escapeHtml($announcement["content"]) ?></textarea>

                    <p class="field-help">
                        Update important information such as
                        dates, locations, instructions,
                        and reminders when applicable.
                    </p>

                </div>

                <!-- ANNOUNCEMENT STATUS -->
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

                        <option
                            value="Draft"
                            <?= $announcement["status"] === "Draft"
                                ? "selected" : "" ?>
                        >
                            Draft
                        </option>

                        <option
                            value="Published"
                            <?= $announcement["status"] === "Published"
                                ? "selected" : "" ?>
                        >
                            Published
                        </option>

                    </select>

                </div>

                <!-- STATUS INFORMATION -->
                <div class="status-information">

                    <strong>Publishing Information</strong>

                    <p>
                        <b>Draft:</b>
                        The announcement will remain
                        unpublished and will not appear
                        in public announcements.
                    </p>

                    <p>
                        <b>Published:</b>
                        The announcement will be available
                        through the public announcements feature.
                    </p>

                </div>

                <!-- FORM ACTIONS -->
                <div class="form-actions">

                    <button
                        type="submit"
                        class="save-changes-btn"
                    >
                        Save Changes
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

<!-- EDIT ANNOUNCEMENT SUBMISSION -->
<script>
"use strict";

document.getElementById("edit-announcement-form")
    .addEventListener("submit", async function(event) {

        event.preventDefault();

        const form = event.currentTarget;

        const submitButton = form.querySelector(
            'button[type="submit"]'
        );

        const formData = new FormData(form);

        submitButton.disabled = true;
        submitButton.textContent = "Saving Changes...";

        try {

            const response = await fetch(
                "api/update_announcement.php",
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
                    "Unable to update announcement."
                );
            }

            alert("Announcement updated successfully!");

            window.location.href = "admin_announcements.php";

        } catch (error) {

            alert(error.message);

        } finally {

            submitButton.disabled = false;
            submitButton.textContent = "Save Changes";

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
