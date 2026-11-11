
<?php

require_once __DIR__ . "/config/auth.php";

// Only authenticated residents can access this dashboard.
$resident = requireRole("resident");

// Safely display the resident's first name.
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

    <title>Resident Dashboard | Barangay Chrysanthemum</title>

    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="resident_dashboard.css">
</head>

<body>

    <main class="resident-dashboard">

        <!-- Dashboard Header -->
        <section class="resident-header">

            <h1>Resident Dashboard</h1>

            <h2>Welcome, <?= $firstName ?>!</h2>

            <p>
                Manage your community reports in one place.
                Submit new incidents and track the progress
                of your existing reports.
            </p>

        </section>

        <!-- Dashboard Action Cards -->
        <section class="resident-actions">

            <a href="report_incident.php"
               class="resident-action-card">

                <h3>Report an Incident</h3>

                <p>
                    Submit a new community concern.
                    Select its location on the map
                    and optionally upload a photo.
                </p>

            </a>

            <a href="my_incidents.php"
               class="resident-action-card">

                <h3>My Incident Reports</h3>

                <p>
                    View your submitted incidents,
                    reference numbers, processing statuses,
                    and map approval statuses.
                </p>

            </a>

        </section>

        <!-- Back to Homepage -->
        <a href="index.html" class="resident-back">
            Back to Homepage
        </a>

    </main>

</body>
</html>
