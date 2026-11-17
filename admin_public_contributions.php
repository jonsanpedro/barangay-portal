
<?php
// Prevent unwanted output from corrupting image responses.
// Save this file as UTF-8 without BOM.
ob_start();

require_once __DIR__ . "/config/auth.php";

$admin = requireRole("admin");

header("Cache-Control: no-store");

function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function validEvidencePath($path): ?string
{
    if (!is_string($path)) {
        return null;
    }

    $path = str_replace("\\", "/", $path);

    if (!preg_match(
        '~^uploads/public_contributions/[a-f0-9]{32}\.(jpg|jpeg|png|webp)$~i',
        $path
    )) {
        return null;
    }

    return $path;
}

/*
|--------------------------------------------------------------------------
| ADMIN EVIDENCE PREVIEW
|--------------------------------------------------------------------------
| Images are served through PHP instead of direct upload URLs.
| Only authenticated administrators can access this preview.
*/
if (isset($_GET["evidence"])) {

    $evidenceId = filter_var(
        $_GET["evidence"],
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if (!$evidenceId) {
        http_response_code(404);
        exit("Evidence not found.");
    }

    try {

        $photoStmt = $pdo->prepare(
            "SELECT evidence_path
             FROM incident_public_contributions
             WHERE contribution_id = ?
             LIMIT 1"
        );

        $photoStmt->execute([$evidenceId]);

        $storedPath = $photoStmt->fetchColumn();

        $relativePath = validEvidencePath($storedPath);

        if ($relativePath === null) {
            http_response_code(404);
            exit("Evidence not found.");
        }

        $uploadDirectory = realpath(
            __DIR__ . "/uploads/public_contributions"
        );

        $absolutePath = realpath(
            __DIR__ . "/" . $relativePath
        );

        if (
            $uploadDirectory === false ||
            $absolutePath === false ||
            dirname($absolutePath) !== $uploadDirectory ||
            !is_file($absolutePath) ||
            !is_readable($absolutePath)
        ) {
            http_response_code(404);
            exit("Evidence file not found.");
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))
            ->file($absolutePath);

        $allowedMimeTypes = [
            "image/jpeg",
            "image/png",
            "image/webp"
        ];

        if (!in_array($mime, $allowedMimeTypes, true)) {
            http_response_code(415);
            exit("Invalid evidence image.");
        }

        // Clear output buffers before sending image data.
        while (ob_get_level() > 0) {
            if (!@ob_end_clean()) {
                break;
            }
        }

        header("Content-Type: " . $mime);
        header("X-Content-Type-Options: nosniff");
        header("Content-Disposition: inline");
        header("Cache-Control: private, no-store, no-transform");

        $handle = fopen($absolutePath, "rb");

        if ($handle === false) {
            http_response_code(500);
            exit;
        }

        fpassthru($handle);
        fclose($handle);
        exit;

    } catch (Throwable $exception) {

        error_log(
            "Admin evidence preview error: " .
            $exception->getMessage()
        );

        http_response_code(500);
        exit("Unable to load evidence.");
    }
}

/*
|--------------------------------------------------------------------------
| MODERATION CSRF TOKEN
|--------------------------------------------------------------------------
*/
if (empty($_SESSION["public_moderation_csrf"])) {
    $_SESSION["public_moderation_csrf"] =
        bin2hex(random_bytes(32));
}

$csrf = $_SESSION["public_moderation_csrf"];

$statuses = [
    "Pending",
    "Approved",
    "Rejected"
];

$filter = $_GET["status"] ?? "Pending";

if (
    !is_string($filter) ||
    !in_array($filter, $statuses, true)
) {
    $filter = "Pending";
}

$message = "";
$error = "";

/*
|--------------------------------------------------------------------------
| APPROVE OR REJECT CONTRIBUTION
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";
    $decision = $_POST["decision"] ?? "";

    $contributionId = filter_var(
        $_POST["contribution_id"] ?? null,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    $reason = trim(
        (string) ($_POST["rejection_reason"] ?? "")
    );

    if (
        !is_string($token) ||
        !hash_equals($csrf, $token)
    ) {

        $error = "Invalid security token.";

    } elseif (
        !$contributionId ||
        !in_array(
            $decision,
            ["Approved", "Rejected"],
            true
        )
    ) {

        $error = "Invalid moderation request.";

    } elseif (mb_strlen($reason) > 500) {

        $error = "Rejection reason is too long.";

    } else {

        try {

            $updateStmt = $pdo->prepare(
                "UPDATE incident_public_contributions
                 SET moderation_status = ?,
                     reviewed_by = ?,
                     reviewed_at = NOW(),
                     rejection_reason = ?
                 WHERE contribution_id = ?
                   AND moderation_status = 'Pending'
                   AND EXISTS (
                       SELECT 1
                       FROM incidents i
                       WHERE i.incident_id =
                           incident_public_contributions.incident_id
                         AND i.map_approval_status = 'Approved'
                   )"
            );

            $updateStmt->execute([
                $decision,
                (int) $admin["user_id"],
                $decision === "Rejected" && $reason !== ""
                    ? $reason
                    : null,
                $contributionId
            ]);

            if ($updateStmt->rowCount() === 1) {

                header(
                    "Location: admin_public_contributions.php"
                    . "?status=Pending&result=updated",
                    true,
                    303
                );

                exit;
            }

            $error =
                "Contribution is no longer pending " .
                "or its incident is not approved.";

        } catch (PDOException $exception) {

            error_log(
                "Contribution moderation error: " .
                $exception->getMessage()
            );

            $error = "Unable to update contribution.";
        }
    }
}

if (($_GET["result"] ?? "") === "updated") {
    $message = "Moderation decision saved successfully.";
}

/*
|--------------------------------------------------------------------------
| LOAD CONTRIBUTIONS
|--------------------------------------------------------------------------
*/
try {

    $stmt = $pdo->prepare(
        "SELECT
            c.contribution_id,
            c.incident_id,
            c.user_id,
            c.comment_text,
            c.evidence_path,
            c.created_at,
            c.moderation_status,
            c.reviewed_at,
            c.rejection_reason,
            i.title AS incident_title,
            i.reference_number,
            i.map_approval_status,
            u.first_name,
            u.last_name
         FROM incident_public_contributions c
         INNER JOIN incidents i
             ON c.incident_id = i.incident_id
         INNER JOIN users u
             ON c.user_id = u.user_id
         WHERE c.moderation_status = ?
         ORDER BY
             c.created_at DESC,
             c.contribution_id DESC
         LIMIT 100"
    );

    $stmt->execute([$filter]);

    $contributions = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

    $countStmt = $pdo->query(
        "SELECT
            moderation_status,
            COUNT(*) AS total
         FROM incident_public_contributions
         GROUP BY moderation_status"
    );

    $counts = array_fill_keys($statuses, 0);

    foreach (
        $countStmt->fetchAll(PDO::FETCH_ASSOC) as $row
    ) {
        if (isset($counts[$row["moderation_status"]])) {
            $counts[$row["moderation_status"]] =
                (int) $row["total"];
        }
    }

} catch (PDOException $exception) {

    error_log(
        "Admin contributions error: " .
        $exception->getMessage()
    );

    http_response_code(500);
    exit("Unable to load community contributions.");
}
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
        Community Contributions | BC Portal
    </title>

    <link rel="stylesheet" href="admin_dashboard.css">

    <style>
        * {
            box-sizing: border-box;
        }

        .moderation-wrap {
            max-width: 1150px;
            margin: 0 auto;
        }

        .moderation-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 22px 0;
        }

        .moderation-tabs a {
            display: inline-block;
            padding: 11px 17px;
            background: #fff;
            border: 1px solid #dce3ee;
            border-radius: 9px;
            text-decoration: none;
            color: #27358b;
            font-weight: 600;
        }

        .moderation-tabs a.selected {
            background: #27358b;
            color: #fff;
        }

        .moderation-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 23px;
            margin-bottom: 20px;
            overflow-wrap: anywhere;
        }

        .moderation-card h2 {
            margin: 0 0 12px;
            color: #27358b;
            font-size: 20px;
        }

        .meta {
            font-size: 14px;
            color: #64748b;
            line-height: 1.8;
        }

        .comment-body {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            line-height: 1.7;
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin: 18px 0;
        }

        .evidence-photo {
            display: block;
            max-width: 380px;
            max-height: 350px;
            width: 100%;
            height: auto;
            object-fit: contain;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background: #fff;
        }

        .evidence-link {
            display: inline-block;
            margin-top: 12px;
            color: #27358b;
            font-weight: 600;
        }

        .moderation-actions {
            margin-top: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .action-btn {
            border: none;
            border-radius: 8px;
            padding: 11px 20px;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
        }

        .approve {
            background: #166534;
        }

        .reject {
            background: #b91c1c;
        }

        .reason-input {
            display: block;
            width: 100%;
            max-width: 600px;
            padding: 11px;
            margin-top: 15px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font: inherit;
        }

        .notice {
            padding: 14px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .notice-success {
            background: #dcfce7;
            color: #166534;
        }

        .notice-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-state {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            color: #64748b;
            text-align: center;
        }

        @media (max-width: 700px) {

            .moderation-card {
                padding: 16px;
            }

            .moderation-actions {
                flex-direction: column;
            }

            .action-btn {
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
    <main class="main-content">

        <div class="moderation-wrap">

            <header class="dashboard-header">

                <div>

                    <h1>Community Contributions</h1>

                    <p>
                        Review public comments and supporting
                        evidence before publishing them.
                    </p>

                </div>

                <div class="admin-profile">
                    Barangay Administrator
                </div>

            </header>

            <!-- FEEDBACK MESSAGES -->

            <?php if ($message !== ""): ?>

                <div
                    class="notice notice-success"
                    role="status"
                >
                    <?= e($message) ?>
                </div>

            <?php endif; ?>

            <?php if ($error !== ""): ?>

                <div
                    class="notice notice-error"
                    role="alert"
                >
                    <?= e($error) ?>
                </div>

            <?php endif; ?>

            <!-- MODERATION TABS -->

            <div class="moderation-tabs">

                <?php foreach ($statuses as $status): ?>

                    <a
                        href="?status=<?= urlencode($status) ?>"
                        class="<?= $filter === $status
                            ? 'selected'
                            : '' ?>"
                    >
                        <?= e($status) ?>
                        (<?= (int) $counts[$status] ?>)
                    </a>

                <?php endforeach; ?>

            </div>

            <!-- EMPTY STATE -->

            <?php if (empty($contributions)): ?>

                <div class="empty-state">
                    No <?= e(strtolower($filter)) ?>
                    contributions found.
                </div>

            <?php endif; ?>

            <!-- CONTRIBUTION CARDS -->

            <?php foreach ($contributions as $item): ?>

                <article class="moderation-card">

                    <h2>
                        <?= e($item["incident_title"]) ?>
                    </h2>

                    <div class="meta">

                        <div>
                            <strong>Reference:</strong>
                            <?= e($item["reference_number"]) ?>
                        </div>

                        <div>
                            <strong>Submitted by:</strong>
                            <?= e(
                                $item["first_name"] . " " .
                                $item["last_name"]
                            ) ?>
                        </div>

                        <div>
                            <strong>Date:</strong>
                            <?= e($item["created_at"]) ?>
                        </div>

                        <div>
                            <strong>Moderation status:</strong>
                            <?= e($item["moderation_status"]) ?>
                        </div>

                        <div>
                            <strong>Incident map status:</strong>
                            <?= e($item["map_approval_status"]) ?>
                        </div>

                    </div>

                    <p class="comment-body"><?= e(
                        $item["comment_text"]
                    ) ?></p>

                    <!-- PROTECTED EVIDENCE PREVIEW -->

                    <?php
                    $photo = validEvidencePath(
                        $item["evidence_path"]
                    );
                    ?>

                    <?php if ($photo !== null): ?>

                        <div class="evidence-section">

                            <p>
                                <strong>
                                    Supporting Evidence
                                </strong>
                            </p>

                            <img
                                src="admin_public_contributions.php?evidence=<?= (int) $item["contribution_id"] ?>"
                                class="evidence-photo"
                                alt="Submitted supporting evidence"
                                loading="lazy"
                            >

                            <a
                                class="evidence-link"
                                href="admin_public_contributions.php?evidence=<?= (int) $item["contribution_id"] ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                View Full Evidence Photo
                            </a>

                        </div>

                    <?php endif; ?>

                    <p>
                        <a
                            href="admin_incident_details.php?id=<?= (int) $item["incident_id"] ?>"
                        >
                            View Incident Details
                        </a>
                    </p>

                    <!-- PENDING ACTIONS -->

                    <?php if (
                        $item["moderation_status"] === "Pending" &&
                        $item["map_approval_status"] === "Approved"
                    ): ?>

                        <form
                            method="POST"
                            action="admin_public_contributions.php"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($csrf) ?>"
                            >

                            <input
                                type="hidden"
                                name="contribution_id"
                                value="<?= (int) $item["contribution_id"] ?>"
                            >

                            <label
                                for="reason-<?= (int) $item["contribution_id"] ?>"
                            >
                                Rejection reason (optional)
                            </label>

                            <textarea
                                id="reason-<?= (int) $item["contribution_id"] ?>"
                                name="rejection_reason"
                                class="reason-input"
                                maxlength="500"
                                rows="2"
                                placeholder="Reason for rejection..."
                            ></textarea>

                            <div class="moderation-actions">

                                <button
                                    type="submit"
                                    name="decision"
                                    value="Approved"
                                    class="action-btn approve"
                                >
                                    Approve
                                </button>

                                <button
                                    type="submit"
                                    name="decision"
                                    value="Rejected"
                                    class="action-btn reject"
                                >
                                    Reject
                                </button>

                            </div>

                        </form>

                    <?php elseif (
                        $item["moderation_status"] !== "Pending"
                    ): ?>

                        <div class="meta">

                            <strong>Reviewed at:</strong>

                            <?= e(
                                $item["reviewed_at"] ?: "N/A"
                            ) ?>

                            <?php if (
                                !empty($item["rejection_reason"])
                            ): ?>

                                <p>
                                    <strong>
                                        Rejection reason:
                                    </strong>

                                    <?= e(
                                        $item["rejection_reason"]
                                    ) ?>
                                </p>

                            <?php endif; ?>

                        </div>

                    <?php else: ?>

                        <p class="meta">
                            This incident is not currently
                            approved for the public Community Map.
                        </p>

                    <?php endif; ?>

                </article>

            <?php endforeach; ?>

        </div>

    </main>

</div>

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
