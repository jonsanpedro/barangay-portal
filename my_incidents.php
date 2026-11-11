
<?php

require_once __DIR__ . "/config/auth.php";

// Only authenticated residents can access this page.
$resident = requireRole("resident");

// Retrieve incidents belonging to the logged-in resident.
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

<body>

    <main class="incidents-container">

        <h1>My Incident Reports</h1>

        <p>
            Welcome, <?= $firstName ?>!
            Here are the incident reports you submitted.
        </p>

        <?php if (empty($incidents)): ?>

            <p>
                You have not submitted any incident reports yet.
            </p>

        <?php else: ?>

            <div class="incidents-table-wrapper">
                <table class="incidents-table">

                <thead>
                    <tr>
                        <th>Reference Number</th>
                        <th>Incident Title</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Processing Status</th>
                        <th>Map Approval</th>
                        <th>Date Submitted</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($incidents as $incident): ?>

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
                                <?= htmlspecialchars(
                                    $incident["location_address"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            
                            <td>
                                <?php
                                    $processingStatus = $incident["processing_status"];

                                    $processingClass = match ($processingStatus) {
                                    "Pending" => "status-pending",
                                    "In Progress" => "status-progress",
                                    "Resolved" => "status-resolved",
                                    default => "status-review"
                                    };
                                ?>

                            <span class="status-badge <?= $processingClass ?>">
                                <?= htmlspecialchars(
                                    $processingStatus,
                                    ENT_QUOTES,
                                    "UTF-8"
                                    ) ?>
                            </span>
                            </td>


                            
                            <td>
                                <?php
                                    $mapStatus = $incident["map_approval_status"];

                                    $mapClass = match ($mapStatus) {
                                    "For Review" => "status-review",
                                    "Approved" => "status-approved",
                                    "Rejected" => "status-rejected",
                                    default => "status-review"
                                    };
                                ?>

                            <span class="status-badge <?= $mapClass ?>">
                            <?= htmlspecialchars(
                            $mapStatus,
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
                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

        <p>
            <a href="resident_dashboard.php">
                Back to Dashboard
            </a>
        </p>

    </main>

</body>
</html>
