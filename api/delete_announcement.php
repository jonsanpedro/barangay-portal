
<?php

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../config/auth.php";

// Allow POST requests only.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");

    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

// Only active administrators can delete announcements.
$admin = requireRole("admin");


// Validate CSRF token.
$submittedToken = $_POST["csrf_token"] ?? "";
$sessionToken = $_SESSION["csrf_token"] ?? "";

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $sessionToken === "" ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Invalid security token. Refresh the page."
    ]);
    exit;
}

// Validate announcement ID.
$id = filter_var(
    $_POST["announcement_id"] ?? null,
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$id) {
    http_response_code(422);

    echo json_encode([
        "success" => false,
        "message" => "Invalid announcement ID."
    ]);
    exit;
}

try {
    // Check if the announcement exists.
    $stmt = $pdo->prepare(
        "SELECT announcement_id
         FROM announcements
         WHERE announcement_id = ?
         LIMIT 1"
    );

    $stmt->execute([$id]);

    if (!$stmt->fetch()) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Announcement not found."
        ]);
        exit;
    }

    // Permanently delete the announcement.
    $delete = $pdo->prepare(
        "DELETE FROM announcements
         WHERE announcement_id = ?"
    );

    $delete->execute([$id]);

    echo json_encode([
        "success" => true,
        "message" => "Announcement deleted successfully."
    ]);

} catch (PDOException $e) {
    error_log(
        "Delete announcement error: " . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to delete announcement."
    ]);
}
