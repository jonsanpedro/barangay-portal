<?php

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

require_once __DIR__ . "/../config/auth.php";

// Accept POST requests only.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
    exit;
}

// Only active administrators can create announcements.
$admin = requireRole("admin");

// Verify CSRF token.
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

// Read and validate inputs.
$title = $_POST["title"] ?? "";
$content = $_POST["content"] ?? "";
$status = $_POST["status"] ?? "";

if (
    !is_string($title) ||
    !is_string($content) ||
    !is_string($status)
) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "Invalid form data."
    ]);
    exit;
}

$title = trim($title);
$content = trim($content);

if (
    $title === "" ||
    $content === "" ||
    mb_strlen($title, "UTF-8") > 200 ||
    !in_array($status, ["Draft", "Published"], true)
) {
    http_response_code(422);
    echo json_encode([
        "success" => false,
        "message" => "Please provide a valid title, content, and status."
    ]);
    exit;
}

try {
    global $pdo;

    $stmt = $pdo->prepare(
        "INSERT INTO announcements
            (title, content, status, published_at)
         VALUES
            (:title, :content, :status, :published_at)"
    );

    $stmt->execute([
        ":title" => $title,
        ":content" => $content,
        ":status" => $status,
        ":published_at" =>
            $status === "Published"
                ? date("Y-m-d H:i:s")
                : null
    ]);

    http_response_code(201);

    echo json_encode([
        "success" => true,
        "message" => "Announcement created successfully.",
        "announcement_id" => (int) $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    error_log(
        "Create announcement error: " . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to save announcement."
    ]);
}
