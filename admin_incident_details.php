
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

// CSRF token for admin actions.
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

$statuses = [
    "Pending",
    "In Progress",
    "Resolved"
];

$processingStatus = $incident["processing_status"];
$mapStatus = $incident["map_approval_status"];

$processingClass = match ($processingStatus) {
    "Pending" => "status-pending",
    "In Progress" => "status-progress",
    "Resolved" => "status-resolved",
    default => "status-neutral"
};

$mapClass = match ($mapStatus) {
    "For Review" => "status-pending",
    "Approved" => "status-resolved",
    "Rejected" => "status-rejected",
    default => "status-neutral"
};

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

        /* ==========================================
           INCIDENT DETAILS PAGE
        ========================================== */

        .incident-details-page {
            font-family: Arial, Helvetica, sans-serif;
            color: #121B2C;
        }

        .incident-details-page,
        .incident-details-page button,
        .incident-details-page input,
        .incident-details-page select,
        .incident-details-page textarea {
            font-family: Arial, Helvetica, sans-serif;
        }

        .incident-details-page .dashboard-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 26px;
        }

        .incident-details-page .dashboard-header h1 {
            color: #27358B;
            font-size: 30px;
            font-weight: 700;
            margin: 0 0 8px;
        }

        .incident-details-page .dashboard-header p {
            color: #64748B;
            font-size: 13px;
            margin: 0;
            overflow-wrap: anywhere;
        }

        .incident-details-page .admin-profile {
            padding: 12px 18px;
            border-radius: 10px;
            background: #FFFFFF;
            color: #121B2C;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        /* ==========================================
           MAIN CONTENT CARDS
        ========================================== */

        .incident-details-page .details-card {
            background: #FFFFFF;
            border: 1px solid #E8ECF3;
            border-radius: 16px;
            padding: 28px;
            margin-bottom: 24px;
            box-shadow: 0 5px 20px rgba(22,34,80,.04);
        }

        .incident-details-page .details-card h2 {
            color: #27358B;
            font-size: 21px;
            font-weight: 700;
            margin: 0 0 10px;
        }

        .incident-details-page .details-card h3 {
            color: #27358B;
            font-size: 17px;
            margin: 0 0 12px;
        }

        .details-subtitle {
            color: #64748B;
            font-size: 13px;
            line-height: 1.6;
            margin: 0 0 22px;
        }

        /* ==========================================
           INCIDENT INFORMATION
        ========================================== */

        .incident-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 24px;
        }

        .incident-info-item {
            background: #F8FAFD;
            border: 1px solid #EDF0F5;
            border-radius: 11px;
            padding: 17px 18px;
            min-width: 0;
        }

        .incident-info-item.full-width {
            grid-column: 1 / -1;
        }

        .incident-info-label {
            display: block;
            color: #64748B;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 9px;
        }

        .incident-info-value {
            display: block;
            color: #1E293B;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .incident-description {
            font-weight: 400;
            white-space: normal;
        }

        /* ==========================================
           STATUS BADGES
        ========================================== */

        .details-status {
            display: inline-flex;
            align-items: center;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-pending {
            background: #FEF3C7;
            color: #92400E;
        }

        .status-progress {
            background: #DBEAFE;
            color: #1D4ED8;
        }

        .status-resolved {
            background: #DCFCE7;
            color: #166534;
        }

        .status-rejected {
            background: #FEE2E2;
            color: #991B1B;
        }

        .status-neutral {
            background: #E2E8F0;
            color: #334155;
        }

        /* ==========================================
           COMMUNITY PRIORITIZATION
        ========================================== */

        .incident-priority-section {
            margin-top: 26px;
            padding: 23px;
            background: #F8FAFF;
            border: 1px solid #DCE5F5;
            border-left: 5px solid #27358B;
            border-radius: 12px;
        }

        .incident-priority-section h3 {
            margin: 0 0 18px;
            color: #27358B;
        }

        .priority-info {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 18px;
        }

        .priority-item {
            padding: 18px;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            min-width: 0;
        }

        .priority-label {
            display: block;
            margin-bottom: 10px;
            color: #64748B;
            font-size: 12px;
        }

        .priority-value {
            font-size: 27px;
            font-weight: 700;
            color: #27358B;
        }

        .incident-priority-section .priority-badge {
            display: inline-block;
            padding: 8px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .incident-priority-section .priority-low {
            background: #E2E8F0;
            color: #334155;
        }

        .incident-priority-section .priority-medium {
            background: #FEF3C7;
            color: #92400E;
        }

        .incident-priority-section .priority-high {
            background: #FFEDD5;
            color: #9A3412;
        }

        .incident-priority-section .priority-critical {
            background: #FEE2E2;
            color: #991B1B;
        }

        .priority-note {
            margin: 0;
            font-size: 12px;
            color: #64748B;
            line-height: 1.7;
        }

        /* ==========================================
           ADMIN MANAGEMENT SECTIONS
        ========================================== */

        .incident-management-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .incident-management-panel {
            padding: 22px;
            background: #F8FAFD;
            border: 1px solid #E8ECF3;
            border-radius: 12px;
            min-width: 0;
        }

        .incident-management-panel h3 {
            margin: 0 0 10px;
            color: #27358B;
        }

        .incident-management-panel p {
            font-size: 13px;
            color: #64748B;
            line-height: 1.6;
            margin: 0 0 18px;
        }

        .processing-status-form,
        .map-approval-form {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 12px;
        }

        .processing-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1 1 180px;
            min-width: 0;
        }

        .processing-field label {
            color: #334155;
            font-size: 12px;
            font-weight: 700;
        }

        .processing-status-form select {
            width: 100%;
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            background: #FFFFFF;
            color: #1E293B;
            font-size: 13px;
        }

        .update-status-btn,
        .approve-btn,
        .reject-btn {
            min-height: 42px;
            padding: 11px 17px;
            border: none;
            border-radius: 8px;
            color: #FFFFFF;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .2s ease;
        }

        .update-status-btn {
            background: #27358B;
        }

        .approve-btn {
            background: #16A34A;
        }

        .reject-btn {
            background: #DC2626;
        }

        .update-status-btn:hover,
        .approve-btn:hover,
        .reject-btn:hover {
            opacity: .88;
        }

        /* ==========================================
           INCIDENT PHOTO
        ========================================== */

        .incident-original-photo {
            margin-top: 25px;
            padding-top: 22px;
            border-top: 1px solid #E8ECF3;
        }

        .incident-original-photo img {
            display: block;
            max-width: 420px;
            width: 100%;
            height: auto;
            border-radius: 12px;
            border: 1px solid #E8ECF3;
        }

        .incident-dates {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid #E8ECF3;
            color: #64748B;
            font-size: 12px;
        }

        /* ==========================================
           INCIDENT CONVERSATION
        ========================================== */

        .incident-details-page .incident-comments-admin {
            margin-bottom: 20px;
        }

        .incident-details-page .chat-messages {
            min-height: 300px;
            max-height: 460px;
            overflow-y: auto;
            padding: 18px;
            background: #F8FAFD;
            border: 1px solid #E8ECF3;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .incident-details-page .admin-add-comment-section {
            border-top: 1px solid #E8ECF3;
            padding-top: 24px;
        }

        .incident-details-page .admin-comment-form {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }

        .incident-details-page .admin-comment-form label {
            color: #334155;
            font-size: 13px;
            font-weight: 700;
        }

        .incident-details-page .admin-comment-form textarea {
            display: block;
            width: 100%;
            min-height: 130px;
            padding: 14px;
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            background: #FFFFFF;
            color: #121B2C;
            font-size: 14px;
            line-height: 1.6;
            resize: vertical;
        }

        .incident-details-page .admin-comment-form input[type="file"] {
            display: block;
            width: 100%;
            padding: 12px;
            border: 1px dashed #CBD5E1;
            border-radius: 9px;
            background: #F8FAFD;
            font-size: 13px;
        }

        .incident-details-page .admin-comment-form small {
            color: #64748B;
            font-size: 12px;
        }

        .incident-details-page .admin-submit-reply-btn {
            align-self: flex-start;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            background: #27358B;
            color: #FFFFFF;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .incident-details-page .admin-submit-reply-btn:hover {
            background: #1C286D;
        }

        .incident-details-page .new-message-indicator {
            padding: 10px 15px;
            margin-bottom: 15px;
            background: #FEF3C7;
            color: #92400E;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }

        .incident-details-page .new-message-indicator[hidden] {
            display: none !important;
        }

        /* ==========================================
           BACK NAVIGATION
        ========================================== */

        .incident-back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 17px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #FFFFFF;
            border: 1px solid #E8ECF3;
            color: #27358B;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .incident-back-link:hover {
            background: #F1F4FF;
        }

        /* ==========================================
           RESPONSIVE
        ========================================== */

        @media (max-width: 1000px) {
            .incident-management-grid {
                grid-template-columns: 1fr;
            }

            .priority-info {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .incident-details-page .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .incident-details-page .dashboard-header h1 {
                font-size: 25px;
            }

            .incident-info-grid,
            .priority-info {
                grid-template-columns: 1fr;
            }

            .incident-details-page .details-card {
                padding: 18px;
            }

            .incident-management-panel {
                padding: 17px;
            }

            .incident-details-page .admin-submit-reply-btn {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="admin-layout">

    <!-- SHARED ADMIN SIDEBAR -->
    <?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

    <!-- MAIN CONTENT -->
    <main class="main-content incident-details-page">

        <!-- PAGE HEADER -->
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
        <section class="details-card">

            <h2>
                <?= escapeHtml($incident["title"]) ?>
            </h2>

            <p class="details-subtitle">
                Incident information and administrative review
            </p>

            <div class="incident-info-grid">

                <div class="incident-info-item">
                    <span class="incident-info-label">
                        Resident
                    </span>

                    <span class="incident-info-value">
                        <?= escapeHtml(
                            $incident["first_name"] . " " .
                            $incident["last_name"]
                        ) ?>
                    </span>
                </div>

                <div class="incident-info-item">
                    <span class="incident-info-label">
                        Category
                    </span>

                    <span class="incident-info-value">
                        <?= escapeHtml($incident["category"]) ?>
                    </span>
                </div>

                <div class="incident-info-item full-width">
                    <span class="incident-info-label">
                        Description
                    </span>

                    <span class="incident-info-value incident-description">
                        <?= nl2br(
                            escapeHtml($incident["description"])
                        ) ?>
                    </span>
                </div>

                <div class="incident-info-item full-width">
                    <span class="incident-info-label">
                        Address / Landmark
                    </span>

                    <span class="incident-info-value">
                        <?= escapeHtml(
                            $incident["location_address"]
                        ) ?>
                    </span>
                </div>

                <div class="incident-info-item">
                    <span class="incident-info-label">
                        Latitude
                    </span>

                    <span class="incident-info-value">
                        <?= escapeHtml($incident["latitude"]) ?>
                    </span>
                </div>

                <div class="incident-info-item">
                    <span class="incident-info-label">
                        Longitude
                    </span>

                    <span class="incident-info-value">
                        <?= escapeHtml($incident["longitude"]) ?>
                    </span>
                </div>

                <div class="incident-info-item">
                    <span class="incident-info-label">
                        Processing Status
                    </span>

                    <div>
                        <span class="details-status <?= $processingClass ?>">
                            <?= escapeHtml($processingStatus) ?>
                        </span>
                    </div>
                </div>

                <div class="incident-info-item">
                    <span class="incident-info-label">
                        Map Approval
                    </span>

                    <div>
                        <span class="details-status <?= $mapClass ?>">
                            <?= escapeHtml($mapStatus) ?>
                        </span>
                    </div>
                </div>

            </div>

            <!-- COMMUNITY SUPPORT -->
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

                        <span class="priority-badge priority-<?= strtolower(
                            escapeHtml($priorityLevel)
                        ) ?>">
                            <?= escapeHtml($priorityLevel) ?>
                        </span>

                    </div>

                </div>

                <p class="priority-note">
                    Priority is calculated using the number of
                    residents who support this incident.
                    Barangay personnel must also consider the
                    severity, urgency, and public safety impact
                    of the reported concern.
                </p>

            </div>

            <!-- ADMIN MANAGEMENT -->
            <div class="incident-management-grid">

                <!-- PROCESSING STATUS -->
                <div class="incident-management-panel">

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
                            value="<?= (int) $incidentId ?>"
                        >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= escapeHtml(
                                $_SESSION["admin_csrf_token"]
                            ) ?>"
                        >

                        <div class="processing-field">

                            <label for="processing_status">
                                Processing Status
                            </label>

                            <select
                                name="processing_status"
                                id="processing_status"
                                required
                            >

                                <?php foreach ($statuses as $status): ?>

                                    <option
                                        value="<?= escapeHtml($status) ?>"
                                        <?= $processingStatus === $status
                                            ? "selected"
                                            : "" ?>
                                    >
                                        <?= escapeHtml($status) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <button
                            type="submit"
                            class="update-status-btn"
                        >
                            Update Status
                        </button>

                    </form>

                </div>

                <!-- MAP APPROVAL -->
                <div class="incident-management-panel">

                    <h3>Map Visibility Review</h3>

                    <p>
                        Review this incident before allowing
                        it to appear on the public Community Map.
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

            </div>

            <!-- INCIDENT DATES -->
            <div class="incident-dates">

                <span>
                    <strong>Date Submitted:</strong>
                    <?= escapeHtml($incident["created_at"]) ?>
                </span>

                <span>
                    <strong>Last Updated:</strong>
                    <?= escapeHtml($incident["updated_at"]) ?>
                </span>

            </div>

            <!-- ORIGINAL INCIDENT PHOTO -->
            <?php if ($originalPhoto !== null): ?>

                <div class="incident-original-photo">

                    <h3>Original Incident Photo</h3>

                    <img
                        src="<?= escapeHtml($originalPhoto) ?>"
                        alt="Original incident photo"
                        loading="lazy"
                    >

                </div>

            <?php endif; ?>

        </section>

        <!-- INCIDENT CONVERSATION -->
        <section
            id="comments"
            class="details-card incident-comments-admin"
        >

            <h2>Incident Conversation</h2>

            <p class="details-subtitle">
                Communicate with the resident, review
                additional evidence, and receive new
                messages automatically every 5 seconds.
            </p>

            <button
                type="button"
                id="new-message-indicator"
                class="new-message-indicator"
                hidden
            >
                New messages available
            </button>

            <!-- LIVE MESSAGES -->
            <div
                id="live-messages"
                class="chat-messages"
                data-incident-id="<?= (int) $incidentId ?>"
                aria-live="polite"
                aria-relevant="additions text"
            >
                <p>Loading conversation...</p>
            </div>

            <!-- ADMIN REPLY -->
            <div class="admin-add-comment-section">

                <h3>Send an Admin Reply</h3>

                <p class="details-subtitle">
                    Send a response or request additional
                    information from the resident.
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
        <a
            href="admin_incidents.php"
            class="incident-back-link"
        >
            ← Back to Incident Reports
        </a>

    </main>

</div>

<!-- INCIDENT MESSAGING -->
<script src="incident_messaging.js" defer></script>

<!-- ADMIN LOGOUT -->
<script>
"use strict";

const logoutForm = document.getElementById(
    "admin-logout-form"
);

if (logoutForm) {

    logoutForm.addEventListener(
        "submit",
        async function(event) {

            event.preventDefault();

            try {

                const response = await fetch(
                    "api/logout.php",
                    {
                        method: "POST",
                        credentials: "same-origin"
                    }
                );

                if (!response.ok) {
                    throw new Error("Logout failed.");
                }

                window.location.href = "index.html";

            } catch (error) {

                alert(
                    "Unable to log out. Please try again."
                );

            }

        }
    );

}
</script>

</body>
</html>
