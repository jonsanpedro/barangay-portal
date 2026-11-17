
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
         ORDER BY
            created_at DESC,
            announcement_id DESC"
    );

    $stmt->execute();

    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {

    error_log(
        "Admin announcements error: " .
        $exception->getMessage()
    );

    http_response_code(500);
    exit("Unable to load announcements.");
}

function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$totalAnnouncements = count($announcements);
$publishedCount = 0;
$draftCount = 0;

foreach ($announcements as $item) {

    if (strcasecmp((string) $item["status"], "Published") === 0) {
        $publishedCount++;
    } elseif (strcasecmp((string) $item["status"], "Draft") === 0) {
        $draftCount++;
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

    <title>Manage Announcements | Barangay Chrysanthemum</title>

    <link rel="stylesheet" href="admin_dashboard.css">

    <meta
        name="csrf-token"
        content="<?= escapeHtml($_SESSION["csrf_token"]) ?>"
    >

    <style>

        /* ANNOUNCEMENTS PAGE STYLING */

        .announcements-page {
            font-family: Arial, sans-serif;
            color: #121B2C;
        }

        .announcements-page *,
        .announcements-page *::before,
        .announcements-page *::after {
            box-sizing: border-box;
        }

        .announcements-page .dashboard-header {
            margin-bottom: 26px;
        }

        .announcements-page .dashboard-header h1 {
            color: #27358B;
            font-size: 30px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .announcements-page .dashboard-header p {
            color: #64748B;
            font-size: 14px;
        }

        .announcements-page .announcements-panel {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            padding: 26px;
            box-shadow: 0 3px 12px rgba(18, 27, 44, 0.04);
        }

        .announcements-page .announcements-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .announcements-page .announcements-toolbar h2 {
            font-size: 21px;
            color: #121B2C;
            margin: 0 0 7px;
        }

        .announcements-page .announcements-toolbar p {
            font-size: 14px;
            color: #64748B;
            margin: 0;
        }

        .announcements-page .add-announcement-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 9px;
            background: #27358B;
            color: #FFFFFF;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .announcements-page .add-announcement-btn:hover {
            background: #1C286D;
            color: #FFFFFF;
        }

        /* SUMMARY CARDS */

        .announcements-page .announcement-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 26px;
        }

        .announcements-page .summary-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 20px;
        }

        .announcements-page .summary-card span {
            display: block;
            font-size: 13px;
            color: #64748B;
            margin-bottom: 9px;
        }

        .announcements-page .summary-card strong {
            display: block;
            font-size: 28px;
            color: #27358B;
        }

        .announcements-page .summary-card.published {
            background: #F0FDF4;
            border-color: #BBF7D0;
        }

        .announcements-page .summary-card.published strong {
            color: #15803D;
        }

        .announcements-page .summary-card.draft {
            background: #FFFBEB;
            border-color: #FDE68A;
        }

        .announcements-page .summary-card.draft strong {
            color: #A16207;
        }

        /* ANNOUNCEMENT LIST */

        .announcements-page .announcement-list {
            display: grid;
            gap: 18px;
        }

        .announcements-page .announcement-admin-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 13px;
            padding: 23px;
            transition: border-color 0.2s ease,
                        box-shadow 0.2s ease;
        }

        .announcements-page .announcement-admin-card:hover {
            border-color: #CBD5E1;
            box-shadow: 0 4px 16px rgba(18, 27, 44, 0.05);
        }

        .announcements-page .announcement-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 14px;
        }

        .announcements-page .announcement-card-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: #27358B;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .announcements-page .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .announcements-page .status-published {
            color: #166534;
            background: #DCFCE7;
        }

        .announcements-page .status-draft {
            color: #92400E;
            background: #FEF3C7;
        }

        .announcements-page .status-other {
            color: #475569;
            background: #F1F5F9;
        }

        .announcements-page .announcement-content {
            background: #F8FAFC;
            padding: 16px;
            border-radius: 9px;
            color: #475569;
            font-size: 14px;
            line-height: 1.75;
            margin: 0 0 18px;
            overflow-wrap: anywhere;
        }

        .announcements-page .announcement-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 24px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #64748B;
        }

        .announcements-page .announcement-meta strong {
            color: #334155;
        }

        /* ACTION BUTTONS */

        .announcements-page .announcement-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 17px;
            border-top: 1px solid #E2E8F0;
        }

        .announcements-page .edit-announcement-btn,
        .announcements-page .delete-announcement-btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            padding: 10px 16px;
            border-radius: 8px;
            font-family: Arial, sans-serif;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .announcements-page .edit-announcement-btn {
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            color: #1D4ED8;
        }

        .announcements-page .edit-announcement-btn:hover {
            background: #DBEAFE;
        }

        .announcements-page .delete-announcement-btn {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #B91C1C;
        }

        .announcements-page .delete-announcement-btn:hover {
            background: #FEE2E2;
        }

        .announcements-page .delete-announcement-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .announcements-page .empty-state {
            text-align: center;
            padding: 55px 20px;
            color: #64748B;
            background: #F8FAFC;
            border: 1px dashed #CBD5E1;
            border-radius: 12px;
        }

        /* RESPONSIVE DESIGN */

        @media (max-width: 900px) {

            .announcements-page .announcement-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 600px) {

            .announcements-page .dashboard-header h1 {
                font-size: 25px;
            }

            .announcements-page .announcements-panel {
                padding: 16px;
            }

            .announcements-page .announcement-summary {
                grid-template-columns: 1fr;
            }

            .announcements-page .announcement-card-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .announcements-page .announcement-admin-card {
                padding: 17px;
            }

            .announcements-page .announcement-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .announcements-page .edit-announcement-btn,
            .announcements-page .delete-announcement-btn {
                width: 100%;
            }

            .announcements-page .add-announcement-btn {
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
    <main class="main-content announcements-page">

        <header class="dashboard-header">

            <div>

                <h1>Manage Announcements</h1>

                <p>Create and manage barangay announcements.</p>

            </div>

            <div class="admin-profile">
                Barangay Administrator
            </div>

        </header>

        <!-- ANNOUNCEMENTS PANEL -->
        <section class="announcements-panel">

            <div class="announcements-toolbar">

                <div>

                    <h2>All Announcements</h2>

                    <p>View published announcements and drafts.</p>

                </div>

                <a
                    href="admin_create_announcement.php"
                    class="add-announcement-btn"
                >
                    + Create Announcement
                </a>

            </div>

            <!-- SUMMARY -->
            <div class="announcement-summary">

                <div class="summary-card">

                    <span>Total Announcements</span>

                    <strong><?= $totalAnnouncements ?></strong>

                </div>

                <div class="summary-card published">

                    <span>Published</span>

                    <strong><?= $publishedCount ?></strong>

                </div>

                <div class="summary-card draft">

                    <span>Drafts</span>

                    <strong><?= $draftCount ?></strong>

                </div>

            </div>

            <?php if (empty($announcements)): ?>

                <div class="empty-state">
                    No announcements available yet.
                </div>

            <?php else: ?>

                <div class="announcement-list">

                    <?php foreach ($announcements as $announcement): ?>

                        <?php

                            $status = (string) $announcement["status"];

                            $normalizedStatus = strtolower($status);

                            $statusClass = "status-other";

                            if ($normalizedStatus === "published") {
                                $statusClass = "status-published";
                            } elseif ($normalizedStatus === "draft") {
                                $statusClass = "status-draft";
                            }

                        ?>

                        <article class="announcement-admin-card">

                            <div class="announcement-card-header">

                                <h3>
                                    <?= escapeHtml($announcement["title"]) ?>
                                </h3>

                                <span class="status-badge <?= $statusClass ?>">
                                    <?= escapeHtml($status) ?>
                                </span>

                            </div>

                            <div class="announcement-content">
                                <?= nl2br(escapeHtml($announcement["content"])) ?>
                            </div>

                            <div class="announcement-meta">

                                <span>
                                    <strong>Created:</strong>
                                    <?= escapeHtml($announcement["created_at"]) ?>
                                </span>

                                <?php if (!empty($announcement["published_at"])): ?>

                                    <span>
                                        <strong>Published:</strong>
                                        <?= escapeHtml($announcement["published_at"]) ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                            <!-- ACTIONS -->
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

                </div>

            <?php endif; ?>

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

<!-- DELETE ANNOUNCEMENT -->
<script>
"use strict";

const deleteButtons = document.querySelectorAll(
    ".delete-announcement-btn"
);

deleteButtons.forEach(function(button) {

    button.addEventListener(
        "click",
        async function() {

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

            const csrfMeta = document.querySelector(
                'meta[name="csrf-token"]'
            );

            if (!csrfMeta) {
                alert(
                    "Security token is missing. Please refresh the page."
                );
                return;
            }

            const csrfToken = csrfMeta.content;

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

                alert(
                    "Announcement deleted successfully!"
                );

                window.location.reload();

            } catch (error) {

                alert(error.message);

                this.disabled = false;
                this.textContent = "Delete Announcement";

            }
        }
    );
});
</script>

</body>
</html>
