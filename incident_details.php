
<?php

require_once __DIR__ . "/config/auth.php";

// Only authenticated residents can access this page.
$resident = requireRole("resident");

// Escape dynamic HTML output.
function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Validate uploaded incident photo paths.
function validIncidentPhoto($path): ?string
{
    if (!is_string($path) || $path === "") {
        return null;
    }

    $path = str_replace("\\", "/", $path);

    if (
        preg_match(
            '~^uploads/incidents/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$~i',
            $path
        )
    ) {
        return $path;
    }

    return null;
}

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

try {

    // Retrieve only the logged-in resident's incident.
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

    // Generate CSRF token for message submission.
    if (empty($_SESSION["incident_comment_csrf_token"])) {
        $_SESSION["incident_comment_csrf_token"] =
            bin2hex(random_bytes(32));
    }

} catch (PDOException $exception) {

    error_log(
        "Resident incident details error: " .
        $exception->getMessage()
    );

    http_response_code(500);
    exit("Unable to load incident details.");
}

$photoUrl = validIncidentPhoto($incident["photo_path"]);

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
        Incident Details | Barangay Chrysanthemum
    </title>

    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="my_incidents.css">
    <link rel="stylesheet" href="incident_messaging.css">

</head>

<body>

<main class="incidents-container">

    <!-- PAGE HEADER -->

    <div class="incident-page-header">

        <div>

            <h1>Incident Details</h1>

            <p>
                View your submitted incident,
                updates, and messages from barangay personnel.
            </p>

        </div>

        <a
            href="my_incidents.php"
            class="incident-back-link"
        >
            Back to My Reports
        </a>

    </div>

    <!-- INCIDENT INFORMATION -->

    <section class="incident-details-card">

        <h2>
            <?= e($incident["title"]) ?>
        </h2>

        <p>
            <strong>Reference Number:</strong>
            <?= e($incident["reference_number"]) ?>
        </p>

        <p>
            <strong>Category:</strong>
            <?= e($incident["category"]) ?>
        </p>

        <div class="incident-description">

            <h3>Description</h3>

            <p>
                <?= nl2br(e($incident["description"])) ?>
            </p>

        </div>

        <h3>Location Information</h3>

        <p>
            <strong>Address / Landmark:</strong>
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

        <h3>Incident Status</h3>

        <p>
            <strong>Processing Status:</strong>

            <span class="incident-status-label">
                <?= e($incident["processing_status"]) ?>
            </span>
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

            <div class="incident-original-photo">

                <h3>Original Incident Photo</h3>

                <img
                    src="<?= e($photoUrl) ?>"
                    alt="Original uploaded incident photo"
                    loading="lazy"
                    style="
                        max-width: 100%;
                        max-height: 400px;
                        height: auto;
                        border-radius: 10px;
                    "
                >

            </div>

        <?php endif; ?>

    </section>

    <!-- NEAR-REAL-TIME MESSAGING -->

    <section
        id="comments"
        class="incident-comments"
    >

        <h2>Incident Conversation</h2>

        <p>
            Communicate with barangay personnel
            about your incident report.
            Messages automatically update every 5 seconds.
        </p>

        <!-- NEW MESSAGE NOTIFICATION -->

        <button
            type="button"
            id="new-message-indicator"
            class="new-message-indicator"
            hidden
        >
            New messages available
        </button>

        <!-- LIVE MESSAGES CONTAINER -->

        <div
            id="live-messages"
            class="chat-messages"
            data-incident-id="<?= (int) $incidentId ?>"
            aria-live="polite"
            aria-relevant="additions text"
        >

            <p>Loading conversation...</p>

        </div>

        <!-- MESSAGE SUBMISSION FORM -->

        <div class="add-comment-section">

            <h3>Send a Message or Update</h3>

            <p>
                Provide additional information
                or attach supporting evidence.
            </p>

            <form
                action="api/add_incident_comment.php"
                method="POST"
                enctype="multipart/form-data"
                class="comment-form"
            >

                <input
                    type="hidden"
                    name="incident_id"
                    value="<?= (int) $incidentId ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(
                        $_SESSION["incident_comment_csrf_token"]
                    ) ?>"
                >

                <label for="comment_text">
                    Your Message
                </label>

                <textarea
                    id="comment_text"
                    name="comment_text"
                    rows="5"
                    maxlength="2000"
                    required
                    placeholder="Type your message or additional incident information..."
                ></textarea>

                <label for="evidence">
                    Attach Additional Evidence (Optional)
                </label>

                <input
                    type="file"
                    id="evidence"
                    name="evidence"
                    accept=".jpg,.jpeg,.png,.webp"
                >

                <small>
                    Accepted formats: JPG, PNG, WebP.
                    Maximum file size: 3 MB.
                </small>

                <button
                    type="submit"
                    class="submit-comment-btn"
                >
                    Send Message
                </button>

            </form>

        </div>

    </section>

    <!-- BACK NAVIGATION -->

    <div class="incident-bottom-navigation">

        <a
            href="my_incidents.php"
            class="incident-back-link"
        >
            Back to My Incident Reports
        </a>

    </div>

</main>

<script src="incident_messaging.js" defer></script>

<?php require_once __DIR__ . "/faq_chatbot.php"; ?>

</body>
</html>
