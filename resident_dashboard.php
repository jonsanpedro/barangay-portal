<?php

require_once __DIR__ . "/config/auth.php";

$resident = requireRole("resident");

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
</head>

<body>

    <main class="dashboard-container">

        <h1>Resident Dashboard</h1>

        <h2>Welcome, <?= $firstName ?>!</h2>

        <p>
            You can submit incident reports,
            track your requests, and communicate
            with barangay personnel.
        </p>

        <div class="dashboard-actions">
            <a href="index.html">Back to Homepage</a>
        </div>

    </main>

</body>
</html>
