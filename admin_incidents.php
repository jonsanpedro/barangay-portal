
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

function statusClass($status): string {
    switch ($status) {
        case "Resolved":
            return "status-resolved";
        case "In Progress":
            return "status-progress";
        default:
            return "status-pending";
    }
}

function priorityLevel(int $count): string {
    if ($count >= 11) return "Critical";
    if ($count >= 6) return "High";
    if ($count >= 3) return "Medium";
    return "Low";
}

function priorityClass(string $priority): string {
    return "priority-" . strtolower($priority);
}

function mapClass($status): string {
    switch ($status) {
        case "Approved":
            return "map-approved";
        case "Rejected":
            return "map-rejected";
        default:
            return "map-pending";
    }
}

// =====================================
// PAGE MODE
// =====================================
$section = (string) ($_GET["section"] ?? "");

$isMapView = ($section === "map");

$pageTitle = $isMapView
    ? "Map Approvals"
    : "Incident Reports";

// =====================================
// SEARCH AND FILTERS
// =====================================
$search = trim((string) ($_GET["search"] ?? ""));
$search = substr($search, 0, 100);

$status = (string) ($_GET["status"] ?? "all");

$allowedStatuses = [
    "all",
    "Pending",
    "In Progress",
    "Resolved"
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = "all";
}

$mapStatus = (string) ($_GET["map_status"] ?? "all");

$allowedMapStatuses = [
    "all",
    "For Review",
    "Approved",
    "Rejected"
];

if (!in_array($mapStatus, $allowedMapStatuses, true)) {
    $mapStatus = "all";
}

// =====================================
// LOAD INCIDENTS
// =====================================
try {

    $sql = "
        SELECT
            i.incident_id,
            i.reference_number,
            i.title,
            i.category,
            i.location_address,
            i.processing_status,
            i.map_approval_status,
            i.created_at,
            u.first_name,
            u.last_name,
            COALESCE(s.support_count, 0) AS support_count
        FROM incidents AS i
        INNER JOIN users AS u
            ON i.resident_id = u.user_id
        LEFT JOIN (
            SELECT
                incident_id,
                COUNT(*) AS support_count
            FROM incident_supports
            GROUP BY incident_id
        ) AS s
            ON i.incident_id = s.incident_id
        WHERE 1 = 1
    ";

    $params = [];

    // Search
    if ($search !== "") {

        $sql .= "
            AND (
                i.reference_number LIKE ?
                OR i.title LIKE ?
                OR i.category LIKE ?
                OR i.location_address LIKE ?
                OR u.first_name LIKE ?
                OR u.last_name LIKE ?
            )
        ";

        $searchValue = "%" . $search . "%";

        for ($i = 0; $i < 6; $i++) {
            $params[] = $searchValue;
        }
    }

    // Incident processing filter
    if (!$isMapView && $status !== "all") {
        $sql .= " AND i.processing_status = ?";
        $params[] = $status;
    }

    // Map approval filter
    if ($isMapView && $mapStatus !== "all") {
        $sql .= " AND i.map_approval_status = ?";
        $params[] = $mapStatus;
    }

    // Sorting
    if ($isMapView) {

        // Pending reviews first
        $sql .= "
            ORDER BY
                CASE i.map_approval_status
                    WHEN 'For Review' THEN 0
                    WHEN 'Approved' THEN 1
                    WHEN 'Rejected' THEN 2
                    ELSE 3
                END,
                i.created_at DESC,
                i.incident_id DESC
        ";

    } else {

        // Most supported incidents first
        $sql .= "
            ORDER BY
                support_count DESC,
                i.created_at DESC,
                i.incident_id DESC
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map approval summary
    $mapSummaryStmt = $pdo->query(
        "SELECT
            COUNT(*) AS total,
            COUNT(CASE
                WHEN map_approval_status = 'For Review'
                THEN 1 END) AS pending_count,
            COUNT(CASE
                WHEN map_approval_status = 'Approved'
                THEN 1 END) AS approved_count,
            COUNT(CASE
                WHEN map_approval_status = 'Rejected'
                THEN 1 END) AS rejected_count
         FROM incidents"
    );

    $mapSummary = $mapSummaryStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {

    error_log(
        "Admin incidents error: " .
        $exception->getMessage()
    );

    http_response_code(500);
    exit("Unable to load incident reports.");
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
        <?= e($pageTitle) ?> | Barangay Chrysanthemum
    </title>

    <link rel="stylesheet" href="admin_dashboard.css">

    <style>
        .incidents-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
            margin: 20px 0;
        }

        .filter-group {
            flex: 1;
            min-width: 180px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #121b2c;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            box-sizing: border-box;
            padding: 11px 12px;
            border: 1px solid #d7dce5;
            border-radius: 8px;
            font-size: 14px;
            background: #ffffff;
        }

        .filter-button {
            padding: 12px 20px;
            background: #27358b;
            border: none;
            border-radius: 8px;
            color: #ffffff;
            font-weight: 600;
            cursor: pointer;
        }

        .filter-button:hover {
            background: #1c286d;
        }

        .reset-link {
            display: inline-block;
            padding: 11px;
            color: #27358b;
            text-decoration: none;
            font-weight: 600;
        }

        .report-count {
            margin-bottom: 18px;
            color: #555555;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .incidents-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }

        .incidents-table th,
        .incidents-table td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        .incidents-table th {
            background: #f4f6fb;
            color: #27358b;
            font-size: 13px;
            white-space: nowrap;
        }

        .incidents-table td {
            font-size: 14px;
        }

        .incident-title {
            font-weight: 600;
            color: #121b2c;
        }

        .incident-category {
            display: block;
            color: #6b7280;
            font-size: 12px;
            margin-top: 4px;
        }

        .status-badge,
        .priority-badge,
        .map-badge {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-pending,
        .map-pending {
            background: #fff3cd;
            color: #805c00;
        }

        .status-progress {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-resolved,
        .map-approved {
            background: #dcfce7;
            color: #166534;
        }

        .map-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .priority-low {
            background: #e2e8f0;
            color: #334155;
        }

        .priority-medium {
            background: #fef3c7;
            color: #92400e;
        }

        .priority-high {
            background: #ffedd5;
            color: #9a3412;
        }

        .priority-critical {
            background: #fee2e2;
            color: #991b1b;
        }

        .support-count {
            font-size: 16px;
            font-weight: 700;
            color: #27358b;
        }

        .view-button {
            display: inline-block;
            padding: 9px 14px;
            background: #27358b;
            color: #ffffff;
            text-decoration: none;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .view-button:hover {
            background: #1c286d;
        }

        .empty-message {
            padding: 35px;
            text-align: center;
            color: #6b7280;
        }

        .priority-notice {
            padding: 13px 16px;
            margin-bottom: 18px;
            background: #f8faff;
            border-left: 4px solid #27358b;
            border-radius: 6px;
            font-size: 13px;
            color: #475569;
            line-height: 1.6;
        }

        .map-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .map-stat {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-top: 4px solid #f4b83f;
            border-radius: 10px;
            padding: 20px;
        }

        .map-stat p {
            margin: 0 0 8px;
            color: #667085;
            font-size: 13px;
        }

        .map-stat strong {
            color: #27358b;
            font-size: 26px;
        }

        @media (max-width: 1000px) {
            .map-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .incidents-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .map-stats {
                grid-template-columns: 1fr;
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

        <header class="dashboard-header">

            <div>
                <h1><?= e($pageTitle) ?></h1>

                <p>
                    <?php if ($isMapView): ?>
                        Review incident visibility on the
                        public Community Issue Map.
                    <?php else: ?>
                        Review, prioritize, and manage
                        incidents submitted by barangay residents.
                    <?php endif; ?>
                </p>
            </div>

            <div class="admin-profile">
                Barangay Administrator
            </div>

        </header>

        <?php if ($isMapView): ?>

            <!-- MAP APPROVAL SUMMARY -->
            <section class="map-stats">

                <article class="map-stat">
                    <p>Total Incidents</p>
                    <strong><?= (int) $mapSummary["total"] ?></strong>
                </article>

                <article class="map-stat">
                    <p>Pending Map Review</p>
                    <strong><?= (int) $mapSummary["pending_count"] ?></strong>
                </article>

                <article class="map-stat">
                    <p>Approved for Map</p>
                    <strong><?= (int) $mapSummary["approved_count"] ?></strong>
                </article>

                <article class="map-stat">
                    <p>Rejected for Map</p>
                    <strong><?= (int) $mapSummary["rejected_count"] ?></strong>
                </article>

            </section>

        <?php endif; ?>

        <section class="recent-reports">

            <h2>
                <?= $isMapView
                    ? "Incident Map Approval Records"
                    : "All Incident Reports" ?>
            </h2>

            <p>
                <?php if ($isMapView): ?>
                    Review map approval status and open
                    individual incidents to manage visibility.
                <?php else: ?>
                    Search incidents, review processing status,
                    and monitor community support.
                <?php endif; ?>
            </p>

            <div class="priority-notice">

                <?php if ($isMapView): ?>

                    <strong>Public Map Visibility:</strong>
                    Only approved incidents should appear
                    on the public Community Issue Map.
                    Open an incident's details to review
                    or update its map approval status.

                <?php else: ?>

                    <strong>Community-Based Prioritization:</strong>
                    Incidents with more resident supports appear
                    first. This ranking is based on support count,
                    not emergency severity. Barangay personnel
                    must still assess urgency and public safety.

                <?php endif; ?>

            </div>

            <!-- SEARCH AND FILTER -->
            <form
                method="GET"
                action="admin_incidents.php"
                class="incidents-toolbar"
            >

                <?php if ($isMapView): ?>
                    <input
                        type="hidden"
                        name="section"
                        value="map"
                    >
                <?php endif; ?>

                <div class="filter-group">

                    <label for="search">
                        Search Incident
                    </label>

                    <input
                        type="search"
                        id="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Search reference, title, resident..."
                    >

                </div>

                <?php if ($isMapView): ?>

                    <div class="filter-group">

                        <label for="map_status">
                            Map Approval Status
                        </label>

                        <select
                            id="map_status"
                            name="map_status"
                        >

                            <?php foreach ($allowedMapStatuses as $option): ?>

                                <option
                                    value="<?= e($option) ?>"
                                    <?= $mapStatus === $option ? "selected" : "" ?>
                                >
                                    <?= $option === "all"
                                        ? "All Map Statuses"
                                        : e($option) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                <?php else: ?>

                    <div class="filter-group">

                        <label for="status">
                            Processing Status
                        </label>

                        <select
                            id="status"
                            name="status"
                        >

                            <?php foreach ($allowedStatuses as $option): ?>

                                <option
                                    value="<?= e($option) ?>"
                                    <?= $status === $option ? "selected" : "" ?>
                                >
                                    <?= $option === "all"
                                        ? "All Statuses"
                                        : e($option) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                <?php endif; ?>

                <button
                    type="submit"
                    class="filter-button"
                >
                    Apply Filters
                </button>

                <a
                    href="<?= $isMapView
                        ? 'admin_incidents.php?section=map'
                        : 'admin_incidents.php' ?>"
                    class="reset-link"
                >
                    Reset
                </a>

            </form>

            <p class="report-count">
                Total reports found:
                <strong><?= count($incidents) ?></strong>
            </p>

            <!-- INCIDENT TABLE -->
            <div class="table-wrapper">

                <table class="incidents-table">

                    <thead>
                        <tr>
                            <th>Reference Number</th>
                            <th>Incident</th>
                            <th>Resident</th>
                            <th>Processing Status</th>
                            <th>Map Approval</th>
                            <th>Supports</th>
                            <th>Priority</th>
                            <th>Date Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (empty($incidents)): ?>

                        <tr>
                            <td colspan="9" class="empty-message">
                                No incident reports found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($incidents as $incident): ?>

                            <?php
                            $supportCount = (int) $incident["support_count"];
                            $priority = priorityLevel($supportCount);
                            ?>

                            <tr>

                                <td>
                                    <?= e($incident["reference_number"]) ?>
                                </td>

                                <td>
                                    <span class="incident-title">
                                        <?= e($incident["title"]) ?>
                                    </span>

                                    <span class="incident-category">
                                        <?= e($incident["category"]) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e(
                                        $incident["first_name"] . " " .
                                        $incident["last_name"]
                                    ) ?>
                                </td>

                                <td>
                                    <span
                                        class="status-badge <?= e(
                                            statusClass(
                                                $incident["processing_status"]
                                            )
                                        ) ?>"
                                    >
                                        <?= e($incident["processing_status"]) ?>
                                    </span>
                                </td>

                                <td>
                                    <span
                                        class="map-badge <?= e(
                                            mapClass(
                                                $incident["map_approval_status"]
                                            )
                                        ) ?>"
                                    >
                                        <?= e($incident["map_approval_status"]) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="support-count">
                                        <?= $supportCount ?>
                                    </span>
                                </td>

                                <td>
                                    <span
                                        class="priority-badge <?= e(
                                            priorityClass($priority)
                                        ) ?>"
                                    >
                                        <?= e($priority) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e($incident["created_at"]) ?>
                                </td>

                                <td>
                                    <a
                                        href="admin_incident_details.php?id=<?= (int) $incident["incident_id"] ?>"
                                        class="view-button"
                                    >
                                        View Details
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

<!-- LOGOUT -->
<script>
"use strict";

const logoutForm = document.getElementById("admin-logout-form");

if (logoutForm) {
    logoutForm.addEventListener("submit", async function(event) {
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
}
</script>

</body>
</html>
