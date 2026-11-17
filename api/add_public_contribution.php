
<?php

require_once __DIR__ . "/../config/auth.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

function sendResponse(int $status, string $message): void
{
    http_response_code($status);

    echo json_encode([
        "success" => $status >= 200 && $status < 300,
        "message" => $message
    ]);

    exit;
}

// Only POST requests are accepted.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    sendResponse(405, "Method not allowed.");
}

// Only active residents may submit contributions.
$user = requireRole("resident");
$userId = (int) $user["user_id"];

// Validate CSRF token.
$token = (string) ($_POST["csrf_token"] ?? "");

if (
    empty($_SESSION["public_contribution_csrf_token"]) ||
    !hash_equals(
        $_SESSION["public_contribution_csrf_token"],
        $token
    )
) {
    sendResponse(403, "Invalid security token.");
}

// Validate incident ID.
$incidentId = filter_var(
    $_POST["incident_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$incidentId) {
    sendResponse(400, "Invalid incident ID.");
}

// Validate comment.
$comment = trim(
    (string) ($_POST["comment_text"] ?? "")
);

if (
    $comment === "" ||
    mb_strlen($comment) > 2000
) {
    sendResponse(
        400,
        "Comment must contain 1 to 2000 characters."
    );
}

// Only approved incidents may receive public contributions.
$checkStmt = $pdo->prepare(
    "SELECT incident_id
     FROM incidents
     WHERE incident_id = ?
       AND map_approval_status = 'Approved'
     LIMIT 1"
);

$checkStmt->execute([$incidentId]);

if (!$checkStmt->fetchColumn()) {
    sendResponse(
        404,
        "Incident not found or not publicly available."
    );
}

$evidencePath = null;
$absolutePath = null;

try {

    // Optional supporting photo.
    if (
        isset($_FILES["evidence"]) &&
        $_FILES["evidence"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        $file = $_FILES["evidence"];

        if (
            !is_array($file) ||
            is_array($file["error"])
        ) {
            throw new RuntimeException(
                "Invalid upload."
            );
        }

        if ($file["error"] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                "Photo upload failed."
            );
        }

        if ($file["size"] > 3 * 1024 * 1024) {
            throw new RuntimeException(
                "Maximum photo size is 3 MB."
            );
        }

        if (!is_uploaded_file($file["tmp_name"])) {
            throw new RuntimeException(
                "Invalid uploaded file."
            );
        }

        $imageInfo = @getimagesize(
            $file["tmp_name"]
        );

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

        // Store public evidence separately from private files.
        $directory =
            __DIR__ . "/../uploads/public_contributions/";

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

        $absolutePath =
            $directory . $filename;

        if (!move_uploaded_file(
            $file["tmp_name"],
            $absolutePath
        )) {
            throw new RuntimeException(
                "Unable to save evidence photo."
            );
        }

        $evidencePath =
            "uploads/public_contributions/" . $filename;
    }

    // Save public contribution.
    $insertStmt = $pdo->prepare(
        "INSERT INTO incident_public_contributions
            (
                incident_id,
                user_id,
                comment_text,
                evidence_path
            )
         VALUES (?, ?, ?, ?)"
    );

    $insertStmt->execute([
        $incidentId,
        $userId,
        $comment,
        $evidencePath
    ]);

    sendResponse(
        201,
        "Community contribution submitted successfully."
    );

} catch (Throwable $e) {

    // Remove uploaded file if database insertion fails.
    if (
        $absolutePath !== null &&
        is_file($absolutePath)
    ) {
        unlink($absolutePath);
    }

    error_log(
        "Public contribution error: " .
        $e->getMessage()
    );

    sendResponse(
        $e instanceof RuntimeException ? 400 : 500,
        $e instanceof RuntimeException
            ? $e->getMessage()
            : "Unable to save contribution."
    );
}
