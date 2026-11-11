<?php

require_once __DIR__ . "/../config/auth.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

// Public read-only endpoint.
// No login is required to view published announcements.

if ($_SERVER["REQUEST_METHOD"] !== "GET") {

    header("Allow: GET");
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);

    exit;
}

try {

    // Retrieve only published announcements.
    $stmt = $pdo->prepare(
        "SELECT
            announcement_id,
            title,
            content,
            published_at
         FROM announcements
         WHERE status = 'Published'
         ORDER BY published_at DESC, announcement_id DESC"
    );

    $stmt->execute();

    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "announcements" => $announcements
    ]);

} catch (PDOException $e) {

    error_log(
        "Announcements API error: " .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to load announcements."
    ]);
}
