
<?php

require_once __DIR__ . "/config/auth.php";

// Only administrators can access this page.
$admin = requireRole("admin");

// Escape dynamic HTML content.
function escapeHtml($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Validate incident image paths.
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

// CSRF token for existing admin actions.
if (empty($_SESSION["admin_csrf_token"])) {
    $_SESSION["admin_csrf_token"] =
        bin2hex(random_bytes(32));
}

// CSRF token for incident messaging.
if (empty($_SESSION["incident_comment_csrf_token"])) {
    $_SESSION["incident_comment_csrf_token"] =
        bin2hex(random_bytes(32));
}

// Validate incident ID.
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

try {

    // Retrieve incident and resident details.
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

    // Count residents supporting this incident.
    $supportStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM incident_supports
         WHERE incident_id = ?"
    );

    $supportStmt->execute([$incidentId]);

    $supportCount = (int) $supportStmt->fetchColumn();

} catch (PDOException $exception) {

    error_log(
        "Admin incident details error: " .
        $exception->getMessage()
    );

    http_response_code(500);
    exit("Unable to load incident details.");
}

// Calculate support-based priority.
if ($supportCount >= 11) {
    $priorityLevel = "Critical";
} elseif ($supportCount >= 6) {
    $priorityLevel = "High";
} elseif ($supportCount >= 3) {
    $priorityLevel = "Medium";
} else {
    $priorityLevel = "Low";
}

$originalPhoto = validIncidentPhoto(
    $incident["photo_path"]
);

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

    <link rel="stylesheet" href="admin_dashboard.css">
    <link rel="stylesheet" href="incident_messaging.css">

    <style>
        .incident-priority-section {
            margin: 24px 0;
            padding: 22px;
            background: #f8faff;
            border: 1px solid #dce5f5;
            border-left: 5px solid #27358b;
            border-radius: 12px;
        }

        .incident-priority-section h3 {
            margin: 0 0 18px;
            color: #27358b;
        }

        .priority-info {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 15px;
        }

        .priority-item {
            flex: 1;
            min-width: 160px;
            padding: 16px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }

        .priority-label {
            display: block;
            margin-bottom: 8px;
            color: #64748b;
            font-size: 13px;
        }

        .priority-value {
            font-size: 23px;
            font-weight: 700;
            color: #27358b;
        }

        .priority-badge {
            display: inline-block;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 700;
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

        .priority-note {
            margin: 0;
            font-size: 13px;
            color: #64748b;
            line-height: 1.6;
        }
    </style>

</head>

<body>

<div class="admin-layout">

    <!-- SIDEBAR -->

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
                <h1>Incident Details</h1>

                <p>
                    Reference:
                    <?= escapeHtml(
                        $incident["reference_number"]
                    ) ?>
                </p>
            </div>

            <div class="admin-profile">
                Barangay Administrator
            </div>

        </header>

        <!-- INCIDENT INFORMATION -->

        <section class="recent-reports">

            <h2>
                <?= escapeHtml($incident["title"]) ?>
            </h2>

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
            </p>

            <p>
                <?= nl2br(
                    escapeHtml($incident["description"])
                ) ?>
            </p>

            <p>
                <strong>Address / Landmark:</strong>
                <?= escapeHtml(
                    $incident["location_address"]
                ) ?>
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
                <?= escapeHtml(
                    $incident["processing_status"]
                ) ?>
            </p>

            <!-- COMMUNITY SUPPORT AND PRIORITIZATION -->

            <div class="incident-priority-section">

                <h3>Community Support & Prioritization</h3>

                <div class="priority-info">

                    <div class="priority-item">

                        <span class="priority-label">
                            Residents with Same Concern
                        </span>

                        <span class="priority-value">
                            <?= (int) $supportCount ?>
                        </span>

                    </div>

                    <div class="priority-item">

                        <span class="priority-label">
                            Community Priority Score
                        </span>

                        <span class="priority-value">
                            <?= (int) $supportCount ?>
                        </span>

                    </div>

                    <div class="priority-item">

                        <span class="priority-label">
                            Support-Based Priority Level
                        </span>

                        <span
                            class="priority-badge priority-<?= strtolower(
                                escapeHtml($priorityLevel)
                            ) ?>"
                        >
                            <?= escapeHtml($priorityLevel) ?>
                        </span>

                    </div>

                </div>

                <p class="priority-note">
                    Priority is calculated using the number
                    of residents who support this incident.
                    Barangay personnel must also consider
                    the severity, urgency, and public safety
                    impact of the reported concern.
                </p>

            </div>

            <!-- PROCESSING STATUS MANAGEMENT -->

            <div class="processing-status-section">

                <h3>Update Processing Status</h3>

                <p>
                    Select the current progress
                    of this incident.
                </p>

                <form
                    action="api/update_processing_status.php"
                    method="POST"
                    class="processing-status-form"
                >

                    <input
                        type="hidden"
                        name="incident_id"
                        value="<?= (int) $incidentId ?>"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escapeHtml(
                            $_SESSION["admin_csrf_token"]
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
                                value="<?= escapeHtml($status) ?>"
                                <?= $incident["processing_status"] === $status
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= escapeHtml($status) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <button
                        type="submit"
                        class="update-status-btn"
                    >
                        Update Status
                    </button>

                </form>

            </div>

            <!-- MAP APPROVAL MANAGEMENT -->

            <p>
                <strong>Map Approval:</strong>
                <?= escapeHtml(
                    $incident["map_approval_status"]
                ) ?>
            </p>

            <div class="map-approval-section">

                <h3>Map Visibility Review</h3>

                <p>
                    Review this incident before
                    allowing it to appear on the
                    public Community Map.
                </p>

                <form
                    action="api/update_map_approval.php"
                    method="POST"
                    class="map-approval-form"
                >

                    <input
                        type="hidden"
                        name="incident_id"
                        value="<?= (int) $incidentId ?>"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escapeHtml(
                            $_SESSION["admin_csrf_token"]
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
                <?= escapeHtml(
                    $incident["created_at"]
                ) ?>
            </p>

            <p>
                <strong>Last Updated:</strong>
                <?= escapeHtml(
                    $incident["updated_at"]
                ) ?>
            </p>

            <?php if ($originalPhoto !== null): ?>

                <div class="incident-original-photo">

                    <h3>Original Incident Photo</h3>

                    <img
                        src="<?= escapeHtml($originalPhoto) ?>"
                        alt="Original incident photo"
                        loading="lazy"
                        style="
                            max-width: 400px;
                            width: 100%;
                            height: auto;
                            border-radius: 10px;
                        "
                    >

                </div>

            <?php endif; ?>

        </section>

        <!-- NEAR-REAL-TIME INCIDENT MESSAGING -->

        <section
            id="comments"
            class="recent-reports incident-comments-admin"
        >

            <h2>Incident Conversation</h2>

            <p>
                Communicate with the resident,
                review additional evidence,
                and receive new messages automatically
                every 5 seconds.
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

            <!-- ADMIN MESSAGE FORM -->

            <div class="admin-add-comment-section">

                <h3>Send an Admin Reply</h3>

                <p>
                    Send a response or request
                    additional information
                    from the resident.
                </p>

                <form
                    action="api/add_incident_comment.php"
                    method="POST"
                    enctype="multipart/form-data"
                    class="admin-comment-form"
                >

                    <input
                        type="hidden"
                        name="incident_id"
                        value="<?= (int) $incidentId ?>"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escapeHtml(
                            $_SESSION["incident_comment_csrf_token"]
                        ) ?>"
                    >

                    <label for="admin_comment">
                        Your Message
                    </label>

                    <textarea
                        id="admin_comment"
                        name="comment_text"
                        rows="5"
                        maxlength="2000"
                        required
                        placeholder="Enter your reply to the resident..."
                    ></textarea>

                    <label for="admin_evidence">
                        Attach Additional Evidence (Optional)
                    </label>

                    <input
                        type="file"
                        id="admin_evidence"
                        name="evidence"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <small>
                        Accepted formats: JPG, PNG, WebP.
                        Maximum file size: 3 MB.
                    </small>

                    <button
                        type="submit"
                        class="admin-submit-reply-btn"
                    >
                        Send Reply
                    </button>

                </form>

            </div>

        </section>

        <!-- BACK NAVIGATION -->

        <p>
            <a href="admin_incidents.php">
                Back to Incident Reports
            </a>
        </p>

    </main>

</div>

<script src="incident_messaging.js" defer></script>

</body>
</html>
