
<?php

require_once __DIR__ . "/config/auth.php";

// Only authenticated admins can access this page.
$admin = requireRole("admin");

// Retrieve all submitted incident reports.
$stmt = $pdo->prepare(
    "SELECT
        i.incident_id,
        i.reference_number,
        i.category,
        i.title,
        i.location_address,
        i.processing_status,
        i.map_approval_status,
        i.created_at,
        u.first_name,
        u.last_name
    FROM incidents AS i
    INNER JOIN users AS u
        ON i.resident_id = u.user_id
    ORDER BY i.created_at DESC, i.incident_id DESC"
);

$stmt->execute();
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Incident Reports | Barangay Chrysanthemum</title>

    <link rel="stylesheet" href="admin_dashboard.css">
</head>

<body>

    <div class="admin-layout">

        <aside class="sidebar">

            <div class="sidebar-brand">
                <h2>BC Portal</h2>
                <p>Administration</p>
            </div>

            <nav class="sidebar-nav"
                 aria-label="Admin navigation">

                <a href="admin_dashboard.php">
                    Dashboard Overview
                </a>

                <a href="admin_incidents.php" class="active">
                    Incident Reports
                </a>

            </nav>

        </aside>

        <main class="main-content">

            <header class="dashboard-header">
                <div>
                    <h1>Incident Reports</h1>
                    <p>View all submitted resident incidents.</p>
                </div>

                <div class="admin-profile">
                    Barangay Administrator
                </div>
            </header>

            <section class="recent-reports">

                <?php if (empty($incidents)): ?>

                    <p>No incident reports available.</p>

                <?php else: ?>

                    <div style="overflow-x: auto;">

                        <table border="1"
                               cellpadding="12"
                               cellspacing="0"
                               style="width: 100%; border-collapse: collapse;">

                            <thead>
                                <tr>
                                    <th>Reference Number</th>
                                    <th>Resident</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Location</th>
                                    <th>Processing</th>
                                    <th>Map Approval</th>
                                    <th>Date Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($incidents as $incident): ?>

                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($incident["reference_number"], ENT_QUOTES, "UTF-8") ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $incident["first_name"] . " " . $incident["last_name"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($incident["title"], ENT_QUOTES, "UTF-8") ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($incident["category"], ENT_QUOTES, "UTF-8") ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($incident["location_address"], ENT_QUOTES, "UTF-8") ?>
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
                                            <?= htmlspecialchars($incident["created_at"], ENT_QUOTES, "UTF-8") ?>
                                        </td>

                                        <td>
                                            <a
                                                href="admin_incident_details.php?id=<?= (int) $incident["incident_id"] ?>"
                                                class="view-details-btn"
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

            <p>
                <a href="admin_dashboard.php">
                    Back to Admin Dashboard
                </a>
            </p>

        </main>

    </div>

</body>
</html>
