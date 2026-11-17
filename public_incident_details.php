
<?php
require_once __DIR__ . "/config/auth.php";

header("Cache-Control: no-store");

// Generate CSRF token for public contributions.
if (empty($_SESSION["public_contribution_csrf_token"])) {
    $_SESSION["public_contribution_csrf_token"] =
        bin2hex(random_bytes(32));
}

// Escape output to prevent XSS.
function escapeOutput($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
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
    // Only approved incidents may be viewed publicly.
    $stmt = $pdo->prepare(
        "SELECT
            incident_id,
            reference_number,
            category,
            title,
            location_address,
            processing_status,
            created_at
         FROM incidents
         WHERE incident_id = :incident_id
           AND map_approval_status = 'Approved'
         LIMIT 1"
    );

    $stmt->execute([
        "incident_id" => $incidentId
    ]);

    $incident = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$incident) {
        http_response_code(404);
        exit("Incident not found or not publicly available.");
    }

} catch (PDOException $e) {
    error_log(
        "Public incident details error: " .
        $e->getMessage()
    );

    http_response_code(500);
    exit("Unable to load incident details.");
}

$status = $incident["processing_status"];

$statusClass = match ($status) {
    "Pending" => "pending",
    "In Progress" => "in-progress",
    "Resolved" => "resolved",
    default => "unknown"
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

    <style>
        :root {
            --primary-blue: #27358B;
            --primary-blue-dark: #1C286D;
            --accent-yellow: #F4B83F;
            --background: #FAFAF7;
            --text: #121B2C;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--background);
            color: var(--text);
        }

        .page-header {
            background: var(--primary-blue);
            color: white;
            padding: 24px 6%;
        }

        .page-header h1 {
            margin: 0 0 6px;
            font-size: 24px;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
        }

        .page-container {
            max-width: 900px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: bold;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .incident-card {
            background: white;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.07);
        }

        .incident-title {
            margin: 0 0 15px;
            color: var(--primary-blue);
            overflow-wrap: anywhere;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }

        .pending {
            background: #FEF3C7;
            color: #92400E;
        }

        .in-progress {
            background: #DBEAFE;
            color: #1D4ED8;
        }

        .resolved {
            background: #DCFCE7;
            color: #166534;
        }

        .unknown {
            background: #E5E7EB;
            color: #374151;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 25px;
        }

        .detail-item {
            min-width: 0;
        }

        .detail-item strong {
            display: block;
            color: #64748B;
            font-size: 13px;
            margin-bottom: 7px;
        }

        .detail-item span {
            overflow-wrap: anywhere;
        }

        .section-divider {
            border: none;
            border-top: 1px solid #E5E7EB;
            margin: 30px 0;
        }

        .contribution-intro {
            color: #475569;
            line-height: 1.6;
        }

        .contribution-form {
            margin-top: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .form-group textarea {
            display: block;
            width: 100%;
            min-height: 130px;
            padding: 12px;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            font: inherit;
            resize: vertical;
        }

        .form-group textarea:focus {
            outline: 2px solid var(--primary-blue);
            outline-offset: 1px;
        }

        .form-group input[type="file"] {
            display: block;
            max-width: 100%;
        }

        .form-hint {
            margin-top: 8px;
            font-size: 13px;
            color: #64748B;
        }

        .submit-btn {
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 13px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
        }

        .submit-btn:hover {
            background: var(--primary-blue-dark);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .contribution-message {
            margin-top: 16px;
            font-weight: 500;
        }

        .login-notice {
            background: #EFF6FF;
            border-left: 4px solid var(--primary-blue);
            padding: 16px;
            border-radius: 6px;
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            .incident-card {
                padding: 20px;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 20px;
            }

            .submit-btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<header class="page-header">
    <h1>Barangay Chrysanthemum</h1>
    <p>Public Incident Details</p>
</header>

<main class="page-container">

    <a href="community_map.php" class="back-link">
        &larr; Back to Community Map
    </a>

    <article class="incident-card">

        <h2 class="incident-title">
            <?= escapeOutput($incident["title"]) ?>
        </h2>

        <span class="status-badge <?= $statusClass ?>">
            <?= escapeOutput($status) ?>
        </span>

        <div class="details-grid">

            <div class="detail-item">
                <strong>Reference Number</strong>
                <span>
                    <?= escapeOutput(
                        $incident["reference_number"]
                    ) ?>
                </span>
            </div>

            <div class="detail-item">
                <strong>Category</strong>
                <span>
                    <?= escapeOutput(
                        $incident["category"]
                    ) ?>
                </span>
            </div>

            <div class="detail-item">
                <strong>Location</strong>
                <span>
                    <?= escapeOutput(
                        $incident["location_address"]
                    ) ?>
                </span>
            </div>

            <div class="detail-item">
                <strong>Date Reported</strong>
                <span>
                    <?= escapeOutput(
                        $incident["created_at"]
                    ) ?>
                </span>
            </div>

        </div>

        <hr class="section-divider">

        <section id="community-contributions">

            <h3>Community Contributions</h3>

            <p class="contribution-intro">
                Share additional observations or supporting
                photos about this community incident.
                Contributions should be relevant to the
                reported concern.
            </p>

            <?php
            // The page remains public, but only residents
            // may submit contributions.
            $isResident = false;

            if (!empty($_SESSION["user_id"])) {
                $roleStmt = $pdo->prepare(
                    "SELECT role
                     FROM users
                     WHERE user_id = ?
                       AND account_status = 'active'
                     LIMIT 1"
                );

                $roleStmt->execute([
                    (int) $_SESSION["user_id"]
                ]);

                $isResident =
                    $roleStmt->fetchColumn() === "resident";
            }
            ?>

            <?php if ($isResident): ?>

                <form
                    id="public-contribution-form"
                    class="contribution-form"
                    enctype="multipart/form-data"
                >

                    <input
                        type="hidden"
                        name="incident_id"
                        value="<?= (int) $incident["incident_id"] ?>"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escapeOutput(
                            $_SESSION["public_contribution_csrf_token"]
                        ) ?>"
                    >

                    <div class="form-group">
                        <label for="comment_text">
                            Your Comment
                        </label>

                        <textarea
                            id="comment_text"
                            name="comment_text"
                            maxlength="2000"
                            required
                            placeholder="Share additional information..."
                        ></textarea>
                    </div>

                    <div class="form-group">
                        <label for="evidence">
                            Supporting Photo (Optional)
                        </label>

                        <input
                            type="file"
                            id="evidence"
                            name="evidence"
                            accept="image/jpeg,image/png,image/webp"
                        >

                        <p class="form-hint">
                            JPG, PNG, or WebP. Maximum 3 MB.
                        </p>
                    </div>

                    <button
                        type="submit"
                        id="submit-contribution"
                        class="submit-btn"
                    >
                        Submit Contribution
                    </button>

                    <p
                        id="contribution-message"
                        class="contribution-message"
                        role="status"
                        aria-live="polite"
                    ></p>

                </form>

            <?php else: ?>

                <div class="login-notice">
                    Please log in with an active Resident
                    account to submit comments and
                    supporting evidence.
                </div>

            <?php endif; ?>

        </section>

    </article>

</main>

<?php if ($isResident): ?>

<script>
"use strict";

const contributionForm = document.getElementById(
    "public-contribution-form"
);

contributionForm.addEventListener(
    "submit",
    async function(event) {
        event.preventDefault();

        const button = document.getElementById(
            "submit-contribution"
        );

        const message = document.getElementById(
            "contribution-message"
        );

        const photoInput = document.getElementById(
            "evidence"
        );

        message.textContent = "";
        message.style.color = "";

        // Client-side file size validation.
        if (
            photoInput.files.length > 0 &&
            photoInput.files[0].size > 3 * 1024 * 1024
        ) {
            message.textContent =
                "Maximum photo size is 3 MB.";

            message.style.color = "#B91C1C";
            return;
        }

        button.disabled = true;
        button.textContent = "Submitting...";
        message.textContent = "Submitting contribution...";

        try {
            const response = await fetch(
                "api/add_public_contribution.php",
                {
                    method: "POST",
                    credentials: "same-origin",
                    body: new FormData(contributionForm)
                }
            );

            const contentType =
                response.headers.get("content-type") || "";

            if (!contentType.includes("application/json")) {
                if (response.status === 401) {
                    throw new Error(
                        "Please log in again as a resident."
                    );
                }

                if (response.status === 403) {
                    throw new Error(
                        "Access denied or security token invalid."
                    );
                }

                throw new Error(
                    "Unexpected server response."
                );
            }

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(
                    data.message ||
                    "Unable to submit contribution."
                );
            }

            message.textContent =
                "Contribution submitted successfully!";

            message.style.color = "#166534";

            contributionForm.reset();

        } catch (error) {
            console.error(
                "Contribution submission error:",
                error
            );

            message.textContent =
                error.message ||
                "Unable to submit contribution.";

            message.style.color = "#B91C1C";

        } finally {
            button.disabled = false;
            button.textContent = "Submit Contribution";
        }
    }
);
</script>

<?php endif; ?>

</body>
</html>
