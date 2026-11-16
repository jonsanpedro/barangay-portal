
<?php
require_once __DIR__ . "/config/auth.php";

// Admin access only.
$admin = requireRole("admin");

function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function statusClass($status): string
{
    switch ($status) {
        case "Resolved":
            return "status-resolved";
        case "In Progress":
            return "status-progress";
        default:
            return "status-pending";
    }
}

function priorityLevel(int $count): string
{
    if ($count >= 11) {
        return "Critical";
    }

    if ($count >= 6) {
        return "High";
    }

    if ($count >= 3) {
        return "Medium";
    }

    return "Low";
}

function priorityClass(string $priority): string
{
    return "priority-" . strtolower($priority);
}

// Search and filtering.
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

try {

    // Count supports without duplicating incident rows.
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

    if ($status !== "all") {
        $sql .= " AND i.processing_status = ?";
        $params[] = $status;
    }

    $sql .= "
        ORDER BY
            s.support_count DESC,
            i.created_at DESC,
            i.incident_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        Incident Reports | Barangay Chrysanthemum
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
        .priority-badge {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-pending {
            background: #fff3cd;
            color: #805c00;
        }

        .status-progress {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-resolved {
            background: #dcfce7;
            color: #166534;
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

        @media (max-width: 768px) {
            .incidents-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }
        }
    </style>
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

            <a href="admin_dashboard.php">
                Dashboard Overview
            </a>

            <a
                href="admin_incidents.php"
                class="active"
            >
                Incident Reports
            </a>

            <a href="admin_announcements.php">
                Announcements
            </a>

        </nav>

    </aside>

    <!-- MAIN CONTENT -->

    <main class="main-content">

        <header class="dashboard-header">

            <div>
                <h1>Incident Reports</h1>

                <p>
                    Review, prioritize, and manage
                    incidents submitted by barangay residents.
                </p>
            </div>

            <div class="admin-profile">
                Barangay Administrator
            </div>

        </header>

        <!-- INCIDENT MANAGEMENT -->

        <section class="recent-reports">

            <h2>All Incident Reports</h2>

            <p>
                Search incidents, review processing status,
                and monitor community support.
            </p>

            <div class="priority-notice">
                <strong>Community-Based Prioritization:</strong>
                Incidents with more resident supports appear
                first. This ranking is based on support count,
                not emergency severity. Barangay personnel
                must still assess urgency and public safety.
            </div>

            <!-- SEARCH AND FILTER -->

            <form
                method="GET"
                action="admin_incidents.php"
                class="incidents-toolbar"
            >

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

                <div class="filter-group">

                    <label for="status">
                        Processing Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <?php foreach (
                            $allowedStatuses as $option
                        ): ?>

                            <option
                                value="<?= e($option) ?>"
                                <?= $status === $option
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= $option === "all"
                                    ? "All Statuses"
                                    : e($option) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <button
                    type="submit"
                    class="filter-button"
                >
                    Apply Filters
                </button>

                <a
                    href="admin_incidents.php"
                    class="reset-link"
                >
                    Reset
                </a>

            </form>

            <!-- REPORT COUNT -->

            <p class="report-count">
                Total reports found:
                <strong>
                    <?= count($incidents) ?>
                </strong>
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
                                <td
                                    colspan="9"
                                    class="empty-message"
                                >
                                    No incident reports found.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach (
                                $incidents as $incident
                            ): ?>

                                <?php
                                $supportCount = (int)
                                    $incident["support_count"];

                                $priority = priorityLevel(
                                    $supportCount
                                );
                                ?>

                                <tr>

                                    <td>
                                        <?= e(
                                            $incident["reference_number"]
                                        ) ?>
                                    </td>

                                    <td>

                                        <span class="incident-title">
                                            <?= e(
                                                $incident["title"]
                                            ) ?>
                                        </span>

                                        <span class="incident-category">
                                            <?= e(
                                                $incident["category"]
                                            ) ?>
                                        </span>

                                    </td>

                                    <td>
                                        <?= e(
                                            $incident["first_name"] .
                                            " " .
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
                                            <?= e(
                                                $incident["processing_status"]
                                            ) ?>
                                        </span>

                                    </td>

                                    <td>
                                        <?= e(
                                            $incident["map_approval_status"]
                                        ) ?>
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
                                        <?= e(
                                            $incident["created_at"]
                                        ) ?>
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

</body>
</html>
