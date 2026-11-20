
<?php

require_once __DIR__ . "/config/auth.php";

$admin = requireRole("admin");

// =====================================
// HTML ESCAPING
// =====================================
function e($value): string {
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// =====================================
// EMAIL MASKING
// =====================================
function maskEmail($email): string {

    $email = trim((string) ($email ?? ""));

    if ($email === "") {
        return "—";
    }

    $parts = explode("@", $email);

    if (
        count($parts) !== 2 ||
        $parts[0] === "" ||
        $parts[1] === ""
    ) {
        return "********";
    }

    $username = $parts[0];
    $domain = $parts[1];

    return substr($username, 0, 1)
        . str_repeat(
            "*",
            max(0, strlen($username) - 1)
        )
        . "@"
        . $domain;
}

// =====================================
// GENERATE CSRF TOKEN
// =====================================
if (empty($_SESSION["residents_csrf"])) {
    $_SESSION["residents_csrf"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "";

// =====================================
// ACTIVATE / DISABLE RESIDENT
// =====================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    $residentId = filter_var(
        $_POST["resident_id"] ?? null,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    $action = $_POST["action"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["residents_csrf"], $token)
    ) {

        $message = "Invalid security token. Please refresh.";
        $messageType = "error";

    } elseif (
        !$residentId ||
        !in_array($action, ["activate", "disable"], true)
    ) {

        $message = "Invalid request.";
        $messageType = "error";

    } else {

        $newStatus = $action === "activate"
            ? "active"
            : "disabled";

        try {

            $stmt = $pdo->prepare(
                "UPDATE users
                 SET account_status = ?
                 WHERE user_id = ?
                   AND role = 'resident'"
            );

            $stmt->execute([
                $newStatus,
                $residentId
            ]);

            if ($stmt->rowCount() > 0) {

                $message = "Resident account updated successfully.";
                $messageType = "success";

            } else {

                $message = "No resident account was updated.";
                $messageType = "error";
            }

        } catch (PDOException $exception) {

            error_log(
                "Resident update error: " .
                $exception->getMessage()
            );

            $message = "Unable to update resident account.";
            $messageType = "error";
        }
    }
}

// =====================================
// SEARCH RESIDENTS
// =====================================
$search = trim((string) ($_GET["search"] ?? ""));
$search = substr($search, 0, 100);

try {

    $sql = "
        SELECT
            user_id,
            first_name,
            last_name,
            email,
            username,
            contact_number,
            address,
            account_status,
            created_at
        FROM users
        WHERE role = 'resident'
    ";

    $params = [];

    if ($search !== "") {

        $sql .= "
            AND (
                first_name LIKE ?
                OR last_name LIKE ?
                OR CONCAT(first_name, ' ', last_name) LIKE ?
                OR email LIKE ?
                OR username LIKE ?
                OR contact_number LIKE ?
            )
        ";

        $keyword = "%" . $search . "%";
        $params = array_fill(0, 6, $keyword);
    }

    $sql .= "
        ORDER BY
            created_at DESC,
            user_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // =====================================
    // RESIDENT STATISTICS
    // =====================================
    $statsStmt = $pdo->query(
        "SELECT
            COUNT(*) AS total_residents,
            COUNT(CASE
                WHEN account_status = 'active'
                THEN 1 END) AS active_residents,
            COUNT(CASE
                WHEN account_status = 'disabled'
                THEN 1 END) AS disabled_residents
         FROM users
         WHERE role = 'resident'"
    );

    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $exception) {

    error_log(
        "Residents page error: " .
        $exception->getMessage()
    );

    http_response_code(500);
    exit("Unable to load residents.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Residents Management | BC Portal</title>

    <link rel="stylesheet" href="admin_dashboard.css">

    <style>
        .residents-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .residents-header h1 {
            color: #27358B;
            margin-bottom: 5px;
        }

        .residents-header p {
            color: #718096;
        }

        .residents-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .residents-stat-card {
            background: #fff;
            border-top: 3px solid #F4B83F;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 4px 14px rgba(0,0,0,.05);
        }

        .residents-stat-card p {
            color: #718096;
            margin: 0 0 10px;
        }

        .residents-stat-card h2 {
            color: #27358B;
            margin: 0;
            font-size: 28px;
        }

        .residents-panel {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0,0,0,.04);
        }

        .residents-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .residents-toolbar h2 {
            color: #27358B;
            margin: 0;
        }

        .search-form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .search-form input {
            padding: 11px 13px;
            border: 1px solid #d7dce7;
            border-radius: 8px;
            min-width: 220px;
        }

        .btn {
            display: inline-block;
            border: none;
            border-radius: 7px;
            padding: 10px 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
        }

        .btn-primary {
            background: #27358B;
            color: white;
        }

        .btn-success {
            background: #daf7e6;
            color: #16713b;
        }

        .btn-danger {
            background: #ffe2e2;
            color: #a52222;
        }

        .btn-neutral {
            background: #edf0f6;
            color: #27358B;
        }

        .residents-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .residents-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .residents-table th,
        .residents-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #e8eaf0;
            text-align: left;
            vertical-align: middle;
        }

        .residents-table th {
            background: #f2f4fa;
            color: #27358B;
            white-space: nowrap;
        }

        .residents-table td {
            color: #344054;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-disabled {
            background: #fee2e2;
            color: #991b1b;
        }

        .alert {
            padding: 13px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #667085;
        }

        @media (max-width: 800px) {
            .residents-stats {
                grid-template-columns: 1fr;
            }

            .residents-panel {
                padding: 14px;
            }
        }
    </style>
</head>

<body>

<div class="admin-layout">

    <!-- SHARED ADMIN SIDEBAR -->
    <?php require __DIR__ . "/includes/admin_sidebar.php"; ?>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <header class="residents-header">

            <div>
                <h1>Residents Management</h1>
                <p>Manage registered resident accounts.</p>
            </div>

            <div class="admin-profile">
                Barangay Administrator
            </div>

        </header>

        <?php if ($message !== ""): ?>

            <div
                class="alert alert-<?= e($messageType) ?>"
                role="alert"
            >
                <?= e($message) ?>
            </div>

        <?php endif; ?>

        <!-- STATISTICS -->
        <section class="residents-stats">

            <article class="residents-stat-card">
                <p>Total Residents</p>

                <h2>
                    <?= (int) $stats["total_residents"] ?>
                </h2>
            </article>

            <article class="residents-stat-card">
                <p>Active Residents</p>

                <h2>
                    <?= (int) $stats["active_residents"] ?>
                </h2>
            </article>

            <article class="residents-stat-card">
                <p>Disabled Residents</p>

                <h2>
                    <?= (int) $stats["disabled_residents"] ?>
                </h2>
            </article>

        </section>

        <!-- RESIDENTS TABLE -->
        <section class="residents-panel">

            <div class="residents-toolbar">

                <h2>Registered Residents</h2>

                <form
                    method="GET"
                    action="admin_residents.php"
                    class="search-form"
                >

                    <input
                        type="search"
                        name="search"
                        placeholder="Search residents..."
                        value="<?= e($search) ?>"
                    >

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Search
                    </button>

                    <a
                        href="admin_residents.php"
                        class="btn btn-neutral"
                    >
                        Reset
                    </a>

                </form>

            </div>

            <div class="residents-table-wrap">

                <table class="residents-table">

                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Address</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (empty($residents)): ?>

                        <tr>
                            <td colspan="8" class="empty-state">
                                No residents found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($residents as $resident): ?>

                            <?php
                            $isActive =
                                $resident["account_status"] === "active";
                            ?>

                            <tr>

                                <td>
                                    <?= e(
                                        $resident["first_name"] . " " .
                                        $resident["last_name"]
                                    ) ?>
                                </td>

                                <td>
                                    <?= e($resident["username"] ?: "—") ?>
                                </td>

                                <!-- MASKED EMAIL -->
                                <td>
                                    <?= e(maskEmail($resident["email"])) ?>
                                </td>

                                <!-- MASKED CONTACT NUMBER -->
                                <td>
                                    <?php
                                    $phone = (string) (
                                        $resident["contact_number"] ?? ""
                                    );

                                    if (
                                        $phone !== "" &&
                                        strlen($phone) === 11
                                    ) {
                                        echo e(
                                            substr($phone, 0, 4) .
                                            "***" .
                                            substr($phone, -4)
                                        );
                                    } else {
                                        echo "—";
                                    }
                                    ?>
                                </td>

                                <td>
                                    <?= e($resident["address"] ?: "—") ?>
                                </td>

                                <td>
                                    <?= e($resident["created_at"]) ?>
                                </td>

                                <td>
                                    <span
                                        class="status-badge <?= $isActive
                                            ? "status-active"
                                            : "status-disabled" ?>"
                                    >
                                        <?= $isActive
                                            ? "Active"
                                            : "Disabled" ?>
                                    </span>
                                </td>

                                <td>

                                    <form
                                        method="POST"
                                        action="admin_residents.php"
                                        onsubmit="return confirm('Are you sure you want to change this resident account status?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($_SESSION["residents_csrf"]) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="resident_id"
                                            value="<?= (int) $resident["user_id"] ?>"
                                        >

                                        <?php if ($isActive): ?>

                                            <button
                                                type="submit"
                                                name="action"
                                                value="disable"
                                                class="btn btn-danger"
                                            >
                                                Disable
                                            </button>

                                        <?php else: ?>

                                            <button
                                                type="submit"
                                                name="action"
                                                value="activate"
                                                class="btn btn-success"
                                            >
                                                Activate
                                            </button>

                                        <?php endif; ?>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

<!-- ADMIN LOGOUT -->
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
