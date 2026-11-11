<?php

require_once __DIR__ . "/config/auth.php";

// Only administrators can access this page.
$admin = requireRole("admin");

// Generate a CSRF token for deletion requests.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Retrieve all announcements, including drafts.
try {
    $stmt = $pdo->prepare(
        "SELECT
            announcement_id,
            title,
            content,
            status,
            created_at,
            published_at
         FROM announcements
         ORDER BY created_at DESC, announcement_id DESC"
    );

    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log(
        "Admin announcements error: " . $e->getMessage()
    );

    http_response_code(500);
    exit("Unable to load announcements.");
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

    <title>Manage Announcements | Barangay Chrysanthemum</title>

    <link rel="stylesheet" href="admin_dashboard.css">

    <meta
    name="csrf-token"
    content="<?= htmlspecialchars(
        $_SESSION["csrf_token"],
        ENT_QUOTES,
        "UTF-8"
    ) ?>"
>

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

        <div class="sidebar-footer">
            <form id="admin-logout-form">
                <button type="submit" class="logout-button">
                    Logout
                </button>
            </form>
        </div>

    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <header class="dashboard-header">

            <div>
                <h1>Manage Announcements</h1>
                <p>
                    Create and manage barangay announcements.
                </p>
            </div>

            <div class="admin-profile">
                Barangay Administrator
            </div>

        </header>

        <section class="recent-reports">

            <div class="section-heading">
                <h2>All Announcements</h2>
                <p>
                    View published announcements and drafts.
                </p>

                <a href="admin_create_announcement.php"
                    class="add-announcement-btn">
                        + Create Announcement
                </a>
            </div>

            <?php if (empty($announcements)): ?>

                <div class="empty-state">
                    No announcements available yet.
                </div>

            <?php else: ?>

                <?php foreach ($announcements as $announcement): ?>

                    <article class="announcement-admin-card">

                        <h3>
                            <?= escapeHtml($announcement["title"]) ?>
                        </h3>

                        <p>
                            <?= nl2br(
                                escapeHtml($announcement["content"])
                            ) ?>
                        </p>

                        <p>
                            <strong>Status:</strong>
                            <?= escapeHtml($announcement["status"]) ?>
                        </p>

                        <p>
                            <strong>Created:</strong>
                            <?= escapeHtml($announcement["created_at"]) ?>
                        </p>

                        <?php if ($announcement["published_at"]): ?>

                            <p>
                                <strong>Published:</strong>
                                <?= escapeHtml(
                                    $announcement["published_at"]
                                ) ?>
                            </p>

                        <?php endif; ?>

                        <div class="announcement-actions">

                            <a
                                href="admin_edit_announcement.php?id=<?= (int) $announcement["announcement_id"] ?>"
                                class="edit-announcement-btn"
                            >
                                Edit Announcement
                            </a>

                            <button
                                type="button"
                                class="delete-announcement-btn"
                                data-announcement-id="<?= (int) $announcement["announcement_id"] ?>"
                                data-announcement-title="<?= escapeHtml($announcement["title"]) ?>"
                            >
                                Delete Announcement
                            </button>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php endif; ?>

        </section>

    </main>

</div>

<script>
document.getElementById("admin-logout-form")
    .addEventListener("submit", async function(event) {

        event.preventDefault();

        try {
            const response = await fetch("api/logout.php", {
                method: "POST",
                credentials: "same-origin"
            });

            if (!response.ok) {
                throw new Error("Logout failed.");
            }

            window.location.href = "index.html";

        } catch (error) {
            alert("Unable to log out. Please try again.");
        }
    });
</script>


<script>
document.querySelectorAll(".delete-announcement-btn")
    .forEach(function(button) {

        button.addEventListener("click", async function() {

            const announcementId =
                this.dataset.announcementId;

            const announcementTitle =
                this.dataset.announcementTitle;

            const confirmed = confirm(
                'Are you sure you want to permanently delete "' +
                announcementTitle +
                '"?\n\nThis action cannot be undone.'
            );

            if (!confirmed) {
                return;
            }

            const csrfToken = document.querySelector(
                'meta[name="csrf-token"]'
            ).content;

            const formData = new FormData();
            formData.append(
                "announcement_id",
                announcementId
            );
            formData.append(
                "csrf_token",
                csrfToken
            );

            this.disabled = true;
            this.textContent = "Deleting...";

            try {
                const response = await fetch(
                    "api/delete_announcement.php",
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
                        "Unable to delete announcement."
                    );
                }

                alert("Announcement deleted successfully!");

                window.location.reload();

            } catch (error) {
                alert(error.message);

                this.disabled = false;
                this.textContent = "Delete Announcement";
            }
        });
    });
</script>


</body>
</html>
