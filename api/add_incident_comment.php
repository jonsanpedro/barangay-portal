
<?php

require_once __DIR__ . "/../config/auth.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    http_response_code(405);
    exit("Method not allowed.");
}

$userId = (int) ($_SESSION["user_id"] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    exit("Please log in.");
}

$userStmt = $pdo->prepare(
    "SELECT role FROM users
     WHERE user_id = ?
       AND account_status = 'active'
     LIMIT 1"
);

$userStmt->execute([$userId]);
$userRole = $userStmt->fetchColumn();

if (!in_array($userRole, ["resident", "admin"], true)) {
    http_response_code(403);
    exit("Access denied.");
}

$user = requireRole($userRole);

$token = (string) ($_POST["csrf_token"] ?? "");

if (
    empty($_SESSION["incident_comment_csrf_token"]) ||
    !hash_equals(
        $_SESSION["incident_comment_csrf_token"],
        $token
    )
) {
    http_response_code(403);
    exit("Invalid security token.");
}

$incidentId = filter_var(
    $_POST["incident_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$incidentId) {
    http_response_code(400);
    exit("Invalid incident ID.");
}

$comment = trim((string) ($_POST["comment_text"] ?? ""));

if ($comment === "" || mb_strlen($comment) > 2000) {
    http_response_code(400);
    exit("Comment must contain 1 to 2000 characters.");
}

// Residents may comment only on their own reports.
// Administrators may comment on any incident.
if ($userRole === "resident") {
    $checkStmt = $pdo->prepare(
        "SELECT incident_id
         FROM incidents
         WHERE incident_id = ?
           AND resident_id = ?
         LIMIT 1"
    );

    $checkStmt->execute([$incidentId, $userId]);
} else {
    $checkStmt = $pdo->prepare(
        "SELECT incident_id
         FROM incidents
         WHERE incident_id = ?
         LIMIT 1"
    );

    $checkStmt->execute([$incidentId]);
}

if (!$checkStmt->fetchColumn()) {
    http_response_code(404);
    exit("Incident not found.");
}

$evidencePath = null;
$absolutePath = null;

try {
    if (
        isset($_FILES["evidence"]) &&
        $_FILES["evidence"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        $file = $_FILES["evidence"];

        if ($file["error"] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Photo upload failed.");
        }

        if ($file["size"] > 3 * 1024 * 1024) {
            throw new RuntimeException(
                "Maximum photo size is 3 MB."
            );
        }

        if (!is_uploaded_file($file["tmp_name"])) {
            throw new RuntimeException("Invalid uploaded file.");
        }

        $imageInfo = @getimagesize($file["tmp_name"]);

        if ($imageInfo === false) {
            throw new RuntimeException(
                "Please upload a valid image."
            );
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))
            ->file($file["tmp_name"]);

        $allowed = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/webp" => "webp"
        ];

        if (
            !isset($allowed[$mime]) ||
            $imageInfo["mime"] !== $mime
        ) {
            throw new RuntimeException(
                "Only JPG, PNG, and WebP images are allowed."
            );
        }

        $directory =
            __DIR__ . "/../uploads/incidents/";

        if (
            !is_dir($directory) &&
            !mkdir($directory, 0755, true)
        ) {
            throw new RuntimeException(
                "Unable to prepare upload directory."
            );
        }

        $filename =
            bin2hex(random_bytes(16)) .
            "." . $allowed[$mime];

        $absolutePath = $directory . $filename;

        if (!move_uploaded_file(
            $file["tmp_name"],
            $absolutePath
        )) {
            throw new RuntimeException(
                "Unable to save evidence photo."
            );
        }

        $evidencePath =
            "uploads/incidents/" . $filename;
    }

    $insertStmt = $pdo->prepare(
        "INSERT INTO incident_comments
            (incident_id, user_id, comment_text, evidence_path)
         VALUES (?, ?, ?, ?)"
    );

    $insertStmt->execute([
        $incidentId,
        $userId,
        $comment,
        $evidencePath
    ]);

    $destination = $userRole === "admin"
        ? "../admin_incident_details.php"
        : "../incident_details.php";

    header(
        "Location: " .
        $destination .
        "?id=" . $incidentId . "#comments",
        true,
        303
    );
    exit;

} catch (Throwable $e) {

    if ($absolutePath !== null && is_file($absolutePath)) {
        unlink($absolutePath);
    }

    error_log(
        "Incident comment error: " . $e->getMessage()
    );

    http_response_code(
        $e instanceof RuntimeException ? 400 : 500
    );

    exit(
        $e instanceof RuntimeException
            ? htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                "UTF-8"
            )
            : "Unable to save comment."
    );
}
