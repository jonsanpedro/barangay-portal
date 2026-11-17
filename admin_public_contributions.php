
<?php
require_once __DIR__ . "/config/auth.php";

$admin = requireRole("admin");

header("Cache-Control: no-store");

function e($value): string {
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

if (empty($_SESSION["public_moderation_csrf"])) {
    $_SESSION["public_moderation_csrf"] =
        bin2hex(random_bytes(32));
}

$csrf = $_SESSION["public_moderation_csrf"];

$statuses = ["Pending", "Approved", "Rejected"];

$filter = $_GET["status"] ?? "Pending";

if (!is_string($filter) ||
    !in_array($filter, $statuses, true)) {
    $filter = "Pending";
}

$message = "";
$error = "";

// Process moderation decisions.
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

    if (!is_string($token) ||
        !hash_equals($csrf, $token)) {
        $error = "Invalid security token.";
    } elseif (!$contributionId ||
        !in_array($decision,
            ["Approved", "Rejected"], true)) {
        $error = "Invalid moderation request.";
    } elseif (mb_strlen($reason) > 500) {
        $error = "Rejection reason is too long.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "UPDATE incident_public_contributions
                 SET moderation_status = ?,
                     reviewed_by = ?,
                     reviewed_at = NOW(),
                     rejection_reason = ?
                 WHERE contribution_id = ?
                   AND moderation_status = 'Pending'
                   AND EXISTS (
                       SELECT 1 FROM incidents i
                       WHERE i.incident_id =
                           incident_public_contributions.incident_id
                         AND i.map_approval_status = 'Approved'
                   )"
            );

            $stmt->execute([
                $decision,
                (int) $admin["user_id"],
                $decision === "Rejected" && $reason !== ""
                    ? $reason : null,
                $contributionId
            ]);

            if ($stmt->rowCount() === 1) {
                header(
                    "Location: admin_public_contributions.php"
                    . "?status=Pending&result=updated",
                    true,
                    303
                );
                exit;
            }

            $error = "Contribution is no longer pending "
                   . "or its incident is not approved.";
        } catch (PDOException $ex) {
            error_log(
                "Moderation error: " . $ex->getMessage()
            );
            $error = "Unable to update contribution.";
        }
    }
}

if (($_GET["result"] ?? "") === "updated") {
    $message = "Moderation decision saved successfully.";
}

// Load contributions with incident details.
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
         ORDER BY c.created_at DESC
         LIMIT 100"
    );

    $stmt->execute([$filter]);
    $contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $pdo->query(
        "SELECT moderation_status, COUNT(*) AS total
         FROM incident_public_contributions
         GROUP BY moderation_status"
    );

    $counts = array_fill_keys($statuses, 0);

    foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[$row["moderation_status"]] =
            (int) $row["total"];
    }

} catch (PDOException $ex) {
    error_log(
        "Contribution list error: " . $ex->getMessage()
    );
    http_response_code(500);
    exit("Unable to load public contributions.");
}

function safeEvidence($path): ?string {
    if (!is_string($path)) {
        return null;
    }

    if (!preg_match(
        '~^uploads/public_contributions/[a-f0-9]{32}\.(jpg|png|webp)$~',
        $path
    )) {
        return null;
    }

    return $path;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">
<title>Community Contributions | BC Portal</title>

<link rel="stylesheet" href="admin_dashboard.css">

<style>
* { box-sizing: border-box; }

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
    max-width: 380px;
    max-height: 350px;
    width: 100%;
    object-fit: contain;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
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

.approve { background: #166534; }
.reject { background: #b91c1c; }

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
    .moderation-card { padding: 16px; }
    .moderation-actions { flex-direction: column; }
    .action-btn { width: 100%; }
}
</style>
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

        <a href="admin_incidents.php">
            Incident Reports
        </a>

        <a href="admin_public_contributions.php"
           class="active">
            Community Contributions
        </a>

        <a href="admin_announcements.php">
            Announcements
        </a>
    </nav>
</aside>

<main class="main-content">
<div class="moderation-wrap">

<header class="dashboard-header">
    <div>
        <h1>Community Contributions</h1>
        <p>
            Review public comments and supporting evidence
            before publishing them.
        </p>
    </div>
    <div class="admin-profile">
        Barangay Administrator
    </div>
</header>

<?php if ($message !== ""): ?>
    <div class="notice notice-success" role="status">
        <?= e($message) ?>
    </div>
<?php endif; ?>

<?php if ($error !== ""): ?>
    <div class="notice notice-error" role="alert">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="moderation-tabs">
    <?php foreach ($statuses as $status): ?>
        <a
            href="?status=<?= urlencode($status) ?>"
            class="<?= $filter === $status
                ? 'selected' : '' ?>"
        >
            <?= e($status) ?>
            (<?= (int) $counts[$status] ?>)
        </a>
    <?php endforeach; ?>
</div>

<?php if (!$contributions): ?>
    <div class="empty-state">
        No <?= e(strtolower($filter)) ?>
        contributions found.
    </div>
<?php endif; ?>

<?php foreach ($contributions as $item): ?>

<article class="moderation-card">

    <h2><?= e($item["incident_title"]) ?></h2>

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

    <?php
        $photo = safeEvidence($item["evidence_path"]);
    ?>

    <?php if ($photo !== null): ?>
        <p><strong>Supporting Evidence</strong></p>

        <img
            src="<?= e($photo) ?>"
            class="evidence-photo"
            alt="Submitted supporting evidence"
            loading="lazy"
        >
    <?php endif; ?>

    <p>
        <a href="admin_incident_details.php?id=<?=
            (int) $item["incident_id"]
        ?>">
            View Incident Details
        </a>
    </p>

    <?php if ($item["moderation_status"] === "Pending"
        && $item["map_approval_status"] === "Approved"): ?>

        <form method="POST"
              action="admin_public_contributions.php">

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

            <label for="reason-<?=
                (int) $item["contribution_id"]
            ?>">
                Rejection reason (optional)
            </label>

            <textarea
                id="reason-<?=
                    (int) $item["contribution_id"]
                ?>"
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

    <?php elseif ($item["moderation_status"] !== "Pending"): ?>

        <div class="meta">
            <strong>Reviewed at:</strong>
            <?= e($item["reviewed_at"] ?: "N/A") ?>

            <?php if ($item["rejection_reason"]): ?>
                <p>
                    <strong>Rejection reason:</strong>
                    <?= e($item["rejection_reason"]) ?>
                </p>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <p class="meta">
            This incident is not currently approved
            for the public Community Map.
        </p>
    <?php endif; ?>

</article>

<?php endforeach; ?>

</div>
</main>
</div>
</body>
</html>
