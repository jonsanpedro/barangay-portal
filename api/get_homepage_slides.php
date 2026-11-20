
<?php
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

try {
    require_once __DIR__ . "/../config/database.php";

    $sql = "
        SELECT slide_id, image_path, image_title
        FROM homepage_slides
        WHERE is_active = 1
        ORDER BY display_order ASC, slide_id ASC
    ";

    $stmt = $pdo->query($sql);
    $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];

    foreach ($slides as $slide) {
        $path = $slide["image_path"];

        // Only accept local images from approved folders.
        if (
            !is_string($path) ||
            !preg_match(
                '~^(images|uploads/slideshow)/[a-zA-Z0-9/_-]+\.(jpg|jpeg|png|webp)$~i',
                $path
            )
        ) {
            continue;
        }

        if (!is_file(__DIR__ . "/../" . $path)) {
            continue;
        }

        $results[] = [
            "slide_id" => (int) $slide["slide_id"],
            "image_path" => $path,
            "image_title" => $slide["image_title"] ?? ""
        ];
    }

    echo json_encode([
        "success" => true,
        "slides" => $results
    ]);

} catch (Throwable $e) {
    error_log("Homepage slideshow error: " . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to load slideshow images."
    ]);
}
