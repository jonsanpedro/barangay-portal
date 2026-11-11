
<?php

require_once __DIR__ . "/config/auth.php";

// Only administrators can view incident details.
$admin = requireRole("admin");

// Generate a CSRF token for admin actions.
if (empty($_SESSION["admin_csrf_token"])) {
    $_SESSION["admin_csrf_token"] =
        bin2hex(random_bytes(32));
}



// Validate incident ID from the URL.
$incidentId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$incidentId) {
    http_response_code(400);
    exit("Invalid incident ID.");
}

// Retrieve the selected incident.
$stmt = $pdo->prepare(
    "SELECT
        i.*,
        u.first_name,
        u.last_name
    FROM incidents AS i
    INNER JOIN users AS u
        ON i.resident_id = u.user_id
    WHERE i.incident_id = ?
    LIMIT 1"
);

$stmt->execute([$incidentId]);
$incident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$incident) {
    http_response_code(404);
    exit("Incident not found.");
}

// Escape text before displaying it in HTML.
function escapeHtml($value) {
    return htmlspecialchars(
        (string) $value,
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

    <title>Incident Details | Barangay Chrysanthemum</title>

    <link rel="stylesheet" href="admin_dashboard.css">
</head>

<body>

<div class="admin-layout">

    <aside class="sidebar">

        <div class="sidebar-brand">
            <h2>BC Portal</h2>
            <p>Administration</p>
        </div>

        <nav class="sidebar-nav">
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
                <h1>Incident Details</h1>
                <p>
                    Reference:
                    <?= escapeHtml($incident["reference_number"]) ?>
                </p>
            </div>
        </header>

        <section class="recent-reports">

            <h2><?= escapeHtml($incident["title"]) ?></h2>

            <p>
                <strong>Resident:</strong>
                <?= escapeHtml(
                    $incident["first_name"] . " " .
                    $incident["last_name"]
                ) ?>
            </p>

            <p>
                <strong>Category:</strong>
                <?= escapeHtml($incident["category"]) ?>
            </p>

            <p>
                <strong>Description:</strong>
                <?= nl2br(escapeHtml($incident["description"])) ?>
            </p>

            <p>
                <strong>Address / Landmark:</strong>
                <?= escapeHtml($incident["location_address"]) ?>
            </p>

            <p>
                <strong>Latitude:</strong>
                <?= escapeHtml($incident["latitude"]) ?>
            </p>

            <p>
                <strong>Longitude:</strong>
                <?= escapeHtml($incident["longitude"]) ?>
            </p>

            <p>
                <strong>Processing Status:</strong>
                <?= escapeHtml($incident["processing_status"]) ?>
            </p>

            <!-- Admin Processing Status Management -->
            <div class="processing-status-section">

            <h3>Update Processing Status</h3>

            <p>
                Select the current progress of this incident.
            </p>

            <form
                action="api/update_processing_status.php"
                method="POST"
                class="processing-status-form"
            >

            <input
                type="hidden"
                name="incident_id"
                value="<?= (int) $incident["incident_id"] ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION["admin_csrf_token"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <label for="processing_status">
                Processing Status
            </label>

            <select
                name="processing_status"
                id="processing_status"
                required
            >
                <?php
                $statuses = [
                    "Pending",
                    "In Progress",
                    "Resolved"
                ];

                foreach ($statuses as $status):
                ?>
                    <option
                        value="<?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?>"
                        <?= $incident["processing_status"] === $status ? "selected" : "" ?>
                    >
                        <?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="update-status-btn">
                Update Status
            </button>

        </form>

    </div>


            <p>
                <strong>Map Approval:</strong>
                <?= escapeHtml($incident["map_approval_status"]) ?>
            </p>


        <!-- Admin Map Approval Controls -->
        <div class="map-approval-section">

        <h3>Map Visibility Review</h3>

        <p>
            Review this incident before allowing it
            to appear on the public Community Map.
        </p>

        <form
            action="api/update_map_approval.php"
            method="POST"
         class="map-approval-form"
            >

            <input
                type="hidden"
                name="incident_id"
                value="<?= (int) $incident["incident_id"] ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION["admin_csrf_token"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <button
                type="submit"
                name="decision"
                value="Approved"
                class="approve-btn"
            >
                Approve
            </button>

            <button
                type="submit"
                name="decision"
                value="Rejected"
                class="reject-btn"
            >
                Reject
            </button>

        </form>

    </div>

            <p>
                <strong>Date Submitted:</strong>
                <?= escapeHtml($incident["created_at"]) ?>
            </p>

            <?php if (!empty($incident["photo_path"])): ?>

                <h3>Incident Photo</h3>

                <img
                    src="<?= escapeHtml($incident["photo_path"]) ?>"
                    alt="Uploaded incident photo"
                    style="max-width: 400px; width: 100%; border-radius: 10px;"
                >

            <?php endif; ?>

        </section>

        <p>
            <a href="admin_incidents.php">
                Back to Incident Reports
            </a>
        </p>

    </main>

</div>

</body>
</html>
