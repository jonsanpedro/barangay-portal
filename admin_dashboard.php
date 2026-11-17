
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

function priorityLevel(int $count): string {
    if ($count >= 6) {
        return "High";
    }

    if ($count >= 3) {
        return "Medium";
    }

    return "Low";
}

function processingStatusClass(string $status): string {
    return match ($status) {
        "Pending" => "status-pending",
        "In Progress" => "status-in-progress",
        "Resolved" => "status-resolved",
        default => "status-unknown"
    };
}

try {

    // Dashboard statistics
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

    // Recent incident reports
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

    // Most supported approved and unresolved incidents
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

    error_log("Dashboard error: " . $exception->getMessage());

    http_response_code(500);

    exit("Unable to load dashboard.");
}

$total = (int) $stats["total_reports"];
$pending = (int) $stats["pending_reports"];
$inProgress = (int) $stats["in_progress_reports"];
$resolved = (int) $stats["resolved_reports"];

$resolvedPercentage = $total > 0
    ? round(($resolved / $total) * 100)
    : 0;

$pendingPercentage = $total > 0
    ? ($pending / $total) * 100
    : 0;

$progressPercentage = $total > 0
    ? ($inProgress / $total) * 100
    : 0;

$resolvedChartPercentage = $total > 0
    ? ($resolved / $total) * 100
    : 0;

// Official transparent barangay logo
$barangayLogo = "images/barangay_logo_updated.png";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Admin Dashboard | Barangay Chrysanthemum</title>

    <link rel="stylesheet" href="admin_dashboard.css">

    <style>

        /* ==========================================
           DASHBOARD ORIGINAL FONT
        ========================================== */

        .dashboard-home,
        .dashboard-home * {
            font-family: Arial, Helvetica, sans-serif;
        }

        .dashboard-home {
            --dash-blue: #27358B;
            --dash-yellow: #F4B83F;
            --dash-muted: #64748b;
        }

        /* ==========================================
           DASHBOARD HEADER
        ========================================== */

        .dashboard-home .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .dashboard-home .dashboard-header h1 {
            font-size: clamp(26px, 3vw, 34px);
            font-weight: 700;
            letter-spacing: -0.7px;
            color: #27358B;
            margin: 0 0 8px;
        }

        .dashboard-home .dashboard-header p {
            font-size: 14px;
            color: #64748b;
            margin: 0;
        }

        .dashboard-home .admin-profile {
            background: #ffffff;
            padding: 12px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #121B2C;
            white-space: nowrap;
        }

        /* ==========================================
           WELCOME BANNER
        ========================================== */

        .dashboard-home .dashboard-welcome {
            background: linear-gradient(
                115deg,
                #27358B 0%,
                #3448A5 65%,
                #5266C1 100%
            );

            border-radius: 18px;
            padding: 30px 34px;
            color: #ffffff;
            margin-bottom: 26px;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;

            min-height: 160px;
            box-sizing: border-box;
        }

        .dashboard-home .dashboard-welcome-content {
            flex: 1;
            min-width: 0;
        }

        .dashboard-home .dashboard-welcome h2 {
            color: #ffffff;
            font-size: clamp(22px, 2.5vw, 30px);
            font-weight: 700;
            margin: 0 0 10px;
        }

        .dashboard-home .dashboard-welcome p {
            color: #e3e9ff;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
            max-width: 540px;
        }

        /* ==========================================
           OFFICIAL BARANGAY LOGO
           Transparent PNG, no extra circle
        ========================================== */

        .dashboard-home .dashboard-welcome-logo {
            display: block;

            width: 150px;
            height: 150px;

            flex: 0 0 150px;

            object-fit: contain;
            object-position: center;

            background: transparent;
            border: none;
            border-radius: 0;
            padding: 0;
            margin: 0;

            box-shadow: none;
            max-width: none;
        }

        /* ==========================================
           STATISTICS CARDS
        ========================================== */

        .dashboard-home .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 26px;
        }

        .dashboard-home .stat-card {
            border-top: none;
            border: 1px solid #e9edf5;
            border-radius: 16px;
            padding: 23px;
            box-shadow: 0 5px 20px rgba(22,34,80,.045);
            position: relative;
            overflow: hidden;
        }

        .dashboard-home .stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: #27358B;
        }

        .dashboard-home .stat-card.card-pending::before {
            background: #f59e0b;
        }

        .dashboard-home .stat-card.card-progress::before {
            background: #3b82f6;
        }

        .dashboard-home .stat-card.card-resolved::before {
            background: #16a34a;
        }

        .stat-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .stat-icon {
            display: grid;
            place-items: center;

            width: 44px;
            height: 44px;

            border-radius: 12px;

            background: #edf0ff;
            color: #27358B;

            font-size: 22px;
            font-weight: 700;
        }

        .card-pending .stat-icon {
            background: #fff5dc;
            color: #a16207;
        }

        .card-progress .stat-icon {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .card-resolved .stat-icon {
            background: #dcfce7;
            color: #15803d;
        }

        .dashboard-home .stat-card p {
            font-size: 13px;
            margin: 0;
        }

        .dashboard-home .stat-card h2 {
            font-size: 34px;
            color: #27358B;
            margin: 16px 0 6px;
        }

        .stat-card small {
            color: #8490a4;
            font-size: 12px;
        }

        /* ==========================================
           INCIDENT OVERVIEW
        ========================================== */

        .dashboard-overview-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(280px, 1fr);
            gap: 20px;
            margin-bottom: 26px;
        }

        .dashboard-panel {
            background: #ffffff;
            border: 1px solid #e9edf5;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(22,34,80,.04);
        }

        .dashboard-panel h2 {
            color: #27358B;
            font-size: 19px;
            margin: 0 0 8px;
        }

        .dashboard-panel-subtitle {
            color: #64748b;
            font-size: 13px;
            margin: 0 0 24px;
        }

        .chart-row {
            margin-bottom: 22px;
        }

        .chart-row:last-child {
            margin-bottom: 0;
        }

        .chart-label {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 9px;
            font-size: 13px;
            color: #475569;
        }

        .chart-label strong {
            color: #27358B;
        }

        .chart-track {
            height: 12px;
            background: #eef1f7;
            border-radius: 20px;
            overflow: hidden;
        }

        .chart-fill {
            height: 100%;
            border-radius: 20px;
        }

        .chart-pending {
            background: #f4b83f;
        }

        .chart-progress {
            background: #3b82f6;
        }

        .chart-resolved {
            background: #22a06b;
        }

        /* ==========================================
           RESOLUTION RATE
        ========================================== */

        .resolution-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .resolution-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;

            display: grid;
            place-items: center;

            margin: 12px auto 18px;

            background: conic-gradient(
                #22a06b 0 var(--resolved-angle),
                #edf0f7 var(--resolved-angle) 100%
            );
        }

        .resolution-circle-inner {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #ffffff;

            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .resolution-circle-inner strong {
            color: #27358B;
            font-size: 32px;
        }

        .resolution-circle-inner span {
            color: #64748b;
            font-size: 12px;
        }

        .resolution-caption {
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
            max-width: 240px;
        }

        /* ==========================================
           INCIDENT REPORT TABLES
        ========================================== */

        .dashboard-home .recent-reports {
            background: #ffffff;
            border: 1px solid #e9edf5;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(22,34,80,.04);
            padding: 26px;
            margin-bottom: 25px;
        }

        .dashboard-home .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .dashboard-home .section-heading h2 {
            color: #27358B;
            font-size: 20px;
            margin: 0 0 7px;
        }

        .dashboard-home .section-heading p {
            color: #64748b;
            margin: 0;
            font-size: 13px;
        }

        .dashboard-home .recent-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .dashboard-home .recent-incidents-table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
            margin-top: 22px;
            font-size: 13px;
        }

        .dashboard-home .recent-incidents-table th,
        .dashboard-home .recent-incidents-table td {
            padding: 14px 16px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #e5e7eb;
        }

        .dashboard-home .recent-incidents-table th {
            background: #f6f8fc;
            color: #27358B;
            font-size: 12px;
            font-weight: 700;
        }

        .dashboard-home .recent-incidents-table td {
            color: #334155;
            font-size: 13px;
        }

        .dashboard-home .recent-incidents-table td:nth-child(2) {
            font-weight: 600;
        }

        /* ==========================================
           RESPONSIVE DESIGN
        ========================================== */

        @media (max-width: 1100px) {

            .dashboard-home .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dashboard-overview-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {

            .dashboard-home .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .dashboard-home .dashboard-welcome {
                padding: 24px;
                gap: 18px;
            }

            .dashboard-home .dashboard-welcome-logo {
                width: 95px;
                height: 95px;
                flex: 0 0 95px;
            }

            .dashboard-panel,
            .dashboard-home .recent-reports {
                padding: 18px;
            }

            .dashboard-home .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {

            .dashboard-home .dashboard-welcome {
                flex-direction: column-reverse;
                align-items: flex-start;
            }

            .dashboard-home .dashboard-welcome-logo {
                display: block;
                width: 85px;
                height: 85px;
                flex: 0 0 85px;
            }
        }

    </style>

</head>

<body>

<div class="admin-layout">

    <!-- Shared Admin Sidebar -->
    <?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

    <main class="main-content dashboard-home">

        <!-- DASHBOARD HEADER -->
        <header class="dashboard-header">

            <div>
                <h1>Dashboard Overview</h1>

                <p>
                    Barangay Chrysanthemum Incident Management
                </p>
            </div>

            <div class="admin-profile">
                Barangay Administrator
            </div>

        </header>

        <!-- WELCOME BANNER -->
        <section class="dashboard-welcome">

            <div class="dashboard-welcome-content">

                <h2>
                    Welcome back,
                    <?= e($admin["first_name"]) ?>!
                </h2>

                <p>
                    Monitor incident reports, review community concerns,
                    and keep track of barangay service requests
                    from one central dashboard.
                </p>

            </div>

            <img
                src="<?= e($barangayLogo) ?>"
                alt="Barangay Chrysanthemum Official Logo"
                class="dashboard-welcome-logo"
                width="150"
                height="150"
            >

        </section>

        <!-- STATISTICS CARDS -->
        <section
            class="stats-grid"
            aria-label="Incident statistics"
        >

            <article class="stat-card">

                <div class="stat-card-top">
                    <p>Total Reports</p>
                    <span class="stat-icon" aria-hidden="true">▤</span>
                </div>

                <h2><?= $total ?></h2>

                <small>All submitted incident reports</small>

            </article>

            <article class="stat-card card-pending">

                <div class="stat-card-top">
                    <p>Pending Reports</p>
                    <span class="stat-icon" aria-hidden="true">◷</span>
                </div>

                <h2><?= $pending ?></h2>

                <small>Awaiting processing</small>

            </article>

            <article class="stat-card card-progress">

                <div class="stat-card-top">
                    <p>In Progress</p>
                    <span class="stat-icon" aria-hidden="true">↻</span>
                </div>

                <h2><?= $inProgress ?></h2>

                <small>Currently being handled</small>

            </article>

            <article class="stat-card card-resolved">

                <div class="stat-card-top">
                    <p>Resolved Reports</p>
                    <span class="stat-icon" aria-hidden="true">✓</span>
                </div>

                <h2><?= $resolved ?></h2>

                <small>Successfully resolved incidents</small>

            </article>

        </section>

        <!-- INCIDENT STATUS OVERVIEW -->
        <section class="dashboard-overview-grid">

            <article class="dashboard-panel">

                <h2>Incident Status Overview</h2>

                <p class="dashboard-panel-subtitle">
                    Current distribution of incident processing statuses
                </p>

                <div class="chart-row">

                    <div class="chart-label">
                        <span>Pending</span>
                        <strong><?= $pending ?> reports</strong>
                    </div>

                    <div class="chart-track">
                        <div
                            class="chart-fill chart-pending"
                            style="width: <?= $pendingPercentage ?>%"
                        ></div>
                    </div>

                </div>

                <div class="chart-row">

                    <div class="chart-label">
                        <span>In Progress</span>
                        <strong><?= $inProgress ?> reports</strong>
                    </div>

                    <div class="chart-track">
                        <div
                            class="chart-fill chart-progress"
                            style="width: <?= $progressPercentage ?>%"
                        ></div>
                    </div>

                </div>

                <div class="chart-row">

                    <div class="chart-label">
                        <span>Resolved</span>
                        <strong><?= $resolved ?> reports</strong>
                    </div>

                    <div class="chart-track">
                        <div
                            class="chart-fill chart-resolved"
                            style="width: <?= $resolvedChartPercentage ?>%"
                        ></div>
                    </div>

                </div>

            </article>

            <!-- RESOLUTION RATE -->
            <article class="dashboard-panel resolution-panel">

                <h2>Resolution Rate</h2>

                <p class="dashboard-panel-subtitle">
                    Percentage of reports resolved
                </p>

                <div
                    class="resolution-circle"
                    style="--resolved-angle: <?= $resolvedPercentage ?>%"
                    role="img"
                    aria-label="<?= $resolvedPercentage ?> percent of reports resolved"
                >

                    <div class="resolution-circle-inner">

                        <strong><?= $resolvedPercentage ?>%</strong>

                        <span>Resolved</span>

                    </div>

                </div>

                <p class="resolution-caption">
                    <?= $resolved ?> out of <?= $total ?>
                    submitted reports have been resolved.
                </p>

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
                            $status = (string) $incident["processing_status"];
                            ?>

                            <tr>

                                <td>
                                    <?= e($incident["reference_number"]) ?>
                                </td>

                                <td>
                                    <?= e($incident["title"]) ?>
                                </td>

                                <td><?= $count ?></td>

                                <td>
                                    <span class="priority-badge priority-<?= strtolower($priority) ?>">
                                        <?= e($priority) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="incident-status <?= processingStatusClass($status) ?>">
                                        <?= e($status) ?>
                                    </span>
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
                    <p>Latest incident submissions</p>
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
                            $status = (string) $incident["processing_status"];
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
                                    <span class="incident-status <?= processingStatusClass($status) ?>">
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
