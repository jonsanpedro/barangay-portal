
<?php
/*
|--------------------------------------------------------------------------
| PUBLIC CONTRIBUTION EVIDENCE
|--------------------------------------------------------------------------
| Displays evidence photos only when:
| 1. The contribution is Approved.
| 2. The incident is approved for the public map.
|
| Active administrators may also access evidence
| regardless of moderation status.
|
| Direct access to the upload directory remains blocked.
|
| IMPORTANT:
| Save this file as UTF-8 without BOM.
| Do not add a closing PHP tag.
|--------------------------------------------------------------------------
*/

// Buffer output to prevent accidental text from
// corrupting the image response.
ob_start();

require_once __DIR__ . "/config/auth.php";

header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store, no-transform");

// Validate contribution ID.
$contributionId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT,
    [
        "options" => [
            "min_range" => 1
        ]
    ]
);

if (!$contributionId) {
    http_response_code(404);
    exit("Image not found.");
}

try {

    /*
    |--------------------------------------------------------------------------
    | FIND CONTRIBUTION
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        "SELECT
            c.contribution_id,
            c.evidence_path,
            c.moderation_status,
            i.map_approval_status
         FROM incident_public_contributions c
         INNER JOIN incidents i
             ON i.incident_id = c.incident_id
         WHERE c.contribution_id = ?
         LIMIT 1"
    );

    $stmt->execute([$contributionId]);

    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$item ||
        empty($item["evidence_path"])
    ) {
        http_response_code(404);
        exit("Image not found.");
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK PUBLIC ACCESS
    |--------------------------------------------------------------------------
    */

    $publicAllowed =
        $item["moderation_status"] === "Approved" &&
        $item["map_approval_status"] === "Approved";

    /*
    |--------------------------------------------------------------------------
    | CHECK ADMIN ACCESS
    |--------------------------------------------------------------------------
    */

    $adminAllowed = false;

    if (!empty($_SESSION["user_id"])) {

        $userStmt = $pdo->prepare(
            "SELECT user_id
             FROM users
             WHERE user_id = ?
               AND role = 'admin'
               AND account_status = 'active'
             LIMIT 1"
        );

        $userStmt->execute([
            (int) $_SESSION["user_id"]
        ]);

        $adminAllowed = (bool) $userStmt->fetchColumn();
    }

    /*
    |--------------------------------------------------------------------------
    | DENY UNAUTHORIZED ACCESS
    |--------------------------------------------------------------------------
    */

    if (!$publicAllowed && !$adminAllowed) {
        http_response_code(403);
        exit("Access denied.");
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE STORED FILE PATH
    |--------------------------------------------------------------------------
    */

    $path = str_replace(
        "\\",
        "/",
        (string) $item["evidence_path"]
    );

    // Only allow generated image filenames.
    if (!preg_match(
        '~^uploads/public_contributions/[a-f0-9]{32}\.(jpg|jpeg|png|webp)$~i',
        $path
    )) {
        http_response_code(404);
        exit("Image not found.");
    }

    /*
    |--------------------------------------------------------------------------
    | LOCATE IMAGE
    |--------------------------------------------------------------------------
    */

    $directory = realpath(
        __DIR__ . "/uploads/public_contributions"
    );

    $file = realpath(
        __DIR__ . "/" . $path
    );

    if (
        $directory === false ||
        $file === false ||
        dirname($file) !== $directory ||
        !is_file($file) ||
        !is_readable($file)
    ) {
        http_response_code(404);
        exit("Image not found.");
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE IMAGE MIME TYPE
    |--------------------------------------------------------------------------
    */

    $mime = (new finfo(FILEINFO_MIME_TYPE))
        ->file($file);

    $allowedMimeTypes = [
        "image/jpeg",
        "image/png",
        "image/webp"
    ];

    if (!in_array(
        $mime,
        $allowedMimeTypes,
        true
    )) {
        http_response_code(415);
        exit("Invalid image format.");
    }

    /*
    |--------------------------------------------------------------------------
    | CLEAR OUTPUT BUFFERS
    |--------------------------------------------------------------------------
    | This prevents buffered PHP output, warnings,
    | or whitespace from being included in the image.
    */

    while (ob_get_level() > 0) {
        if (!@ob_end_clean()) {
            break;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SEND IMAGE RESPONSE
    |--------------------------------------------------------------------------
    */

    header("Content-Type: " . $mime);

    header(
        "X-Content-Type-Options: nosniff"
    );

    header(
        "Content-Disposition: inline"
    );

    header(
        "Cache-Control: private, no-store, no-transform"
    );

    // Do not manually set Content-Length.
    // Output compression can cause a mismatch.

   
    /* STREAM IMAGE FILE*/

    $handle = fopen($file, "rb");

    if ($handle === false) {
        http_response_code(500);
        exit;
    }

    fpassthru($handle);

    fclose($handle);

    exit;

} catch (Throwable $exception) {

    error_log(
        "Public evidence access error: " .
        $exception->getMessage()
    );

    http_response_code(500);

    exit("Unable to load image.");
}
