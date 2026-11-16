
<?php

require_once __DIR__ . "/../config/auth.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

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
    $residentId = 0;

    // Identify an active, logged-in resident.
    // Guests can still view support counts.
    if (!empty($_SESSION["user_id"])) {
        $checkUser = $pdo->prepare(
            "SELECT user_id
             FROM users
             WHERE user_id = ?
               AND role = 'resident'
               AND account_status = 'active'
             LIMIT 1"
        );

        $checkUser->execute([
            (int) $_SESSION["user_id"]
        ]);

        if ($checkUser->fetch(PDO::FETCH_ASSOC)) {
            $residentId = (int) $_SESSION["user_id"];
        }
    }

    // Return support counts for approved incidents only.
    $stmt = $pdo->prepare(
        "SELECT
            i.incident_id,
            COUNT(s.support_id) AS support_count,
            MAX(
                CASE
                    WHEN s.resident_id = :resident_id
                    THEN 1
                    ELSE 0
                END
            ) AS supported
         FROM incidents i
         LEFT JOIN incident_supports s
             ON s.incident_id = i.incident_id
         WHERE i.map_approval_status = 'Approved'
         GROUP BY i.incident_id"
    );

    $stmt->execute([
        ":resident_id" => $residentId
    ]);

    $supports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "supports" => $supports,
        "can_support" => $residentId > 0
    ]);

} catch (PDOException $e) {
    error_log(
        "Get incident supports error: " . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to load incident supports."
    ]);
}
