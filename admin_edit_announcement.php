
<?php

require_once __DIR__ . "/config/auth.php";

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
        "SELECT announcement_id, title, content, status
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
    error_log("Edit announcement error: " . $e->getMessage());
    http_response_code(500);
    exit("Unable to load announcement.");
}

function escapeHtml($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit Announcement | Barangay Chrysanthemum</title>

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

            <a href="admin_announcements.php" class="active"
               aria-current="page">
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
                <h1>Edit Announcement</h1>
                <p>Update an existing barangay announcement.</p>
            </div>

            <div class="admin-profile">
                Barangay Administrator
            </div>

        </header>

        <section class="recent-reports">

            <form id="edit-announcement-form">

                <input
                    type="hidden"
                    name="announcement_id"
                    value="<?= (int) $announcement["announcement_id"] ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escapeHtml($_SESSION["csrf_token"]) ?>"
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
                        value="<?= escapeHtml($announcement["title"]) ?>"
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
                    ><?= escapeHtml($announcement["content"]) ?></textarea>

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

                        <option value="Draft"
                            <?= $announcement["status"] === "Draft"
                                ? "selected" : "" ?>>
                            Draft
                        </option>

                        <option value="Published"
                            <?= $announcement["status"] === "Published"
                                ? "selected" : "" ?>>
                            Published
                        </option>

                    </select>

                </div>

                <button type="submit" class="create-announcement-btn">
                    Save Changes
                </button>

                <a href="admin_announcements.php">
                    Cancel
                </a>

            </form>

        </section>

    </main>

</div>


<script>
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
                    data.message || "Unable to update announcement."
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


</body>
</html>
