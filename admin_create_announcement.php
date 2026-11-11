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

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Create Announcement | Barangay Chrysanthemum</title>

    <link rel="stylesheet" href="admin_dashboard.css">
</head>

<body>

<div class="admin-layout">

    <!-- Sidebar -->
    <aside class="sidebar">

        <div class="sidebar-brand">
            <h2>BC Portal</h2>
            <p>Administration</p>
        </div>

        <nav class="sidebar-nav" aria-label="Admin navigation">

            <a href="admin_dashboard.php">
                Dashboard Overview
            </a>

            <a href="admin_incidents.php">
                Incident Reports
            </a>

            <a href="#" class="nav-placeholder">
                Map Approvals
            </a>

            <a href="admin_announcements.php"
               class="active">
                Announcements
            </a>

            <a href="#" class="nav-placeholder">
                Residents
            </a>

            <a href="#" class="nav-placeholder">
                Settings
            </a>

        </nav>

    </aside>

    <!-- Main Content -->
    <main class="main-content">

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

        <section class="recent-reports">

            <form id="create-announcement-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION["csrf_token"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >

                <div class="form-group">
                    <label for="announcement-title">
                        Announcement Title
                    </label>

                    <input
                        type="text"
                        id="announcement-title"
                        name="title"
                        maxlength="200"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="announcement-content">
                        Announcement Content
                    </label>

                    <textarea
                        id="announcement-content"
                        name="content"
                        rows="6"
                        required
                    ></textarea>
                </div>

                <div class="form-group">
                    <label for="announcement-status">
                        Status
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

                <button type="submit" class="create-announcement-btn">
                    Save Announcement
                </button>

                <a href="admin_announcements.php">
                    Cancel
                </a>

            </form>

        </section>

    </main>

</div>


<script>
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
                    data.message || "Unable to save announcement."
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



</body>
</html>
