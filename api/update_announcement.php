
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

// Only active administrators can update announcements.
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

// Validate form inputs.
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
        "message" => "Please provide valid announcement details."
    ]);
    exit;
}

try {
    // Check existing announcement and its status.
    $stmt = $pdo->prepare(
        "SELECT status, published_at
         FROM announcements
         WHERE announcement_id = ?
         LIMIT 1"
    );

    $stmt->execute([$id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "Announcement not found."
        ]);
        exit;
    }

    // Keep the original publication date when already published.
    // Set a new publication date when publishing a draft.
    // Clear the date when reverting to draft.
    if ($status === "Draft") {
        $publishedAt = null;
    } elseif ($existing["status"] === "Published") {
        $publishedAt = $existing["published_at"]
            ?: date("Y-m-d H:i:s");
    } else {
        $publishedAt = date("Y-m-d H:i:s");
    }

    $update = $pdo->prepare(
        "UPDATE announcements
         SET title = :title,
             content = :content,
             status = :status,
             published_at = :published_at
         WHERE announcement_id = :id"
    );

    $update->execute([
        ":title" => $title,
        ":content" => $content,
        ":status" => $status,
        ":published_at" => $publishedAt,
        ":id" => $id
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Announcement updated successfully."
    ]);

} catch (PDOException $e) {
    error_log(
        "Update announcement error: " . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to update announcement."
    ]);
}
