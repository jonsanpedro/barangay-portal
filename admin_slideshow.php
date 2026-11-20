
<?php

require_once __DIR__ . "/config/auth.php";

$admin = requireRole("admin");

function slideEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

if (empty($_SESSION["slideshow_csrf_token"])) {
    $_SESSION["slideshow_csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["slideshow_csrf_token"];

$uploadDirectory = __DIR__ . "/uploads/slideshow";
$uploadWebPath = "uploads/slideshow/";

$successMessage = "";
$errorMessage = "";

if (isset($_SESSION["slideshow_flash"])) {
    $flash = $_SESSION["slideshow_flash"];

    if (($flash["type"] ?? "") === "success") {
        $successMessage = $flash["message"] ?? "";
    } else {
        $errorMessage = $flash["message"] ?? "";
    }

    unset($_SESSION["slideshow_flash"]);
}

function slideRedirect(string $type, string $message): void
{
    $_SESSION["slideshow_flash"] = [
        "type" => $type,
        "message" => $message
    ];

    header("Location: admin_slideshow.php");
    exit;
}

function slideValidateUpload(array $file): array
{
    if (
        !isset($file["error"]) ||
        is_array($file["error"])
    ) {
        throw new RuntimeException("Invalid upload request.");
    }

    if ($file["error"] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            "Image upload failed. Please try again."
        );
    }

    $maxSize = 5 * 1024 * 1024;

    if (
        $file["size"] <= 0 ||
        $file["size"] > $maxSize
    ) {
        throw new RuntimeException(
            "Image must be smaller than 5 MB."
        );
    }

    if (!is_uploaded_file($file["tmp_name"])) {
        throw new RuntimeException("Invalid uploaded file.");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mime = $finfo->file($file["tmp_name"]);

    $allowedTypes = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];

    if (!isset($allowedTypes[$mime])) {
        throw new RuntimeException(
            "Only JPG, PNG, and WebP images are allowed."
        );
    }

    $imageInfo = @getimagesize($file["tmp_name"]);

    if ($imageInfo === false) {
        throw new RuntimeException("Invalid image content.");
    }

    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];

    if (
        $width < 400 ||
        $height < 250 ||
        $width > 6000 ||
        $height > 6000
    ) {
        throw new RuntimeException(
            "Image dimensions must be between 400x250 and 6000x6000 pixels."
        );
    }

    return [
        "extension" => $allowedTypes[$mime]
    ];
}

function slideStoreUpload(
    array $file,
    string $directory,
    string $webPath
): string {
    $validated = slideValidateUpload($file);

    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException(
                "Unable to create image upload directory."
            );
        }
    }

    $filename = bin2hex(random_bytes(16))
        . "."
        . $validated["extension"];

    $destination = $directory . "/" . $filename;

    if (!move_uploaded_file($file["tmp_name"], $destination)) {
        throw new RuntimeException(
            "Unable to save uploaded image."
        );
    }

    chmod($destination, 0644);

    return $webPath . $filename;
}

function slideDeleteManagedFile(string $path): void
{
    // Never delete the original barangay images.
    // Only files inside our dedicated upload folder are removable.

    if (!preg_match(
        '~^uploads/slideshow/[a-f0-9]{32}\.(jpg|png|webp)$~',
        $path
    )) {
        return;
    }

    $fullPath = __DIR__ . "/" . $path;

    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        http_response_code(403);
        exit("Invalid security token.");
    }

    $action = $_POST["action"] ?? "";
    $slideId = filter_var(
        $_POST["slide_id"] ?? null,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    try {

        if ($action === "upload") {

            if (!isset($_FILES["slide_image"])) {
                throw new RuntimeException(
                    "Please choose an image."
                );
            }

            $title = trim(
                (string) ($_POST["image_title"] ?? "")
            );

            if (mb_strlen($title) > 150) {
                throw new RuntimeException(
                    "Image title is too long."
                );
            }

            $path = slideStoreUpload(
                $_FILES["slide_image"],
                $uploadDirectory,
                $uploadWebPath
            );

            try {
                $stmt = $pdo->query(
                    "SELECT COALESCE(MAX(display_order), 0) + 1
                     FROM homepage_slides"
                );

                $nextOrder = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare(
                    "INSERT INTO homepage_slides
                        (image_path, image_title, display_order, is_active)
                     VALUES (?, ?, ?, 1)"
                );

                $stmt->execute([
                    $path,
                    $title !== "" ? $title : null,
                    $nextOrder
                ]);

            } catch (Throwable $exception) {
                slideDeleteManagedFile($path);
                throw $exception;
            }

            slideRedirect(
                "success",
                "New slideshow image uploaded successfully."
            );
        }

        if (!$slideId) {
            throw new RuntimeException("Invalid slide selected.");
        }

        $stmt = $pdo->prepare(
            "SELECT *
             FROM homepage_slides
             WHERE slide_id = ?"
        );

        $stmt->execute([$slideId]);

        $slide = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$slide) {
            throw new RuntimeException("Slide not found.");
        }

        if ($action === "toggle") {

            $newStatus = (int) $slide["is_active"] === 1
                ? 0
                : 1;

            $stmt = $pdo->prepare(
                "UPDATE homepage_slides
                 SET is_active = ?
                 WHERE slide_id = ?"
            );

            $stmt->execute([$newStatus, $slideId]);

            slideRedirect(
                "success",
                $newStatus
                    ? "Slide activated successfully."
                    : "Slide deactivated successfully."
            );
        }

        if ($action === "replace") {

            if (!isset($_FILES["slide_image"])) {
                throw new RuntimeException(
                    "Please choose a replacement image."
                );
            }

            $newPath = slideStoreUpload(
                $_FILES["slide_image"],
                $uploadDirectory,
                $uploadWebPath
            );

            try {
                $stmt = $pdo->prepare(
                    "UPDATE homepage_slides
                     SET image_path = ?
                     WHERE slide_id = ?"
                );

                $stmt->execute([$newPath, $slideId]);

            } catch (Throwable $exception) {
                slideDeleteManagedFile($newPath);
                throw $exception;
            }

            slideDeleteManagedFile($slide["image_path"]);

            slideRedirect(
                "success",
                "Slideshow image replaced successfully."
            );
        }

        if ($action === "delete") {

            $stmt = $pdo->prepare(
                "DELETE FROM homepage_slides
                 WHERE slide_id = ?"
            );

            $stmt->execute([$slideId]);

            slideDeleteManagedFile($slide["image_path"]);

            slideRedirect(
                "success",
                "Slide removed successfully."
            );
        }

        if ($action === "move_up" || $action === "move_down") {

            $operator = $action === "move_up" ? "<" : ">";
            $direction = $action === "move_up" ? "DESC" : "ASC";

            $pdo->beginTransaction();

            try {

                // Lock the selected slide before reordering.
                $stmt = $pdo->prepare(
                    "SELECT slide_id, display_order
                     FROM homepage_slides
                     WHERE slide_id = ?
                     FOR UPDATE"
                );

                $stmt->execute([$slideId]);

                $current = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$current) {
                    throw new RuntimeException("Slide not found.");
                }

                $stmt = $pdo->prepare(
                    "SELECT slide_id, display_order
                     FROM homepage_slides
                     WHERE display_order $operator ?
                        OR (
                            display_order = ?
                            AND slide_id $operator ?
                        )
                     ORDER BY display_order $direction,
                              slide_id $direction
                     LIMIT 1
                     FOR UPDATE"
                );

                $stmt->execute([
                    $current["display_order"],
                    $current["display_order"],
                    $slideId
                ]);

                $neighbor = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($neighbor) {

                    $stmt = $pdo->prepare(
                        "UPDATE homepage_slides
                         SET display_order = ?
                         WHERE slide_id = ?"
                    );

                    $stmt->execute([
                        $neighbor["display_order"],
                        $slideId
                    ]);

                    $stmt->execute([
                        $current["display_order"],
                        $neighbor["slide_id"]
                    ]);

                    // Handle equal order values with normalization below.
                }

                // Normalize all positions to 1, 2, 3...
                $stmt = $pdo->query(
                    "SELECT slide_id
                     FROM homepage_slides
                     ORDER BY display_order ASC, slide_id ASC
                     FOR UPDATE"
                );

                $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $update = $pdo->prepare(
                    "UPDATE homepage_slides
                     SET display_order = ?
                     WHERE slide_id = ?"
                );

                foreach ($ids as $index => $id) {
                    $update->execute([$index + 1, $id]);
                }

                $pdo->commit();

            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            slideRedirect(
                "success",
                "Slideshow order updated."
            );
        }

        throw new RuntimeException("Unknown action.");

    } catch (Throwable $exception) {

        error_log(
            "Slideshow management: " . $exception->getMessage()
        );

        $safeErrors = [
            "Invalid upload request.",
            "Image upload failed. Please try again.",
            "Image must be smaller than 5 MB.",
            "Invalid uploaded file.",
            "Only JPG, PNG, and WebP images are allowed.",
            "Invalid image content.",
            "Image dimensions must be between 400x250 and 6000x6000 pixels.",
            "Unable to create image upload directory.",
            "Unable to save uploaded image.",
            "Please choose an image.",
            "Image title is too long.",
            "Invalid slide selected.",
            "Slide not found.",
            "Please choose a replacement image.",
            "Unknown action."
        ];

        $publicMessage = in_array(
            $exception->getMessage(),
            $safeErrors,
            true
        )
            ? $exception->getMessage()
            : "Unable to complete the action. Please try again.";

        slideRedirect("error", $publicMessage);
    }
}

try {

    $stmt = $pdo->query(
        "SELECT *
         FROM homepage_slides
         ORDER BY display_order ASC, slide_id ASC"
    );

    $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {

    error_log($exception->getMessage());

    http_response_code(500);
    exit("Unable to load slideshow images.");
}

$totalSlides = count($slides);
$activeSlides = count(
    array_filter(
        $slides,
        fn($slide) => (int) $slide["is_active"] === 1
    )
);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Homepage Slideshow | Barangay Chrysanthemum</title>

    <link rel="stylesheet" href="admin_dashboard.css">

    <style>
        .slideshow-page,
        .slideshow-page * {
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        .slideshow-page {
            color: #121B2C;
        }

        .slideshow-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .slideshow-heading h1 {
            margin: 0 0 8px;
            color: #27358B;
            font-size: clamp(26px, 3vw, 34px);
        }

        .slideshow-heading p {
            margin: 0;
            color: #64748B;
            font-size: 14px;
            line-height: 1.6;
        }

        .admin-profile {
            background: white;
            padding: 12px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
        }

        .slideshow-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 25px;
            padding: 30px 34px;
            border-radius: 18px;
            margin-bottom: 25px;
            color: white;
            background: linear-gradient(
                115deg,
                #27358B,
                #3448A5 65%,
                #5266C1
            );
        }

        .slideshow-banner h2 {
            margin: 0 0 12px;
            color: white;
            font-size: 27px;
        }

        .slideshow-banner p {
            margin: 0;
            color: #E3E9FF;
            font-size: 14px;
            line-height: 1.7;
            max-width: 580px;
        }

        .slideshow-banner img {
            display: block;
            width: 125px;
            height: 125px;
            object-fit: contain;
            flex-shrink: 0;

            background: #FFFFFF;
            padding: 8px;

            border: 3px solid #F4B83F;
            border-radius: 50%;

            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
        }

        .slideshow-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .slideshow-stat {
            background: white;
            border: 1px solid #E9EDF5;
            border-radius: 15px;
            padding: 22px;
            border-left: 5px solid #27358B;
        }

        .slideshow-stat:nth-child(2) {
            border-left-color: #F4B83F;
        }

        .slideshow-stat span {
            display: block;
            color: #64748B;
            font-size: 13px;
        }

        .slideshow-stat strong {
            display: block;
            margin-top: 12px;
            font-size: 32px;
            color: #27358B;
        }

        .slideshow-panel {
            background: white;
            border: 1px solid #E9EDF5;
            border-radius: 17px;
            padding: 26px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(22,34,80,.04);
        }

        .slideshow-panel h2 {
            color: #27358B;
            margin: 0 0 8px;
            font-size: 20px;
        }

        .panel-description {
            margin: 0 0 22px;
            font-size: 13px;
            line-height: 1.6;
            color: #64748B;
        }

        .upload-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            align-items: end;
        }

        .slide-field label {
            display: block;
            margin-bottom: 9px;
            font-size: 13px;
            font-weight: 700;
            color: #27358B;
        }

        .slide-field input {
            width: 100%;
            min-width: 0;
            padding: 13px;
            border: 1px solid #CBD5E1;
            border-radius: 9px;
            background: #F8FAFC;
            color: #121B2C;
        }

        .slide-field input:focus {
            outline: 2px solid #27358B;
            outline-offset: 2px;
        }

        .upload-actions {
            margin-top: 20px;
        }

        .slide-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 11px 15px;
            border-radius: 9px;
            border: 1px solid transparent;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: .2s ease;
        }

        .slide-btn:hover {
            filter: brightness(.94);
        }

        .slide-btn:disabled {
            opacity: .45;
            cursor: not-allowed;
        }

        .btn-primary {
            background: #27358B;
            color: white;
        }

        .btn-yellow {
            background: #F4B83F;
            color: #121B2C;
        }

        .btn-outline {
            background: white;
            color: #27358B;
            border-color: #CBD5E1;
        }

        .btn-danger {
            background: #FFF1F2;
            color: #BE123C;
            border-color: #FECDD3;
        }

        .slide-alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 14px;
            line-height: 1.6;
        }

        .slide-alert.success {
            background: #ECFDF5;
            color: #166534;
            border: 1px solid #BBF7D0;
        }

        .slide-alert.error {
            background: #FFF1F2;
            color: #9F1239;
            border: 1px solid #FECDD3;
        }

        .slides-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .slide-card {
            border: 1px solid #E5EAF3;
            border-radius: 15px;
            overflow: hidden;
            background: white;
        }

        .slide-preview {
            position: relative;
            background: #F1F5F9;
            aspect-ratio: 16 / 9;
            overflow: hidden;
        }

        .slide-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .slide-number {
            position: absolute;
            top: 12px;
            left: 12px;
            padding: 7px 11px;
            border-radius: 7px;
            background: rgba(18,27,44,.8);
            color: white;
            font-size: 12px;
            font-weight: 700;
        }

        .slide-card-body {
            padding: 20px;
        }

        .slide-card-title {
            margin: 0 0 8px;
            color: #27358B;
            font-size: 16px;
        }

        .slide-path {
            margin: 0 0 15px;
            color: #64748B;
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .slide-status {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .slide-status.active {
            background: #DCFCE7;
            color: #166534;
        }

        .slide-status.inactive {
            background: #F1F5F9;
            color: #64748B;
        }

        .slide-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }

        .slide-actions form {
            display: inline-flex;
        }

        .replace-form {
            border-top: 1px solid #E9EDF5;
            padding-top: 15px;
        }

        .replace-form label {
            display: block;
            margin-bottom: 9px;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }

        .replace-form input[type="file"] {
            width: 100%;
            max-width: 100%;
            font-size: 12px;
            margin-bottom: 12px;
        }

        .empty-slides {
            text-align: center;
            padding: 45px 20px;
            border: 2px dashed #CBD5E1;
            border-radius: 12px;
            color: #64748B;
        }

        .empty-slides strong {
            display: block;
            color: #27358B;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .slideshow-help {
            margin-top: 18px;
            padding: 16px;
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            border-radius: 10px;
            color: #92400E;
            font-size: 13px;
            line-height: 1.7;
        }

        @media (max-width: 900px) {
            .slides-grid,
            .upload-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .slideshow-banner {
                padding: 24px;
            }

            .slideshow-banner img {
                display: none;
            }

            .slideshow-banner h2 {
                font-size: 23px;
            }

            .slideshow-stats {
                grid-template-columns: 1fr;
            }

            .slideshow-panel {
                padding: 18px;
            }

            .slide-actions {
                gap: 7px;
            }
        }
    </style>
</head>

<body>

<div class="admin-layout">

    <?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

    <main class="main-content slideshow-page">

        <header class="slideshow-heading">
            <div>
                <h1>Homepage Slideshow</h1>
                <p>
                    Manage the photos displayed on the
                    Barangay Chrysanthemum homepage.
                </p>
            </div>

            <div class="admin-profile">
                Barangay Administrator
            </div>
        </header>

        <section class="slideshow-banner">

            <div>
                <h2>Slideshow Management</h2>

                <p>
                    Keep the community homepage updated with
                    official barangay photos. Upload new images,
                    arrange their display order, and control
                    which pictures are visible.
                </p>
            </div>

            <img
                src="images/barangay_logo_updated.png"
                alt="Barangay Chrysanthemum Official Logo"
            >

        </section>

        <?php if ($successMessage !== ""): ?>
            <div class="slide-alert success" role="status">
                <?= slideEscape($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ""): ?>
            <div class="slide-alert error" role="alert">
                <?= slideEscape($errorMessage) ?>
            </div>
        <?php endif; ?>

        <section class="slideshow-stats">

            <article class="slideshow-stat">
                <span>Total Slideshow Images</span>
                <strong><?= $totalSlides ?></strong>
            </article>

            <article class="slideshow-stat">
                <span>Active Images</span>
                <strong><?= $activeSlides ?></strong>
            </article>

        </section>

        <section class="slideshow-panel">

            <h2>Upload New Image</h2>

            <p class="panel-description">
                Add a new picture to the homepage slideshow.
                Accepted formats: JPG, PNG, WebP.
                Maximum file size: 5 MB.
            </p>

            <form
                method="POST"
                enctype="multipart/form-data"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= slideEscape($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="upload"
                >

                <div class="upload-grid">

                    <div class="slide-field">
                        <label for="image_title">
                            Image Title (Optional)
                        </label>

                        <input
                            type="text"
                            id="image_title"
                            name="image_title"
                            maxlength="150"
                            placeholder="e.g., Barangay Community Event"
                        >
                    </div>

                    <div class="slide-field">
                        <label for="slide_image">
                            Choose Image
                        </label>

                        <input
                            type="file"
                            id="slide_image"
                            name="slide_image"
                            accept="image/jpeg,image/png,image/webp"
                            required
                        >
                    </div>

                </div>

                <div class="upload-actions">
                    <button
                        type="submit"
                        class="slide-btn btn-primary"
                    >
                        + Upload Image
                    </button>
                </div>

            </form>

        </section>

        <section class="slideshow-panel">

            <h2>Current Slideshow Images</h2>

            <p class="panel-description">
                Preview, replace, reorder, activate,
                deactivate, or remove existing pictures.
            </p>

            <?php if (empty($slides)): ?>

                <div class="empty-slides">
                    <strong>No slideshow images yet</strong>
                    Upload your first image to get started.
                </div>

            <?php else: ?>

                <div class="slides-grid">

                    <?php foreach ($slides as $index => $slide): ?>

                        <?php
                        $slideId = (int) $slide["slide_id"];
                        $isActive = (int) $slide["is_active"] === 1;
                        ?>

                        <article class="slide-card">

                            <div class="slide-preview">

                                <img
                                    src="<?= slideEscape($slide["image_path"]) ?>"
                                    alt="<?= slideEscape(
                                        $slide["image_title"] ?: "Barangay slideshow image"
                                    ) ?>"
                                    loading="lazy"
                                >

                                <span class="slide-number">
                                    Slide <?= $index + 1 ?>
                                </span>

                            </div>

                            <div class="slide-card-body">

                                <h3 class="slide-card-title">
                                    <?= slideEscape(
                                        $slide["image_title"]
                                        ?: "Untitled Slide"
                                    ) ?>
                                </h3>

                                <p class="slide-path">
                                    <?= slideEscape($slide["image_path"]) ?>
                                </p>

                                <span class="slide-status <?= $isActive ? 'active' : 'inactive' ?>">
                                    <?= $isActive ? "● Active" : "○ Inactive" ?>
                                </span>

                                <div class="slide-actions">

                                    <form method="POST">
                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= slideEscape($csrfToken) ?>">

                                        <input type="hidden"
                                               name="slide_id"
                                               value="<?= $slideId ?>">

                                        <input type="hidden"
                                               name="action"
                                               value="move_up">

                                        <button
                                            type="submit"
                                            class="slide-btn btn-outline"
                                            <?= $index === 0 ? "disabled" : "" ?>
                                        >
                                            ↑ Up
                                        </button>
                                    </form>

                                    <form method="POST">
                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= slideEscape($csrfToken) ?>">

                                        <input type="hidden"
                                               name="slide_id"
                                               value="<?= $slideId ?>">

                                        <input type="hidden"
                                               name="action"
                                               value="move_down">

                                        <button
                                            type="submit"
                                            class="slide-btn btn-outline"
                                            <?= $index === $totalSlides - 1 ? "disabled" : "" ?>
                                        >
                                            ↓ Down
                                        </button>
                                    </form>

                                    <form method="POST">
                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= slideEscape($csrfToken) ?>">

                                        <input type="hidden"
                                               name="slide_id"
                                               value="<?= $slideId ?>">

                                        <input type="hidden"
                                               name="action"
                                               value="toggle">

                                        <button
                                            type="submit"
                                            class="slide-btn <?= $isActive ? 'btn-outline' : 'btn-yellow' ?>"
                                        >
                                            <?= $isActive ? "Deactivate" : "Activate" ?>
                                        </button>
                                    </form>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Remove this slide from the slideshow?');"
                                    >
                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= slideEscape($csrfToken) ?>">

                                        <input type="hidden"
                                               name="slide_id"
                                               value="<?= $slideId ?>">

                                        <input type="hidden"
                                               name="action"
                                               value="delete">

                                        <button
                                            type="submit"
                                            class="slide-btn btn-danger"
                                        >
                                            Delete
                                        </button>
                                    </form>

                                </div>

                                <form
                                    method="POST"
                                    enctype="multipart/form-data"
                                    class="replace-form"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= slideEscape($csrfToken) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="slide_id"
                                        value="<?= $slideId ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="replace"
                                    >

                                    <label>
                                        Replace This Image
                                    </label>

                                    <input
                                        type="file"
                                        name="slide_image"
                                        accept="image/jpeg,image/png,image/webp"
                                        required
                                    >

                                    <button
                                        type="submit"
                                        class="slide-btn btn-primary"
                                    >
                                        Replace Image
                                    </button>

                                </form>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <div class="slideshow-help">
                <strong>Important:</strong>
                Only active images will be included in the
                database-powered homepage slideshow once the
                homepage integration is completed.
                Existing original barangay photos are preserved
                when removed or replaced.
            </div>

        </section>

    </main>

</div>

<script>
"use strict";

const logoutForm = document.getElementById(
    "admin-logout-form"
);

if (logoutForm) {
    logoutForm.addEventListener(
        "submit",
        async function(event) {
            event.preventDefault();

            try {
                const response = await fetch(
                    "api/logout.php",
                    {
                        method: "POST",
                        credentials: "same-origin"
                    }
                );

                if (!response.ok) {
                    throw new Error("Logout failed.");
                }

                window.location.href = "index.html";

            } catch (error) {
                alert(
                    "Unable to log out. Please try again."
                );
            }
        }
    );
}
</script>

</body>
</html>
