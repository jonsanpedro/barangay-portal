
<?php
require_once __DIR__ . "/../config/auth.php";

// Only authenticated residents can submit incidents.
$resident = requireRole("resident");

// Accept POST requests only.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Allow: POST");
    http_response_code(405);
    header("Content-Type: application/json");
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

header("Content-Type: application/json; charset=utf-8");


/* VERIFY INCIDENT SUBMISSION CSRF TOKEN */

$submittedToken = $_POST["csrf_token"] ?? "";
$sessionToken = $_SESSION["incident_csrf_token"] ?? "";

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === "" ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Invalid incident security token. Please refresh the page."
    ]);

    exit;
}


// Read submitted incident details.
$category = trim($_POST["category"] ?? "");
$title = trim($_POST["title"] ?? "");
$description = trim($_POST["description"] ?? "");
$locationAddress = trim($_POST["location_address"] ?? "");

$latitude = filter_var(
    $_POST["latitude"] ?? null,
    FILTER_VALIDATE_FLOAT
);

$longitude = filter_var(
    $_POST["longitude"] ?? null,
    FILTER_VALIDATE_FLOAT
);

// Categories allowed by the reporting form.
$allowedCategories = [
    "Road and Infrastructure",
    "Street Lighting",
    "Drainage and Flooding",
    "Waste Management",
    "Public Safety",
    "Other"
];

// Validate required text fields.
if (
    !in_array($category, $allowedCategories, true) ||
    $title === "" ||
    $description === "" ||
    $locationAddress === "" ||
    mb_strlen($title) > 200 ||
    mb_strlen($locationAddress) > 255
) {
    http_response_code(422);

    echo json_encode([
        "success" => false,
        "message" => "Please provide valid incident details."
    ]);
    exit;
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
    http_response_code(422);

    echo json_encode([
        "success" => false,
        "message" => "Please select a valid location on the map."
    ]);
    exit;
}


// Validate the optional incident photo.
// Do not move the uploaded file yet.
$validatedPhoto = null;

if (isset($_FILES["photo"])) {

    $photo = $_FILES["photo"];

    if ($photo["error"] !== UPLOAD_ERR_NO_FILE) {

        if ($photo["error"] !== UPLOAD_ERR_OK) {
            http_response_code(422);
            echo json_encode([
                "success" => false,
                "message" => "Photo upload failed."
            ]);
            exit;
        }

        // Maximum file size: 5 MB.
        $maxPhotoSize = 5 * 1024 * 1024;

        if (
            $photo["size"] <= 0 ||
            $photo["size"] > $maxPhotoSize
        ) {
            http_response_code(422);
            echo json_encode([
                "success" => false,
                "message" => "Photo must not exceed 5 MB."
            ]);
            exit;
        }

        // Verify that PHP received a real uploaded file.
        if (!is_uploaded_file($photo["tmp_name"])) {
            http_response_code(422);
            echo json_encode([
                "success" => false,
                "message" => "Invalid uploaded file."
            ]);
            exit;
        }

        // Detect actual MIME type from file content.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($photo["tmp_name"]);

        $allowedMimeTypes = [
            "image/jpeg" => "jpg",
            "image/png"  => "png",
            "image/webp" => "webp"
        ];

        if (!isset($allowedMimeTypes[$mimeType])) {
            http_response_code(422);
            echo json_encode([
                "success" => false,
                "message" => "Only JPEG, PNG, and WebP images are allowed."
            ]);
            exit;
        }

        // Verify that the file can be recognized as an image.
        if (@getimagesize($photo["tmp_name"]) === false) {
            http_response_code(422);
            echo json_encode([
                "success" => false,
                "message" => "Invalid image content."
            ]);
            exit;
        }

        // Store validated information for the next step.
        $validatedPhoto = [
            "tmp_name" => $photo["tmp_name"],
            "extension" => $allowedMimeTypes[$mimeType]
        ];
    }
}



/*
 * Save the incident and optional photo.
 * Use a database transaction to prevent incomplete records.
 */

$inserted = false;
$referenceNumber = "";
$photoPath = null;
$savedPhotoFile = null;


try {

    // Start database transaction.
    $pdo->beginTransaction();

    


/* INCIDENT SUBMISSION RATE LIMIT */

// Lock this resident's user record until the transaction ends.
// This serializes incident submissions for the same resident.
    $lockStmt = $pdo->prepare(
        "SELECT user_id
        FROM users
        WHERE user_id = ?
        FOR UPDATE"
    );

$lockStmt->execute([$resident["user_id"]]);

if (!$lockStmt->fetchColumn()) {
    throw new RuntimeException("Resident account not found.");
}

// Count successful submissions in the last 60 minutes.
$limitStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM incidents
     WHERE resident_id = ?
       AND created_at > DATE_SUB(NOW(), INTERVAL 60 MINUTE)"
);

$limitStmt->execute([$resident["user_id"]]);

$recentReports = (int) $limitStmt->fetchColumn();

if ($recentReports >= 5) {
    $pdo->rollBack();

    header("Retry-After: 3600");
    http_response_code(429);

    echo json_encode([
        "success" => false,
        "message" =>
            "You have reached the limit of 5 incident reports per hour. Please try again later."
    ]);

    exit;
}


// Save the uploaded photo if provided.
    if ($validatedPhoto !== null) {

        $uploadDirectory =
            __DIR__ . "/../uploads/incidents/";

        // Make sure the upload folder exists.
        if (!is_dir($uploadDirectory)) {
            throw new RuntimeException(
                "Incident upload directory does not exist."
            );
        }

        // Generate a random filename.
        $fileName = bin2hex(random_bytes(16)) .
            "." . $validatedPhoto["extension"];

        $savedPhotoFile = $uploadDirectory . $fileName;

        // Move the uploaded file.
        if (!move_uploaded_file(
            $validatedPhoto["tmp_name"],
            $savedPhotoFile
        )) {
            throw new RuntimeException(
                "Unable to save uploaded photo."
            );
        }

        // Store relative path in the database.
        $photoPath = "uploads/incidents/" . $fileName;
    }

    // Generate a unique incident reference number.
    for ($attempt = 0; $attempt < 3; $attempt++) {

        $referenceNumber = "INC-" .
            date("Ymd") . "-" .
            strtoupper(bin2hex(random_bytes(8)));

        try {

            $stmt = $pdo->prepare(
                "INSERT INTO incidents (
                    reference_number,
                    resident_id,
                    category,
                    title,
                    description,
                    location_address,
                    latitude,
                    longitude,
                    photo_path
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $referenceNumber,
                $resident["user_id"],
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
                $e->getCode() === "23000" &&
                isset($e->errorInfo[1]) &&
                (int) $e->errorInfo[1] === 1062 &&
                str_contains(
                    $e->errorInfo[2] ?? "",
                    "reference_number"
                );

            if (!$isDuplicateReference) {
                throw $e;
            }
        }
    }

    if (!$inserted) {
        throw new RuntimeException(
            "Unable to generate a unique reference number."
        );
    }

    // Commit the database changes.
    $pdo->commit();

} catch (Throwable $e) {

    // Undo database changes if possible.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Remove the uploaded photo if saving failed.
    if (
        $savedPhotoFile !== null &&
        is_file($savedPhotoFile)
    ) {
        unlink($savedPhotoFile);
    }

    error_log(
        "Incident submission failed: " . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to save the incident report."
    ]);

    exit;
}

// Default database statuses:
// processing_status = Pending
// map_approval_status = For Review

echo json_encode([
    "success" => true,
    "message" => "Incident report submitted successfully.",
    "reference_number" => $referenceNumber
]);



