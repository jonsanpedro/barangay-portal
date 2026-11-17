
<?php
require_once __DIR__ . "/config/auth.php";

$admin = requireRole("admin");

function e($value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function priorityLevel(int $count): string {
    if ($count >= 6) return "High";
    if ($count >= 3) return "Medium";
    return "Low";
}

try {
    $stmt = $pdo->query(
        "SELECT
            COUNT(*) AS total_reports,
            COUNT(CASE WHEN processing_status = 'Pending'
                THEN 1 END) AS pending_reports,
            COUNT(CASE WHEN processing_status = 'In Progress'
                THEN 1 END) AS in_progress_reports,
            COUNT(CASE WHEN processing_status = 'Resolved'
                THEN 1 END) AS resolved_reports
         FROM incidents"
    );

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $recentStmt = $pdo->query(
        "SELECT
            i.incident_id,
            i.reference_number,
            i.title,
            i.category,
            i.processing_status,
            i.created_at,
            COALESCE(s.support_count, 0) AS support_count
         FROM incidents i
         LEFT JOIN (
             SELECT incident_id, COUNT(*) AS support_count
             FROM incident_supports
             GROUP BY incident_id
         ) s ON s.incident_id = i.incident_id
         ORDER BY i.created_at DESC, i.incident_id DESC
         LIMIT 5"
    );

    $recentIncidents = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    $priorityStmt = $pdo->query(
        "SELECT
            i.incident_id,
            i.reference_number,
            i.title,
            i.category,
            i.processing_status,
            COUNT(s.support_id) AS support_count
         FROM incidents i
         LEFT JOIN incident_supports s
             ON i.incident_id = s.incident_id
         WHERE i.map_approval_status = 'Approved'
           AND i.processing_status <> 'Resolved'
         GROUP BY
             i.incident_id,
             i.reference_number,
             i.title,
             i.category,
             i.processing_status
         ORDER BY support_count DESC, i.created_at DESC
         LIMIT 5"
    );

    $priorityIncidents = $priorityStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {
    error_log(
        "Dashboard error: " . $exception->getMessage()
    );

    http_response_code(500);
    exit("Unable to load dashboard.");
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
        Admin Dashboard | Barangay Chrysanthemum
    </title>

    <link rel="stylesheet" href="admin_dashboard.css">
</head>

<body>

<div class="admin-layout">

    <!-- ADMIN SIDEBAR -->

    <aside class="sidebar">

        <div class="sidebar-brand">
            <h2>BC Portal</h2>
            <p>Administration</p>
        </div>

        <nav
            class="sidebar-nav"
            aria-label="Admin navigation"
        >

            <a
                href="admin_dashboard.php"
                class="active"
            >
                Dashboard Overview
            </a>

            <a href="admin_incidents.php">
                Incident Reports
            </a>

            <a href="admin_incidents.php">
                Map Approvals
            </a>

            <!-- NEW COMMUNITY CONTRIBUTIONS TAB -->

            <a href="admin_public_contributions.php">
                Community Contributions
            </a>

            <a href="admin_announcements.php">
                Announcements
            </a>

        </nav>

        <div class="sidebar-footer">

            <form id="admin-logout-form">

                <button
                    type="submit"
                    class="logout-button"
                >
                    Logout
                </button>

            </form>

        </div>

    </aside>

    <!-- MAIN DASHBOARD CONTENT -->

    <main class="main-content">

        <header class="dashboard-header">

            <div>
                <h1>Dashboard Overview</h1>

                <p>
                    Welcome,
                    <?= e($admin["first_name"]) ?>!
                </p>
            </div>

            <div class="admin-profile">
                Barangay Administrator
            </div>

        </header>

        <!-- INCIDENT STATISTICS -->

        <section
            class="stats-grid"
            aria-label="Incident summary"
        >

            <article class="stat-card">
                <p>Total Reports</p>

                <h2>
                    <?= (int) $stats["total_reports"] ?>
                </h2>
            </article>

            <article class="stat-card">
                <p>Pending Reports</p>

                <h2>
                    <?= (int) $stats["pending_reports"] ?>
                </h2>
            </article>

            <article class="stat-card">
                <p>In Progress</p>

                <h2>
                    <?= (int) $stats["in_progress_reports"] ?>
                </h2>
            </article>

            <article class="stat-card">
                <p>Resolved Reports</p>

                <h2>
                    <?= (int) $stats["resolved_reports"] ?>
                </h2>
            </article>

        </section>

        <!-- MOST SUPPORTED INCIDENTS -->

        <section class="recent-reports">

            <div class="section-heading">

                <div>
                    <h2>Most Supported Incidents</h2>

                    <p>
                        Approved, unresolved incidents ranked
                        by community support.
                    </p>
                </div>

            </div>

            <?php if (empty($priorityIncidents)): ?>

                <div class="empty-state">
                    No approved unresolved incidents available.
                </div>

            <?php else: ?>

                <div class="recent-table-wrapper">

                    <table class="recent-incidents-table">

                        <thead>
                            <tr>
                                <th>Reference Number</th>
                                <th>Incident</th>
                                <th>Support Count</th>
                                <th>Priority Level</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($priorityIncidents as $incident): ?>

                            <?php
                                $count = (int) $incident["support_count"];
                                $priority = priorityLevel($count);
                            ?>

                            <tr>

                                <td>
                                    <?= e($incident["reference_number"]) ?>
                                </td>

                                <td>
                                    <?= e($incident["title"]) ?>
                                </td>

                                <td>
                                    <?= $count ?>
                                </td>

                                <td>

                                    <span
                                        class="priority-badge priority-<?= strtolower($priority) ?>"
                                    >
                                        <?= e($priority) ?>
                                    </span>

                                </td>

                                <td>
                                    <?= e($incident["processing_status"]) ?>
                                </td>

                                <td>

                                    <a
                                        class="view-incident-btn"
                                        href="admin_incident_details.php?id=<?= (int) $incident["incident_id"] ?>"
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

        <!-- RECENT INCIDENT REPORTS -->

        <section class="recent-reports">

            <div class="section-heading">

                <div>

                    <h2>Recent Incident Reports</h2>

                    <p>
                        Latest incident submissions
                    </p>

                </div>

                <a
                    href="admin_incidents.php"
                    class="view-all-reports-btn"
                >
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
                                <th>Supports</th>
                                <th>Date Submitted</th>
                                <th>Action</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($recentIncidents as $incident): ?>

                            <?php
                                $status = $incident["processing_status"];

                                $statusClass = match ($status) {
                                    "Pending" => "status-pending",
                                    "In Progress" => "status-in-progress",
                                    "Resolved" => "status-resolved",
                                    default => "status-unknown"
                                };
                            ?>

                            <tr>

                                <td>
                                    <?= e($incident["reference_number"]) ?>
                                </td>

                                <td>
                                    <?= e($incident["title"]) ?>
                                </td>

                                <td>
                                    <?= e($incident["category"]) ?>
                                </td>

                                <td>

                                    <span
                                        class="incident-status <?= $statusClass ?>"
                                    >
                                        <?= e($status) ?>
                                    </span>

                                </td>

                                <td>
                                    <?= (int) $incident["support_count"] ?>
                                </td>

                                <td>
                                    <?= e($incident["created_at"]) ?>
                                </td>

                                <td>

                                    <a
                                        class="view-incident-btn"
                                        href="admin_incident_details.php?id=<?= (int) $incident["incident_id"] ?>"
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

<!-- ADMIN LOGOUT -->

<script>
"use strict";

document.getElementById("admin-logout-form")
    .addEventListener("submit", async function(event) {

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

    });
</script>

</body>
</html>
