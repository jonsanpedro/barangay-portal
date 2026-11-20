
<?php

require_once __DIR__ . "/config/auth.php";

// Only authenticated residents can access this page.
$resident = requireRole("resident");

// Retrieve only incidents belonging to the logged-in resident.
$stmt = $pdo->prepare(
    "SELECT
        incident_id,
        reference_number,
        category,
        title,
        location_address,
        processing_status,
        map_approval_status,
        created_at
    FROM incidents
    WHERE resident_id = ?
    ORDER BY created_at DESC, incident_id DESC"
);

$stmt->execute([$resident["user_id"]]);

$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$firstName = htmlspecialchars(
    $resident["first_name"],
    ENT_QUOTES,
    "UTF-8"
);

// Calculate summary statistics from the resident's own reports.
$totalReports = count($incidents);
$pendingReports = 0;
$inProgressReports = 0;
$resolvedReports = 0;

foreach ($incidents as $incident) {
    switch ($incident["processing_status"]) {
        case "Pending":
            $pendingReports++;
            break;

        case "In Progress":
            $inProgressReports++;
            break;

        case "Resolved":
            $resolvedReports++;
            break;
    }
}

// Helper for safely displaying database values.
function escapeIncidentValue($value): string {
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

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Incident Reports | Barangay Chrysanthemum</title>

    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="my_incidents.css">
</head>

<body class="my-incidents-page">

<main class="incidents-container">

    <!-- Top Navigation -->
    <header class="incidents-topbar">

    <a href="resident_dashboard.php"
       class="incidents-topbar-identity">

        <img
            src="images/barangay_logo_updated.png"
            alt="Barangay Chrysanthemum Official Logo"
            class="incidents-topbar-logo"
            width="54"
            height="54"
        >

        <span class="incidents-topbar-branding">
            <strong>Barangay Chrysanthemum</strong>
            <small>Resident Portal</small>
        </span>

    </a>

    <a href="resident_dashboard.php"
       class="incidents-back-link">
        <span aria-hidden="true">&larr;</span>
        Back to Dashboard
    </a>

</header>

    <!-- Main Header -->
    <section class="incidents-hero">

        <div class="incidents-hero-content">

            <span class="incidents-eyebrow">
                RESIDENT REPORT TRACKING
            </span>

            <h1>My Incident Reports</h1>

            <p>
                Welcome, <?= $firstName ?>!
                Stay updated on your submitted community
                concerns and monitor their progress.
            </p>

        </div>

        <div class="incidents-hero-icon" aria-hidden="true">
            <svg
                width="54"
                height="54"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <rect x="4" y="3" width="16" height="18" rx="2"/>
                <path d="M8 8h8M8 12h8M8 16h5"/>
                <path d="m15 16 2 2 4-4"/>
            </svg>
        </div>

    </section>

    <!-- Summary Cards -->
    <section class="incident-summary-grid"
             aria-label="Incident report summary">

        <div class="incident-summary-card summary-total">
            <div class="summary-card-top">
                <span>Total Reports</span>
                <span class="summary-card-symbol" aria-hidden="true">
                    &#9776;
                </span>
            </div>

            <strong><?= $totalReports ?></strong>

            <small>All submitted incidents</small>
        </div>

        <div class="incident-summary-card summary-pending">
            <div class="summary-card-top">
                <span>Pending</span>
                <span class="summary-card-symbol" aria-hidden="true">
                    &#9719;
                </span>
            </div>

            <strong><?= $pendingReports ?></strong>

            <small>Waiting for processing</small>
        </div>

        <div class="incident-summary-card summary-progress">
            <div class="summary-card-top">
                <span>In Progress</span>
                <span class="summary-card-symbol" aria-hidden="true">
                    &#8635;
                </span>
            </div>

            <strong><?= $inProgressReports ?></strong>

            <small>Currently being handled</small>
        </div>

        <div class="incident-summary-card summary-resolved">
            <div class="summary-card-top">
                <span>Resolved</span>
                <span class="summary-card-symbol" aria-hidden="true">
                    &#10003;
                </span>
            </div>

            <strong><?= $resolvedReports ?></strong>

            <small>Completed incident reports</small>
        </div>

    </section>

    <!-- Reports Panel -->
    <section class="incidents-panel">

        <div class="incidents-panel-heading">

            <div>
                <span class="incidents-section-eyebrow">
                    YOUR REPORT HISTORY
                </span>

                <h2>Submitted Incidents</h2>

                <p>
                    Browse your reports, check their status,
                    and open individual incident details.
                </p>
            </div>

            <a href="report_incident.php"
               class="new-incident-button">
                + New Report
            </a>

        </div>

        <?php if (empty($incidents)): ?>

            <div class="incidents-empty-state">

                <div class="empty-state-icon" aria-hidden="true">
                    &#128203;
                </div>

                <h3>No incident reports yet</h3>

                <p>
                    You haven't submitted any incidents.
                    Start by reporting a community concern.
                </p>

                <a href="report_incident.php"
                   class="new-incident-button">
                    Report an Incident
                </a>

            </div>

        <?php else: ?>

            <!-- Search and Filters -->
            <div class="incidents-toolbar">

                <div class="incidents-search">

                    <label for="incident-search">
                        Search Reports
                    </label>

                    <input
                        type="search"
                        id="incident-search"
                        placeholder="Search reference, title, category or location..."
                        autocomplete="off"
                    >

                </div>

                <div class="incidents-filter">

                    <label for="incident-status-filter">
                        Processing Status
                    </label>

                    <select id="incident-status-filter">
                        <option value="all">All Statuses</option>
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Resolved">Resolved</option>
                    </select>

                </div>

            </div>

            <div class="incidents-results-bar">

                <span id="incident-results-count" role="status">
                    Showing <?= $totalReports ?> of
                    <?= $totalReports ?> reports
                </span>

                <span class="results-hint">
                    Most recent reports first
                </span>

            </div>

            <!-- Incident Table -->
            <div class="incidents-table-wrapper">

                <table class="incidents-table">

                    <thead>
                        <tr>
                            <th>Incident Report</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Processing Status</th>
                            <th>Map Approval</th>
                            <th>Date Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody id="incidents-table-body">

                    <?php foreach ($incidents as $incident): ?>

                        <?php

                        $processingStatus =
                            $incident["processing_status"];

                        $processingClass = match ($processingStatus) {
                            "Pending" => "status-pending",
                            "In Progress" => "status-progress",
                            "Resolved" => "status-resolved",
                            default => "status-review"
                        };

                        $mapStatus =
                            $incident["map_approval_status"];

                        $mapClass = match ($mapStatus) {
                            "For Review" => "status-review",
                            "Approved" => "status-approved",
                            "Rejected" => "status-rejected",
                            default => "status-review"
                        };

                        $searchText = implode(" ", [
                            $incident["reference_number"],
                            $incident["title"],
                            $incident["category"],
                            $incident["location_address"]
                        ]);

                        ?>

                        <tr
                            class="incident-data-row"
                            data-search="<?= escapeIncidentValue($searchText) ?>"
                            data-status="<?= escapeIncidentValue($processingStatus) ?>"
                        >

                            <td data-label="Incident Report">

                                <div class="incident-title-cell">

                                    <strong>
                                        <?= escapeIncidentValue(
                                            $incident["title"]
                                        ) ?>
                                    </strong>

                                    <span class="incident-reference">
                                        <?= escapeIncidentValue(
                                            $incident["reference_number"]
                                        ) ?>
                                    </span>

                                </div>

                            </td>

                            <td data-label="Category">
                                <?= escapeIncidentValue(
                                    $incident["category"]
                                ) ?>
                            </td>

                            <td data-label="Location">

                                <span class="incident-location-text">
                                    <?= escapeIncidentValue(
                                        $incident["location_address"]
                                    ) ?>
                                </span>

                            </td>

                            <td data-label="Processing Status">

                                <span class="status-badge <?= $processingClass ?>">
                                    <?= escapeIncidentValue(
                                        $processingStatus
                                    ) ?>
                                </span>

                            </td>

                            <td data-label="Map Approval">

                                <span class="status-badge <?= $mapClass ?>">
                                    <?= escapeIncidentValue(
                                        $mapStatus
                                    ) ?>
                                </span>

                            </td>

                            <td data-label="Date Submitted">

                                <span class="incident-date">
                                    <?= escapeIncidentValue(
                                        $incident["created_at"]
                                    ) ?>
                                </span>

                            </td>

                            <td data-label="Action">

                                <a
                                    href="incident_details.php?id=<?= (int) $incident["incident_id"] ?>"
                                    class="view-details-button"
                                >
                                    View Details
                                    <span aria-hidden="true">&rarr;</span>
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <div
                id="incidents-no-results"
                class="incidents-no-results"
                hidden
            >
                <h3>No matching reports</h3>

                <p>
                    Try a different search term or
                    choose another processing status.
                </p>
            </div>

        <?php endif; ?>

    </section>

    

</main>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const searchInput =
        document.getElementById("incident-search");

    const statusFilter =
        document.getElementById("incident-status-filter");

    const rows = Array.from(
        document.querySelectorAll(".incident-data-row")
    );

    const resultsCount =
        document.getElementById("incident-results-count");

    const noResults =
        document.getElementById("incidents-no-results");

    if (!searchInput || !statusFilter) {
        return;
    }

    function filterIncidents() {

        const searchTerm =
            searchInput.value.trim().toLowerCase();

        const selectedStatus =
            statusFilter.value;

        let visibleCount = 0;

        rows.forEach(function (row) {

            const searchableText =
                (row.dataset.search || "").toLowerCase();

            const processingStatus =
                row.dataset.status || "";

            const matchesSearch =
                searchableText.includes(searchTerm);

            const matchesStatus =
                selectedStatus === "all" ||
                processingStatus === selectedStatus;

            const shouldShow =
                matchesSearch && matchesStatus;

            row.hidden = !shouldShow;

            if (shouldShow) {
                visibleCount++;
            }

        });

        resultsCount.textContent =
            "Showing " + visibleCount +
            " of " + rows.length + " reports";

        noResults.hidden = visibleCount !== 0;
    }

    searchInput.addEventListener(
        "input",
        filterIncidents
    );

    statusFilter.addEventListener(
        "change",
        filterIncidents
    );

});
</script>

</body>
</html>
