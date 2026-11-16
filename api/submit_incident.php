
<?php
// REPLACE EXISTING INCIDENT SUBMISSION API.
// Keep the original filename if it differs.

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/email_notifications.php';

// Only authenticated residents can submit incidents.
$resident = requireRole('resident');

header('Content-Type: application/json; charset=utf-8');

function incidentResponse(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Accept POST requests only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    incidentResponse(405, [
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
}

// Validate incident submission CSRF token.
$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['incident_csrf_token'] ?? '';

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    incidentResponse(403, [
        'success' => false,
        'message' =>
            'Invalid incident security token. Please refresh the page.'
    ]);
}

// Read submitted incident details.
$category = trim((string) ($_POST['category'] ?? ''));
$title = trim((string) ($_POST['title'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$locationAddress = trim(
    (string) ($_POST['location_address'] ?? '')
);

$latitude = filter_var(
    $_POST['latitude'] ?? null,
    FILTER_VALIDATE_FLOAT
);

$longitude = filter_var(
    $_POST['longitude'] ?? null,
    FILTER_VALIDATE_FLOAT
);

// Categories allowed by the reporting form.
$allowedCategories = [
    'Road and Infrastructure',
    'Street Lighting',
    'Drainage and Flooding',
    'Waste Management',
    'Public Safety',
    'Other'
];

// Validate required text fields.
if (
    !in_array($category, $allowedCategories, true) ||
    $title === '' ||
    $description === '' ||
    $locationAddress === '' ||
    mb_strlen($title) > 200 ||
    mb_strlen($locationAddress) > 255
) {
    incidentResponse(422, [
        'success' => false,
        'message' => 'Please provide valid incident details.'
    ]);
}

// Validate map coordinates.
if (
    $latitude === false ||
    $longitude === false ||
    $latitude < -90 ||
    $latitude > 90 ||
    $longitude < -180 ||
    $longitude > 180
) {
    incidentResponse(422, [
        'success' => false,
        'message' => 'Please select a valid location on the map.'
    ]);
}

// Validate optional incident photo.
$validatedPhoto = null;

if (isset($_FILES['photo'])) {

    $photo = $_FILES['photo'];

    if ($photo['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($photo['error'] !== UPLOAD_ERR_OK) {
            incidentResponse(422, [
                'success' => false,
                'message' => 'Photo upload failed.'
            ]);
        }

        // Maximum photo size: 5 MB.
        if (
            $photo['size'] <= 0 ||
            $photo['size'] > 5 * 1024 * 1024
        ) {
            incidentResponse(422, [
                'success' => false,
                'message' => 'Photo must not exceed 5 MB.'
            ]);
        }

        if (!is_uploaded_file($photo['tmp_name'])) {
            incidentResponse(422, [
                'success' => false,
                'message' => 'Invalid uploaded file.'
            ]);
        }

        // Detect actual MIME type.
        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $mimeType = $finfo->file($photo['tmp_name']);

        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowedMimeTypes[$mimeType])) {
            incidentResponse(422, [
                'success' => false,
                'message' =>
                    'Only JPEG, PNG, and WebP images are allowed.'
            ]);
        }

        // Verify image content.
        if (@getimagesize($photo['tmp_name']) === false) {
            incidentResponse(422, [
                'success' => false,
                'message' => 'Invalid image content.'
            ]);
        }

        $validatedPhoto = [
            'tmp_name' => $photo['tmp_name'],
            'extension' => $allowedMimeTypes[$mimeType]
        ];
    }
}

// Prepare incident saving.
$inserted = false;
$referenceNumber = '';
$photoPath = null;
$savedPhotoFile = null;

try {

    // Start database transaction.
    $pdo->beginTransaction();

    // Lock resident record during submission.
    $lockStmt = $pdo->prepare(
        'SELECT user_id
         FROM users
         WHERE user_id = ?
         FOR UPDATE'
    );

    $lockStmt->execute([
        $resident['user_id']
    ]);

    if (!$lockStmt->fetchColumn()) {
        throw new RuntimeException(
            'Resident account not found.'
        );
    }

    // Preserve the existing 5 reports/hour limit.
    $limitStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM incidents
         WHERE resident_id = ?
           AND created_at >
               DATE_SUB(NOW(), INTERVAL 60 MINUTE)'
    );

    $limitStmt->execute([
        $resident['user_id']
    ]);

    $recentReports = (int) $limitStmt->fetchColumn();

    if ($recentReports >= 5) {

        $pdo->rollBack();

        header('Retry-After: 3600');

        incidentResponse(429, [
            'success' => false,
            'message' =>
                'You have reached the limit of 5 incident reports per hour. Please try again later.'
        ]);
    }

    // Save validated photo if provided.
    if ($validatedPhoto !== null) {

        $uploadDirectory =
            __DIR__ . '/../uploads/incidents/';

        if (!is_dir($uploadDirectory)) {
            throw new RuntimeException(
                'Incident upload directory does not exist.'
            );
        }

        // Generate a random filename.
        $fileName =
            bin2hex(random_bytes(16)) .
            '.' .
            $validatedPhoto['extension'];

        $savedPhotoFile =
            $uploadDirectory . $fileName;

        if (!move_uploaded_file(
            $validatedPhoto['tmp_name'],
            $savedPhotoFile
        )) {
            throw new RuntimeException(
                'Unable to save uploaded photo.'
            );
        }

        $photoPath =
            'uploads/incidents/' . $fileName;
    }

    // Generate a unique incident reference number.
    for ($attempt = 0; $attempt < 3; $attempt++) {

        $referenceNumber =
            'INC-' .
            date('Ymd') .
            '-' .
            strtoupper(bin2hex(random_bytes(8)));

        try {

            $stmt = $pdo->prepare(
                'INSERT INTO incidents (
                    reference_number,
                    resident_id,
                    category,
                    title,
                    description,
                    location_address,
                    latitude,
                    longitude,
                    photo_path
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $referenceNumber,
                $resident['user_id'],
                $category,
                $title,
                $description,
                $locationAddress,
                $latitude,
                $longitude,
                $photoPath
            ]);

            $inserted = true;
            break;

        } catch (PDOException $e) {

            // Retry only for duplicate reference numbers.
            $isDuplicateReference =
                $e->getCode() === '23000' &&
                isset($e->errorInfo[1]) &&
                (int) $e->errorInfo[1] === 1062 &&
                str_contains(
                    $e->errorInfo[2] ?? '',
                    'reference_number'
                );

            if (!$isDuplicateReference) {
                throw $e;
            }
        }
    }

    if (!$inserted) {
        throw new RuntimeException(
            'Unable to generate a unique reference number.'
        );
    }

    // Commit incident before sending notification.
    $pdo->commit();

} catch (Throwable $e) {

    // Roll back database changes if needed.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Remove uploaded photo if saving failed.
    if (
        $savedPhotoFile !== null &&
        is_file($savedPhotoFile)
    ) {
        unlink($savedPhotoFile);
    }

    error_log(
        'Incident submission failed: ' .
        $e->getMessage()
    );

    incidentResponse(500, [
        'success' => false,
        'message' =>
            'Unable to save the incident report.'
    ]);
}

// The incident is now saved successfully.
// Send confirmation email after commit.

// Assumes users.email stores registered email.
try {

    $emailStmt = $pdo->prepare(
        'SELECT email
         FROM users
         WHERE user_id = ?
         LIMIT 1'
    );

    $emailStmt->execute([
        $resident['user_id']
    ]);

    $recipient = (string) (
        $emailStmt->fetchColumn() ?: ''
    );

    $subject =
        'Incident Report Received - ' .
        $referenceNumber;

    $body =
        "Barangay Chrysanthemum Portal\n\n" .
        "Your incident report has been received.\n\n" .
        "Reference: {$referenceNumber}\n" .
        "Incident: {$title}\n" .
        "Processing status: Pending\n\n" .
        "Please log in to the portal to track your report.\n\n" .
        "This is an automated notification. Do not reply.";

    sendPortalEmail(
        $recipient,
        $subject,
        $body
    );

} catch (Throwable $e) {

    error_log(
        'Incident saved, but confirmation email could not be prepared: ' .
        $e->getMessage()
    );
}

// Default database statuses:
// processing_status = Pending
// map_approval_status = For Review

incidentResponse(200, [
    'success' => true,
    'message' =>
        'Incident report submitted successfully.',
    'reference_number' => $referenceNumber
]);
