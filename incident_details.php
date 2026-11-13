
<?php

require_once __DIR__ . "/config/auth.php";

$resident = requireRole("resident");

// Validate incident ID from URL.
$incidentId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$incidentId) {
    http_response_code(404);
    exit("Incident report not found.");
}

// Retrieve only an incident owned by this resident.
$stmt = $pdo->prepare(
    "SELECT
        incident_id,
        reference_number,
        category,
        title,
        description,
        location_address,
        latitude,
        longitude,
        photo_path,
        processing_status,
        map_approval_status,
        created_at,
        updated_at
    FROM incidents
    WHERE incident_id = ?
      AND resident_id = ?
    LIMIT 1"
);

$stmt->execute([
    $incidentId,
    $resident["user_id"]
]);

$incident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$incident) {
    http_response_code(404);
    exit("Incident report not found.");
}

// Escape all dynamic output.
function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Photo paths must be reviewed before rendering.
// Do not use unvalidated database paths directly.
$photoUrl = null;

if (!empty($incident["photo_path"])) {
    $path = str_replace("\\", "/", $incident["photo_path"]);

    if (
        str_starts_with($path, "uploads/incidents/") &&
        preg_match(
            '~^uploads/incidents/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$~i',
            $path
        )
    ) {
        $photoUrl = $path;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Incident Details | Barangay Chrysanthemum</title>

    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="my_incidents.css">
</head>

<body>

<main class="incidents-container">

    <h1>Incident Details</h1>

    <p>
        <strong>Reference Number:</strong>
        <?= e($incident["reference_number"]) ?>
    </p>

    <h2><?= e($incident["title"]) ?></h2>

    <p>
        <strong>Category:</strong>
        <?= e($incident["category"]) ?>
    </p>

    <p>
        <strong>Description:</strong>
    </p>

    <p><?= nl2br(e($incident["description"])) ?></p>

    <p>
        <strong>Location:</strong>
        <?= e($incident["location_address"]) ?>
    </p>

    <p>
        <strong>Latitude:</strong>
        <?= e($incident["latitude"]) ?>
    </p>

    <p>
        <strong>Longitude:</strong>
        <?= e($incident["longitude"]) ?>
    </p>

    <p>
        <strong>Processing Status:</strong>
        <?= e($incident["processing_status"]) ?>
    </p>

    <p>
        <strong>Map Approval Status:</strong>
        <?= e($incident["map_approval_status"]) ?>
    </p>

    <p>
        <strong>Date Submitted:</strong>
        <?= e($incident["created_at"]) ?>
    </p>

    <p>
        <strong>Last Updated:</strong>
        <?= e($incident["updated_at"]) ?>
    </p>

    <?php if ($photoUrl !== null): ?>

        <h3>Incident Photo</h3>

        <img
            src="<?= e($photoUrl) ?>"
            alt="Uploaded incident photo"
            style="max-width: 100%; height: auto;"
        >

    <?php endif; ?>

    <p>
        <a href="my_incidents.php">
            Back to My Incident Reports
        </a>
    </p>

</main>

</body>
</html>
