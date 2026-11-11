<?php

require_once __DIR__ . "/config/auth.php";

$admin = requireRole("admin");

$firstName = htmlspecialchars(
    $admin["first_name"],
    ENT_QUOTES,
    "UTF-8"
);


try {
    $stmt = $pdo->query(
        "SELECT
            COUNT(*) AS total_reports,
            COUNT(CASE
                WHEN processing_status = 'Pending'
                THEN 1
            END) AS pending_reports,
            COUNT(CASE
                WHEN processing_status = 'In Progress'
                THEN 1
            END) AS in_progress_reports,
            COUNT(CASE
                WHEN processing_status = 'Resolved'
                THEN 1
            END) AS resolved_reports
         FROM incidents"
    );

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Retrieve the five most recent incident reports.
    $recentStmt = $pdo->query(
        "SELECT
            incident_id,
            reference_number,
            title,
            category,
            processing_status,
            map_approval_status,
            created_at
         FROM incidents
         ORDER BY created_at DESC, incident_id DESC
         LIMIT 5"
    );

    $recentIncidents = $recentStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


} catch (PDOException $e) {
    error_log(
        "Admin dashboard statistics error: " . $e->getMessage()
    );

    http_response_code(500);
    exit("Unable to load dashboard statistics.");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | Barangay Chrysanthemum</title>

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
                <a href="admin_dashboard.php" class="active">
                    Dashboard Overview
                </a>

                <a href="admin_incidents.php">
                    Incident Reports
                </a>

                <a href="#" class="nav-placeholder">
                    Map Approvals
                </a>

                <a href="admin_announcements.php">
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
                    <h1>Dashboard Overview</h1>
                    <p>Welcome, <?= $firstName ?>!</p>
                </div>

                <div class="admin-profile">
                    Barangay Administrator
                </div>
            </header>

            <!-- Summary Cards -->
            
            <section class="stats-grid" aria-label="Incident summary">

                <article class="stat-card">
                    <p>Total Reports</p>
                    <h2><?= (int) $stats["total_reports"] ?></h2>
                </article>

                 <article class="stat-card">
                    <p>Pending Reports</p>
                    <h2><?= (int) $stats["pending_reports"] ?></h2>
                </article>

                <article class="stat-card">
                    <p>In Progress</p>
                    <h2><?= (int) $stats["in_progress_reports"] ?></h2>
                </article>

                <article class="stat-card">
                    <p>Resolved Reports</p>
                    <h2><?= (int) $stats["resolved_reports"] ?></h2>
                </article>

            </section>


        <!-- Recent Reports -->
            
        <section class="recent-reports">

                <div class="section-heading">
                    <h2>Recent Incident Reports</h2>
                    <p>Latest incident submissions</p>

                    <a href="admin_incidents.php"
                        class="view-all-reports-btn">
                        View All Reports
                    </a>
                </div>

                <?php if (empty($recentIncidents)): ?>

                <div class="empty-state">
                    No incident reports available yet.
                </div>

                <?php else: ?>

        <div class="recent-table-wrapper">
            <table class="recent-incidents-table">

                <thead>
                    <tr>
                        <th>Reference Number</th>
                        <th>Incident Title</th>
                        <th>Category</th>
                        <th>Processing Status</th>
                        <th>Date Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($recentIncidents as $incident): ?>

                        <tr>
                            <td>
                                <?= htmlspecialchars(
                                    $incident["reference_number"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $incident["title"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $incident["category"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <?php
                                    $status = $incident["processing_status"];

                                    $statusClass = match ($status) {
                                        "Pending" => "status-pending",
                                        "In Progress" => "status-in-progress",
                                        "Resolved" => "status-resolved",
                                        default => "status-unknown"
                                    };
                                ?>

                                <span class="incident-status <?= $statusClass ?>">
                                    <?= htmlspecialchars(
                                        $status,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $incident["created_at"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <a
                                    href="admin_incident_details.php?id=<?= (int) $incident["incident_id"] ?>"
                                    class="view-incident-btn"
                                >
                                    View Details
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>
        </div>

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


</body>
</html>
