<?php
session_start();
require_once 'db.php';

// --- ПРОВЕРКА ПРАВ АДМИНИСТРАТОРА ---
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('HTTP/1.1 403 Forbidden');
    die("
        <div style='background:#0b0f19; color:#ef4444; font-family:sans-serif; height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center;'>
            <h1 style='font-size:48px; margin-bottom:10px;'>403 — Доступ запрещен ⛔</h1>
            <p style='color:#8a99ad; font-size:18px;'>Панель управления доступна только для аккаунта администратора (admin).</p>
            <a href='index.php' style='margin-top:20px; color:#00e676; text-decoration:none; border:1px solid #00e676; padding:10px 20px; border-radius:20px; font-weight:bold;'>← Вернуться в магазин</a>
        </div>
    ");
}

// Автоматическая проверка и создание таблицы промокодов с новыми полями
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS promo_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) UNIQUE NOT NULL,
        discount_percent INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        expires_at DATE NULL,
        max_uses INT DEFAULT 0,
        used_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    // Таблица уже создана
}

// --- ФУНКЦИЯ ЭКСПОРТА РЕЗЕРВНОЙ КОПИИ (BACKUP SQL) ---
if (isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    $tables = [];
    $query = $pdo->query("SHOW TABLES");
    while ($row = $query->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sql_script = "-- CyberPhone DB Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $query = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $query->fetch(PDO::FETCH_NUM);
        $sql_script .= "DROP TABLE IF EXISTS `$table`;\n" . $row[1] . ";\n\n";

        $query = $pdo->query("SELECT * FROM `$table`");
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $keys = array_keys($row);
            $values = array_values($row);
            $escaped_values = array_map(function($val) use ($pdo) {
                if ($val === null) return 'NULL';
                return $pdo->quote($val);
            }, $values);
            $sql_script .= "INSERT INTO `$table` (`" . implode("`, `", $keys) . "`) VALUES (" . implode(", ", $escaped_values) . ");\n";
        }
        $sql_script .= "\n";
    }
    $sql_script .= "SET FOREIGN_KEY_CHECKS=1;\n";

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="cyberphone_backup_' . date('Y-m-d_H-i') . '.sql"');
    header('Content-Length: ' . strlen($sql_script));
    echo $sql_script;
    exit;
}

$message = $_SESSION['admin_msg'] ?? '';
$message_type = $_SESSION['admin_msg_type'] ?? '';
unset($_SESSION['admin_msg'], $_SESSION['admin_msg_type']);

function normalizePath($path) {
    $clean = str_replace('\\', '/', trim($path));
    return preg_replace('#/+#', '/', $clean);
}

function prepareColorsData($color_names, $color_images) {
    $colors_map = [];
    $first_image = '';

    if (is_array($color_names)) {
        foreach ($color_names as $idx => $cname) {
            $clean_name = trim($cname);
            $img_path = isset($color_images[$idx]) ? normalizePath($color_images[$idx]) : '';

            if (!empty($clean_name)) {
                $colors_map[$clean_name] = $img_path;
                if (empty($first_image) && !empty($img_path)) {
                    $first_image = $img_path;
                }
            }
        }
    }

    return [
        'colors_json' => json_encode($colors_map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'first_image' => $first_image
    ];
}

function prepareSpecsData($spec_names, $spec_values) {
    $specs_map = [];
    if (is_array($spec_names)) {
        foreach ($spec_names as $idx => $sname) {
            $clean_name = trim($sname);
            $val = isset($spec_values[$idx]) ? trim($spec_values[$idx]) : '';
            if (!empty($clean_name)) {
                $specs_map[$clean_name] = $val;
            }
        }
    }
    return json_encode($specs_map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// --- УПРАВЛЕНИЕ ПРОМОКОДАМИ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_promo'])) {
    $code = mb_strtoupper(trim($_POST['promo_code']));
    $discount = (int)$_POST['discount_percent'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $max_uses = (int)($_POST['max_uses'] ?? 0);

    if (!empty($code) && $discount > 0 && $discount <= 100) {
        try {
            $stmt = $pdo->prepare("INSERT INTO promo_codes (code, discount_percent, is_active, expires_at, max_uses) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$code, $discount, $is_active, $expires_at, $max_uses]);
            $_SESSION['admin_msg'] = "Промокод «{$code}» ({$discount}%) успешно создан!";
            $_SESSION['admin_msg_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['admin_msg'] = "Ошибка: Промокод с таким кодом уже существует.";
            $_SESSION['admin_msg_type'] = "error";
        }
    } else {
        $_SESSION['admin_msg'] = "Укажите верный код и процент скидки от 1 до 100%.";
        $_SESSION['admin_msg_type'] = "error";
    }
    header('Location: admin.php?tab=promos');
    exit;
}

if (isset($_GET['toggle_promo_id'])) {
    $promo_id = (int)$_GET['toggle_promo_id'];
    try {
        $stmt = $pdo->prepare("UPDATE promo_codes SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$promo_id]);
        $_SESSION['admin_msg'] = "Статус промокода изменен!";
        $_SESSION['admin_msg_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['admin_msg'] = "Ошибка: " . $e->getMessage();
        $_SESSION['admin_msg_type'] = "error";
    }
    header('Location: admin.php?tab=promos');
    exit;
}

if (isset($_GET['delete_promo_id'])) {
    $promo_id = (int)$_GET['delete_promo_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM promo_codes WHERE id = ?");
        $stmt->execute([$promo_id]);
        $_SESSION['admin_msg'] = "Промокод удален!";
        $_SESSION['admin_msg_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['admin_msg'] = "Ошибка удаления: " . $e->getMessage();
        $_SESSION['admin_msg_type'] = "error";
    }
    header('Location: admin.php?tab=promos');
    exit;
}

// ОБНОВЛЕНИЕ СТАТУСА ЗАКАЗА
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = trim($_POST['new_status']);

    if ($order_id > 0 && !empty($new_status)) {
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);
            $_SESSION['admin_msg'] = "Статус заказа #{$order_id} изменен на «{$new_status}»!";
            $_SESSION['admin_msg_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['admin_msg'] = "Ошибка смены статуса: " . $e->getMessage();
            $_SESSION['admin_msg_type'] = "error";
        }
    }
    header('Location: admin.php?tab=orders');
    exit;
}

// УДАЛЕНИЕ ЗАКАЗА
if (isset($_GET['delete_order_id'])) {
    $del_ord_id = (int)$_GET['delete_order_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$del_ord_id]);
        $_SESSION['admin_msg'] = "Заказ #{$del_ord_id} успешно удален!";
        $_SESSION['admin_msg_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['admin_msg'] = "Ошибка удаления заказа: " . $e->getMessage();
        $_SESSION['admin_msg_type'] = "error";
    }
    header('Location: admin.php?tab=orders');
    exit;
}

// ОБНОВЛЕНИЕ ТОВАРА
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product_full'])) {
    $product_id = (int)$_POST['product_id'];
    $name = trim($_POST['name']);
    $brand = trim($_POST['brand']);
    $category = trim($_POST['category']);
    $price = (float)$_POST['price'];
    $release_year = (int)$_POST['release_year'];
    $description = trim($_POST['description']);
    $storage_options = trim($_POST['storage_options']);
    $ram = trim($_POST['ram']);
    
    $processed_colors = prepareColorsData($_POST['color_names'] ?? [], $_POST['color_images'] ?? []);
    $image_url = !empty($_POST['fallback_image_url']) ? normalizePath($_POST['fallback_image_url']) : $processed_colors['first_image'];
    $colors_data = $processed_colors['colors_json'];
    $specs_json = prepareSpecsData($_POST['spec_names'] ?? [], $_POST['spec_values'] ?? []);

    if ($product_id > 0 && !empty($name) && !empty($brand) && $price > 0) {
        try {
            $sql = "UPDATE products SET 
                    name = ?, brand = ?, category = ?, price = ?, release_year = ?, 
                    description = ?, colors = ?, storage_options = ?, ram = ?, image_url = ?, specs = ? 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $brand, $category, $price, $release_year, $description, $colors_data, $storage_options, $ram, $image_url, $specs_json, $product_id]);
            
            $_SESSION['admin_msg'] = "Гаджет #{$product_id} («" . htmlspecialchars($name) . "») успешно сохранен!";
            $_SESSION['admin_msg_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['admin_msg'] = "Ошибка обновления: " . $e->getMessage();
            $_SESSION['admin_msg_type'] = "error";
        }
    }
    header('Location: admin.php');
    exit;
}

// БЫСТРОЕ ОБНОВЛЕНИЕ ЦЕНЫ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_price'])) {
    $product_id = (int)$_POST['product_id'];
    $new_price = (float)$_POST['new_price'];

    if ($product_id > 0 && $new_price > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE products SET price = ? WHERE id = ?");
            $stmt->execute([$new_price, $product_id]);
            $_SESSION['admin_msg'] = "Цена для товара #{$product_id} изменена на " . number_format($new_price, 0, '', ' ') . " грн.!";
            $_SESSION['admin_msg_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['admin_msg'] = "Ошибка обновления цены: " . $e->getMessage();
            $_SESSION['admin_msg_type'] = "error";
        }
    }
    header('Location: admin.php');
    exit;
}

// УДАЛЕНИЕ ТОВАРА
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$delete_id]);
        $_SESSION['admin_msg'] = "Товар #{$delete_id} успешно удален!";
        $_SESSION['admin_msg_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['admin_msg'] = "Ошибка при удалении: " . $e->getMessage();
        $_SESSION['admin_msg_type'] = "error";
    }
    header('Location: admin.php');
    exit;
}

// ДОБАВЛЕНИЕ ТОВАРА
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $brand = trim($_POST['brand']);
    $category = trim($_POST['category']);
    $price = (float)$_POST['price'];
    $release_year = (int)$_POST['release_year'];
    $description = trim($_POST['description']);
    $storage_options = trim($_POST['storage_options']);
    $ram = trim($_POST['ram']);

    $processed_colors = prepareColorsData($_POST['color_names'] ?? [], $_POST['color_images'] ?? []);
    $image_url = !empty($_POST['fallback_image_url']) ? normalizePath($_POST['fallback_image_url']) : $processed_colors['first_image'];
    $colors_data = $processed_colors['colors_json'];
    $specs_json = prepareSpecsData($_POST['spec_names'] ?? [], $_POST['spec_values'] ?? []);

    if (empty($name) || empty($brand) || $price <= 0) {
        $_SESSION['admin_msg'] = "Заполните обязательные поля: Название, Бренд и Цена!";
        $_SESSION['admin_msg_type'] = "error";
    } else {
        try {
            $sql = "INSERT INTO products (name, brand, category, price, release_year, description, colors, storage_options, ram, image_url, specs) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $brand, $category, $price, $release_year, $description, $colors_data, $storage_options, $ram, $image_url, $specs_json]);
            
            $_SESSION['admin_msg'] = "Гаджет «" . htmlspecialchars($name) . "» опубликован!";
            $_SESSION['admin_msg_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['admin_msg'] = "Ошибка БД: " . $e->getMessage();
            $_SESSION['admin_msg_type'] = "error";
        }
    }
    header('Location: admin.php');
    exit;
}

try {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
    $products = $stmt->fetchAll();

    $stmt_orders = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
    $orders = $stmt_orders->fetchAll();

    $stmt_promos = $pdo->query("SELECT * FROM promo_codes ORDER BY id DESC");
    $promos = $stmt_promos->fetchAll();

    $users_stmt = $pdo->query("
        SELECT u.*, COUNT(o.id) as orders_count 
        FROM users u 
        LEFT JOIN orders o ON u.id = o.user_id 
        GROUP BY u.id 
        ORDER BY u.id DESC
    ");
    $users = $users_stmt->fetchAll();

    $sales_chart_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $date_label = date('d.m', strtotime("-$i days"));
        $sum_stmt = $pdo->prepare("SELECT SUM(total_price) as sum FROM orders WHERE DATE(created_at) = ? AND status != 'Отменен'");
        $sum_stmt->execute([$date]);
        $val = (float)($sum_stmt->fetch()['sum'] ?? 0);
        $sales_chart_data[] = ['date' => $date_label, 'val' => $val];
    }

    $max_chart_val = max(array_column($sales_chart_data, 'val')) ?: 1;
    $total_revenue = array_sum(array_column($orders, 'total_price'));
} catch (PDOException $e) {
    die("Ошибка загрузки данных: " . $e->getMessage());
}

$active_tab = $_GET['tab'] ?? 'catalog';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора | CyberPhone</title>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <style>
        :root {
            --bg-color: #0b0f19;
            --panel-bg: #131926;
            --border-color: #1f293d;
            --text-main: #ffffff;
            --text-muted: #8a99ad;
            --accent-green: #00e676;
            --accent-red: #ef4444;
            --accent-blue: #2563eb;
            --input-bg: #0b0f19;
            --input-text: #ffffff;
            --card-img-bg: #1a2234;
        }

        [data-theme="light"] {
            --bg-color: #f1f5f9;
            --panel-bg: #ffffff;
            --border-color: #cbd5e1;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --accent-green: #10b981;
            --accent-red: #dc2626;
            --accent-blue: #2563eb;
            --input-bg: #f8fafc;
            --input-text: #0f172a;
            --card-img-bg: #e2e8f0;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 30px 20px;
            transition: background-color 0.3s, color 0.3s;
        }

        .admin-container {
            max-width: 1250px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .header h1 { margin: 0; font-size: 26px; }

        .logo-admin-link {
            text-decoration: none;
            color: var(--text-main);
            font-weight: 800;
            transition: opacity 0.2s;
        }
        .logo-admin-link:hover { opacity: 0.85; }
        .logo-admin-link span { color: var(--accent-blue); }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) 300px;
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 18px 22px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .stat-card-title {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-card-val {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
        }

        .chart-box {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 15px 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .chart-bars {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            height: 50px;
            gap: 6px;
            margin-top: 10px;
        }

        .chart-bar-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            justify-content: flex-end;
        }

        .chart-bar-fill {
            width: 100%;
            max-width: 18px;
            background: linear-gradient(180deg, #2563eb 0%, #00e676 100%);
            border-radius: 4px 4px 0 0;
            transition: height 0.5s ease;
        }

        .chart-bar-date {
            font-size: 9px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .tabs-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
        }

        .tab-btn {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 10px 20px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .tab-btn.active, .tab-btn:hover {
            background: var(--accent-blue);
            color: #ffffff;
            border-color: var(--accent-blue);
        }

        .tab-badge {
            background: var(--accent-green);
            color: #000;
            font-size: 11px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 10px;
        }

        .theme-toggle-btn {
            background: var(--card-img-bg);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            font-size: 18px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .theme-toggle-btn:hover {
            transform: rotate(30deg) scale(1.1);
            border-color: var(--accent-blue);
        }

        .btn-header {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 10px 18px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-header:hover { border-color: var(--accent-blue); }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 600;
        }
        .alert.success { background: rgba(0, 230, 118, 0.15); border: 1px solid var(--accent-green); color: var(--accent-green); }
        .alert.error { background: rgba(239, 68, 68, 0.15); border: 1px solid var(--accent-red); color: var(--accent-red); }

        .admin-grid {
            display: grid;
            grid-template-columns: 440px 1fr;
            gap: 30px;
            align-items: start;
        }

        .card {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .card h2 {
            margin: 0 0 20px 0;
            font-size: 18px;
            border-left: 4px solid var(--accent-blue);
            padding-left: 10px;
        }

        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--input-text);
            box-sizing: border-box;
            font-family: inherit;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: var(--accent-blue);
            outline: none;
        }

        .color-image-row {
            display: grid;
            grid-template-columns: 1fr 1fr 40px 32px;
            gap: 6px;
            margin-bottom: 8px;
            align-items: center;
        }
        .spec-item-row {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
            align-items: center;
        }
        .spec-item-row input { flex: 1; }

        .btn-file-custom {
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid var(--accent-blue);
            color: var(--accent-blue);
            border-radius: 6px;
            height: 38px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s;
        }
        .btn-file-custom:hover { background: rgba(59, 130, 246, 0.3); }

        .btn-remove-row {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid var(--accent-red);
            color: var(--accent-red);
            border-radius: 6px;
            width: 32px;
            height: 38px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-add-color, .btn-add-spec {
            background: rgba(59, 130, 246, 0.15);
            border: 1px dashed var(--accent-blue);
            color: var(--accent-blue);
            width: 100%;
            padding: 8px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 15px;
        }
        .btn-add-color:hover, .btn-add-spec:hover { background: rgba(59, 130, 246, 0.3); }

        .btn-submit {
            background: var(--accent-green);
            color: #000;
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { color: var(--text-muted); font-size: 12px; text-transform: uppercase; }

        .price-input {
            width: 85px; padding: 6px; background: var(--input-bg);
            border: 1px solid var(--border-color); border-radius: 6px;
            color: var(--accent-green); font-weight: bold;
        }

        .btn-edit-full {
            background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.4);
            color: var(--accent-blue); padding: 6px 10px; border-radius: 6px;
            cursor: pointer; font-weight: bold; font-size: 12px;
        }
        .btn-delete {
            background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--accent-red); padding: 6px 10px; border-radius: 6px;
            text-decoration: none; font-size: 12px; font-weight: bold; cursor: pointer;
            transition: all 0.2s;
        }
        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.3);
            border-color: var(--accent-red);
        }

        .status-select {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--accent-green);
            font-weight: bold;
            padding: 6px 8px;
            border-radius: 6px;
            outline: none;
            cursor: pointer;
        }

        .order-row-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--card-img-bg);
            padding: 6px 10px;
            border-radius: 8px;
            margin-bottom: 4px;
            font-size: 13px;
        }

        .order-item-mini-img {
            width: 32px;
            height: 32px;
            object-fit: contain;
            border-radius: 4px;
        }

        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.65); display: none; justify-content: center; align-items: center;
            z-index: 9999; backdrop-filter: blur(8px);
        }
        .modal-overlay.show { display: flex; }

        .modal-content {
            background: var(--panel-bg); border: 1px solid var(--border-color);
            border-radius: 16px; width: 720px; max-width: 90%; max-height: 90vh;
            overflow-y: auto; padding: 25px; position: relative; color: var(--text-main);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;
        }
        .close-modal { background: none; border: none; color: var(--text-muted); font-size: 24px; cursor: pointer; }
        .modal-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* ПРОМОКОДЫ - СТИЛИ СТАТУСОВ */
        .promo-badge-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        .promo-active { background: rgba(0, 230, 118, 0.15); color: var(--accent-green); border: 1px solid rgba(0, 230, 118, 0.3); }
        .promo-inactive { background: rgba(239, 68, 68, 0.15); color: var(--accent-red); border: 1px solid rgba(239, 68, 68, 0.3); }
        .promo-expired { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
    </style>
</head>
<body>

<div class="admin-container">
    <div class="header">
        <div>
            <h1>
                <a href="index.php" class="logo-admin-link">Cyber<span>Phone</span></a> 
                <span style="font-size:22px; font-weight:normal; color:var(--text-muted); margin-left:8px;">Панель управления</span>
            </h1>
            <p style="color: var(--text-muted); margin: 5px 0 0 0; font-size: 14px;">Вы вошли как: <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong></p>
        </div>
        <div class="header-actions">
            <a href="admin.php?action=download_backup" class="btn-header" style="border-color:#00e676; color:#00e676;">📥 Скачать Backup</a>
            <button id="theme-toggle" class="theme-toggle-btn">🌙</button>
            <a href="index.php" class="btn-header">🏠 В каталог</a>
            <a href="logout.php" class="btn-header" style="border-color: var(--accent-red); color: var(--accent-red);">🚪 Выйти</a>
        </div>
    </div>

    <!-- АНАЛИТИКА И МИНИ ГРАФИК -->
    <div class="analytics-grid">
        <div class="stat-card">
            <div class="stat-card-title">💰 Выручка магазина</div>
            <div class="stat-card-val" style="color:#00e676;"><?= number_format($total_revenue, 0, '.', ' '); ?> грн.</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">📦 Всего заказов</div>
            <div class="stat-card-val"><?= count($orders); ?> шт.</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">👥 Клиентов</div>
            <div class="stat-card-val"><?= count($users); ?> чел.</div>
        </div>
        <div class="chart-box">
            <div class="stat-card-title">📈 Продажи за недели (7 дней)</div>
            <div class="chart-bars">
                <?php foreach ($sales_chart_data as $bar): ?>
                    <?php $pct = round(($bar['val'] / $max_chart_val) * 100); ?>
                    <div class="chart-bar-item" title="<?= $bar['date']; ?>: <?= number_format($bar['val'], 0, '', ' '); ?> грн.">
                        <div class="chart-bar-fill" style="height: <?= max($pct, 8); ?>%;"></div>
                        <div class="chart-bar-date"><?= $bar['date']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ПЕРЕКЛЮЧАТЕЛЬ ВКЛАДОК -->
    <div class="tabs-bar">
        <a href="admin.php?tab=catalog" class="tab-btn <?= $active_tab === 'catalog' ? 'active' : ''; ?>">
            📱 Каталог товаров <span class="tab-badge"><?= count($products); ?></span>
        </a>
        <a href="admin.php?tab=orders" class="tab-btn <?= $active_tab === 'orders' ? 'active' : ''; ?>">
            📑 Заказы клиентов <span class="tab-badge" style="background:#2563eb; color:#fff;"><?= count($orders); ?></span>
        </a>
        <a href="admin.php?tab=promos" class="tab-btn <?= $active_tab === 'promos' ? 'active' : ''; ?>">
            🎟️ Промокоды <span class="tab-badge" style="background:#f59e0b; color:#fff;"><?= count($promos); ?></span>
        </a>
        <a href="admin.php?tab=users" class="tab-btn <?= $active_tab === 'users' ? 'active' : ''; ?>">
            👥 Пользователи <span class="tab-badge" style="background:#8b5cf6; color:#fff;"><?= count($users); ?></span>
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert <?= $message_type; ?>"><?= $message; ?></div>
    <?php endif; ?>

    <!-- ВКЛАДКА 1: КАТАЛОГ ТОВАРОВ -->
    <?php if ($active_tab === 'catalog'): ?>
        <div class="admin-grid">
            <div class="card">
                <h2>➕ Добавить гаджет</h2>
                <form action="admin.php" method="POST">
                    <div class="form-group">
                        <label>Название модели *</label>
                        <input type="text" name="name" class="form-control" required placeholder="Xiaomi 17 Ultra">
                    </div>
                    <div class="form-group">
                        <label>Бренд *</label>
                        <input type="text" name="brand" class="form-control" required placeholder="xiaomi">
                    </div>
                    <div class="form-group">
                        <label>Категория</label>
                        <input type="text" name="category" class="form-control" value="android">
                    </div>
                    <div class="form-group">
                        <label>Базовая цена (грн) *</label>
                        <input type="number" name="price" step="0.01" class="form-control" required placeholder="62000">
                    </div>
                    <div class="form-group">
                        <label>Год выпуска</label>
                        <input type="number" name="release_year" class="form-control" value="2026">
                    </div>

                    <div class="form-group">
                        <label>Расцветки и файлы изображений</label>
                        <div id="add_colors_container"></div>
                        <button type="button" class="btn-add-color" onclick="addColorRow('add_colors_container')">+ Добавить цвет</button>
                    </div>

                    <div class="form-group">
                        <label>Главное изображение товара (image_url)</label>
                        <input type="text" name="fallback_image_url" class="form-control" placeholder="img/xiaomi17ultra_green.jpg">
                    </div>

                    <div class="form-group">
                        <label>Характеристики устройства</label>
                        <div id="add_specs_container"></div>
                        <button type="button" class="btn-add-spec" onclick="addSpecRow('add_specs_container')">+ Добавить характеристику</button>
                        <button type="button" class="btn-add-spec" style="margin-top:-8px; border-color:var(--accent-green); color:var(--accent-green); background:rgba(0,230,118,0.1);" onclick="addDefaultSpecs(document.getElementById('add_specs_container'))">⚡ Заполнить набор флагмана</button>
                    </div>

                    <div class="form-group">
                        <label>Варианты памяти</label>
                        <input type="text" name="storage_options" class="form-control" placeholder="256 ГБ, 512 ГБ, 1 ТБ">
                    </div>
                    <div class="form-group">
                        <label>Оперативная память (RAM)</label>
                        <input type="text" name="ram" class="form-control" placeholder="12 ГБ, 16 ГБ">
                    </div>
                    <div class="form-group">
                        <label>Описание</label>
                        <textarea name="description" rows="2" class="form-control"></textarea>
                    </div>
                    <button type="submit" name="add_product" class="btn-submit">Опубликовать на сайт</button>
                </form>
            </div>

            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h2 style="margin:0;">📦 Товары в каталоге (Всего: <?= count($products); ?>)</h2>
                    <input type="text" id="catalog-search" class="form-control" placeholder="🔍 Быстрый поиск..." style="width: 240px; margin:0;">
                </div>

                <?php if (empty($products)): ?>
                    <p style="color: var(--text-muted);">В базе пока нет товаров.</p>
                <?php else: ?>
                    <table id="products-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Бренд</th>
                                <th>Название</th>
                                <th>Цена (грн)</th>
                                <th>Действие</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $prod): ?>
                                <tr class="product-row" data-search="<?= mb_strtolower(htmlspecialchars($prod['name'] . ' ' . $prod['brand'])); ?>">
                                    <td style="color: var(--text-muted);">#<?= $prod['id']; ?></td>
                                    <td style="color: var(--text-muted);"><?= htmlspecialchars($prod['brand']); ?></td>
                                    <td><strong><?= htmlspecialchars($prod['name']); ?></strong></td>
                                    <td>
                                        <form action="admin.php" method="POST" style="display:flex; gap:4px;">
                                            <input type="hidden" name="product_id" value="<?= $prod['id']; ?>">
                                            <input type="number" name="new_price" value="<?= (int)$prod['price']; ?>" class="price-input" step="100" required>
                                            <button type="submit" name="update_price" style="background:rgba(0,230,118,0.15); border:1px solid var(--accent-green); color:var(--accent-green); border-radius:6px; cursor:pointer;">✔</button>
                                        </form>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:6px;">
                                            <button type="button" class="btn-edit-full" onclick='openEditModal(<?= json_encode($prod, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>✏️ Изменить</button>
                                            <a href="admin.php?delete_id=<?= $prod['id']; ?>" class="btn-delete" onclick="return confirm('Удалить <?= htmlspecialchars($prod['name']); ?>?');">Удалить</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ВКЛАДКА 2: УПРАВЛЕНИЕ ЗАКАЗАМИ -->
    <?php if ($active_tab === 'orders'): ?>
        <div class="card">
            <h2>📑 Управление заказами клиентов (Всего: <?= count($orders); ?>)</h2>
            <?php if (empty($orders)): ?>
                <p style="color: var(--text-muted);">Заказов пока нет.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>№ Заказа</th>
                            <th>Дата / Клиент</th>
                            <th>Доставка</th>
                            <th>Состав заказа</th>
                            <th>Сумма</th>
                            <th>Статус</th>
                            <th>Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $ord): ?>
                            <?php
                            $raw_json = $ord['product_list'] ?? '';
                            $items = json_decode($raw_json, true);
                            $is_json = is_array($items) && !empty($items);
                            ?>
                            <tr>
                                <td><strong>#<?= $ord['id']; ?></strong></td>
                                <td>
                                    <div style="font-size:12px; color:var(--text-muted);"><?= $ord['created_at'] ?? ''; ?></div>
                                    <strong><?= htmlspecialchars($ord['customer_name'] ?? 'Покупатель'); ?></strong><br>
                                    <small style="color:var(--text-muted);"><?= htmlspecialchars($ord['customer_phone'] ?? ''); ?></small>
                                </td>
                                <td style="font-size:13px;">
                                    📍 г. <?= htmlspecialchars($ord['delivery_city'] ?? ''); ?><br>
                                    <small style="color:var(--text-muted);">Отд. № <?= htmlspecialchars($ord['delivery_post'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <?php if ($is_json): ?>
                                        <?php foreach ($items as $it): ?>
                                            <?php if (empty($it['name'])) continue; ?>
                                            <div class="order-row-item">
                                                <img src="<?= htmlspecialchars(!empty($it['image']) ? $it['image'] : 'img/default.jpg'); ?>" class="order-item-mini-img" onerror="this.src='img/default.jpg';">
                                                <div>
                                                    <strong><?= htmlspecialchars($it['name']); ?></strong>
                                                    <div style="font-size:11px; color:var(--text-muted);">
                                                        <?= !empty($it['color']) ? 'Цвет: ' . htmlspecialchars($it['color']) . ' | ' : ''; ?>
                                                        <?= !empty($it['memory']) ? 'Память: ' . htmlspecialchars($it['memory']) : ''; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div style="font-size:12px; color:var(--text-muted); max-width:250px;">
                                            <?= htmlspecialchars($raw_json); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color:var(--accent-green); font-size:15px;"><?= number_format((float)$ord['total_price'], 0, '.', ' '); ?> грн.</strong>
                                </td>
                                <td>
                                    <form action="admin.php?tab=orders" method="POST" onsubmit="saveScrollPosition()">
                                        <input type="hidden" name="order_id" value="<?= $ord['id']; ?>">
                                        <select name="new_status" class="status-select" onchange="saveScrollPosition(); this.form.submit();">
                                            <option value="В обработке" <?= ($ord['status'] ?? '') === 'В обработке' ? 'selected' : ''; ?>>⏳ В обработке</option>
                                            <option value="На сборке" <?= ($ord['status'] ?? '') === 'На сборке' ? 'selected' : ''; ?>>📦 На сборке</option>
                                            <option value="Отправлен" <?= ($ord['status'] ?? '') === 'Отправлен' ? 'selected' : ''; ?>>🚚 Отправлен</option>
                                            <option value="Доставлен" <?= ($ord['status'] ?? '') === 'Доставлен' ? 'selected' : ''; ?>>✅ Доставлен</option>
                                            <option value="Отменен" <?= ($ord['status'] ?? '') === 'Отменен' ? 'selected' : ''; ?>>❌ Отменен</option>
                                        </select>
                                        <input type="hidden" name="update_order_status" value="1">
                                    </form>
                                </td>
                                <td>
                                    <button type="button" class="btn-delete" onclick="confirmDeleteOrder(<?= $ord['id']; ?>)">Удалить</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ВКЛАДКА 3: УПРАВЛЕНИЕ ПРОМОКОДАМИ В СТИЛЕ CMS SHOPIFY -->
    <?php if ($active_tab === 'promos'): ?>
        <div class="admin-grid">
            <div class="card">
                <h2>➕ Создать промокод</h2>
                <form action="admin.php?tab=promos" method="POST">
                    <div class="form-group">
                        <label>Код промокода *</label>
                        <input type="text" name="promo_code" class="form-control" required placeholder="CYBER5" style="text-transform: uppercase;">
                    </div>
                    <div class="form-group">
                        <label>Размер скидки (%) *</label>
                        <input type="number" name="discount_percent" class="form-control" required placeholder="5" min="1" max="100">
                    </div>
                    <div class="form-group">
                        <label>Действует до (необязательно)</label>
                        <input type="date" name="expires_at" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Лимит использований (0 = безлимит)</label>
                        <input type="number" name="max_uses" class="form-control" placeholder="100" min="0" value="0">
                    </div>
                    <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                        <input type="checkbox" name="is_active" id="is_active" value="1" checked style="width:18px; height:18px; cursor:pointer;">
                        <label for="is_active" style="margin:0; cursor:pointer; text-transform:none; font-size:14px;">Активен сразу</label>
                    </div>
                    <button type="submit" name="add_promo" class="btn-submit">Опубликовать промокод</button>
                </form>
            </div>

            <div class="card">
                <h2>🎟️ Список промокодов (Всего: <?= count($promos); ?>)</h2>
                <?php if (empty($promos)): ?>
                    <p style="color: var(--text-muted);">Промокодов пока нет.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Код</th>
                                <th>Скидка</th>
                                <th>Статус</th>
                                <th>Срок действия</th>
                                <th>Использований</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($promos as $p): ?>
                                <?php
                                $is_expired = !empty($p['expires_at']) && strtotime($p['expires_at']) < strtotime(date('Y-m-d'));
                                $is_limit = ((int)$p['max_uses'] > 0) && ((int)$p['used_count'] >= (int)$p['max_uses']);
                                ?>
                                <tr>
                                    <td><strong style="font-size:15px; letter-spacing:1px; color:var(--accent-blue);"><?= htmlspecialchars($p['code']); ?></strong></td>
                                    <td><span style="color:var(--accent-green); font-weight:bold; font-size:15px;"><?= (int)$p['discount_percent']; ?>%</span></td>
                                    <td>
                                        <?php if ($is_expired): ?>
                                            <span class="promo-badge-status promo-expired">🟡 Истёк</span>
                                        <?php elseif ($is_limit): ?>
                                            <span class="promo-badge-status promo-expired">🟡 Лимит</span>
                                        <?php else: ?>
                                            <a href="admin.php?tab=promos&toggle_promo_id=<?= $p['id']; ?>" class="promo-badge-status <?= $p['is_active'] ? 'promo-active' : 'promo-inactive'; ?>" title="Кликните для смены">
                                                <?= $p['is_active'] ? '🟢 Активен' : '🔴 Выключен'; ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:13px; color:var(--text-muted);">
                                        <?= !empty($p['expires_at']) ? date('d.m.Y', strtotime($p['expires_at'])) : '♾️ Бессрочно'; ?>
                                    </td>
                                    <td style="font-size:13px;">
                                        <strong><?= (int)$p['used_count']; ?></strong> 
                                        <span style="color:var(--text-muted);">/ <?= (int)$p['max_uses'] > 0 ? (int)$p['max_uses'] : '∞'; ?></span>
                                    </td>
                                    <td>
                                        <a href="admin.php?tab=promos&delete_promo_id=<?= $p['id']; ?>" class="btn-delete" onclick="return confirm('Удалить промокод <?= htmlspecialchars($p['code']); ?>?');">🗑️ Удалить</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ВКЛАДКА 4: ПОЛЬЗОВАТЕЛИ -->
    <?php if ($active_tab === 'users'): ?>
        <div class="card">
            <h2>👥 Зарегистрированные пользователи (Всего: <?= count($users); ?>)</h2>
            <?php if (empty($users)): ?>
                <p style="color: var(--text-muted);">Пользователей пока нет.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Логин</th>
                            <th>Email</th>
                            <th>Заказов сделано</th>
                            <th>Роль</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $usr): ?>
                            <tr>
                                <td style="color:var(--text-muted);">#<?= $usr['id']; ?></td>
                                <td><strong><?= htmlspecialchars($usr['username'] ?? 'Гость'); ?></strong></td>
                                <td style="color:var(--text-muted);"><?= htmlspecialchars($usr['email'] ?? '—'); ?></td>
                                <td>
                                    <span style="background:rgba(37,99,235,0.15); color:#3b82f6; border:1px solid rgba(37,99,235,0.3); padding:3px 10px; border-radius:12px; font-weight:bold; font-size:12px;">
                                        📦 <?= (int)$usr['orders_count']; ?> заказов
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($usr['is_admin'])): ?>
                                        <span style="color:#ef4444; font-weight:bold;">👑 Администратор</span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">👤 Покупатель</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Модальное окно редактирования товара -->
<div id="editModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0; color:var(--accent-blue);">✏️ Редактирование товара</h3>
            <button class="close-modal" onclick="closeEditModal()">×</button>
        </div>
        <form action="admin.php" method="POST">
            <input type="hidden" name="product_id" id="edit_id">
            
            <div class="form-group">
                <label>Название модели *</label>
                <input type="text" name="name" id="edit_name" class="form-control" required>
            </div>

            <div class="modal-grid-2">
                <div class="form-group">
                    <label>Бренд *</label>
                    <input type="text" name="brand" id="edit_brand" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Категория</label>
                    <input type="text" name="category" id="edit_category" class="form-control">
                </div>
            </div>

            <div class="modal-grid-2">
                <div class="form-group">
                    <label>Базовая цена (грн) *</label>
                    <input type="number" name="price" id="edit_price" step="0.01" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Год выпуска</label>
                    <input type="number" name="release_year" id="edit_release_year" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label>Расцветки и файлы изображений</label>
                <div id="modal_colors_container"></div>
                <button type="button" class="btn-add-color" onclick="addColorRow('modal_colors_container')">+ Добавить цвет</button>
            </div>

            <div class="form-group">
                <label>Главное изображение товара (image_url)</label>
                <input type="text" name="fallback_image_url" id="edit_image_url" class="form-control">
            </div>

            <div class="form-group">
                <label>Характеристики устройства</label>
                <div id="modal_specs_container"></div>
                <button type="button" class="btn-add-spec" onclick="addSpecRow('modal_specs_container')">+ Добавить характеристику</button>
                <button type="button" class="btn-add-spec" style="margin-top:-8px; border-color:var(--accent-green); color:var(--accent-green); background:rgba(0,230,118,0.1);" onclick="addDefaultSpecs(document.getElementById('modal_specs_container'))">⚡ Заполнить набор флагмана</button>
            </div>

            <div class="modal-grid-2">
                <div class="form-group">
                    <label>Варианты памяти</label>
                    <input type="text" name="storage_options" id="edit_storage_options" class="form-control">
                </div>
                <div class="form-group">
                    <label>Оперативная память (RAM)</label>
                    <input type="text" name="ram" id="edit_ram" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label>Описание</label>
                <textarea name="description" id="edit_description" rows="3" class="form-control"></textarea>
            </div>

            <button type="submit" name="edit_product_full" class="btn-submit">Сохранить все изменения</button>
        </form>
    </div>
</div>

<!-- Модалка подтверждения удаления заказа -->
<div id="deleteConfirmModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 420px; text-align: center; border-color: rgba(239, 68, 68, 0.4);">
        <div style="font-size: 48px; margin-bottom: 10px;">🗑️</div>
        <h3 style="margin: 0 0 10px 0; color: var(--text-main); font-size: 20px;">Подтверждение удаления</h3>
        <p style="color: var(--text-muted); margin-bottom: 25px; font-size: 15px;" id="deleteModalText">Вы действительно хотите удалить этот заказ?</p>
        
        <div style="display: flex; gap: 12px; justify-content: center;">
            <button type="button" class="btn-header" onclick="closeDeleteModal()" style="padding: 12px 24px; cursor: pointer;">Отмена</button>
            <a id="confirmDeleteBtn" href="#" class="btn-submit" onclick="saveScrollPosition()" style="background: var(--accent-red); color: #fff; text-decoration: none; padding: 12px 24px; width: auto; border-radius: 10px;">Да, удалить</a>
        </div>
    </div>
</div>

<script>
    const catalogSearchInput = document.getElementById('catalog-search');
    if (catalogSearchInput) {
        catalogSearchInput.addEventListener('input', function() {
            const text = this.value.toLowerCase().trim();
            document.querySelectorAll('.product-row').forEach(row => {
                const searchData = row.getAttribute('data-search');
                if (searchData.includes(text)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    function saveScrollPosition() {
        localStorage.setItem('admin_scroll_pos', window.scrollY || document.documentElement.scrollTop);
    }

    document.addEventListener("DOMContentLoaded", () => {
        const savedScroll = localStorage.getItem('admin_scroll_pos');
        if (savedScroll !== null) {
            window.scrollTo(0, parseInt(savedScroll, 10));
            localStorage.removeItem('admin_scroll_pos');
        }
    });

    const themeToggleBtn = document.getElementById('theme-toggle');
    if (localStorage.getItem('theme') === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
        themeToggleBtn.textContent = '☀️';
    }

    themeToggleBtn.addEventListener('click', () => {
        if (document.documentElement.getAttribute('data-theme') === 'light') {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('theme', 'dark');
            themeToggleBtn.textContent = '🌙';
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
            localStorage.setItem('theme', 'light');
            themeToggleBtn.textContent = '☀️';
        }
    });

    function confirmDeleteOrder(orderId) {
        document.getElementById('deleteModalText').textContent = `Вы действительно хотите удалить заказ #${orderId}?`;
        document.getElementById('confirmDeleteBtn').href = `admin.php?tab=orders&delete_order_id=${orderId}`;
        document.getElementById('deleteConfirmModal').classList.add('show');
    }

    function closeDeleteModal() {
        document.getElementById('deleteConfirmModal').classList.remove('show');
    }

    document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteModal();
    });

    function cleanJsPath(p) {
        if (!p) return '';
        return p.replace(/\\+/g, '/').replace(/\/+/g, '/');
    }

    function addColorRow(containerId, colorName = '', imagePath = '') {
        const container = document.getElementById(containerId);
        if (!container) return;
        const rowId = 'color_row_' + Math.random().toString(36).substr(2, 9);
        const row = document.createElement('div');
        row.className = 'color-image-row';
        
        const safeName = colorName.replace(/"/g, '&quot;');
        const safePath = cleanJsPath(imagePath).replace(/"/g, '&quot;');

        row.innerHTML = `
            <input type="text" name="color_names[]" class="form-control" value="${safeName}" placeholder="Название цвета (White)">
            <input type="text" name="color_images[]" id="img_input_${rowId}" class="form-control" value="${safePath}" placeholder="img/file.jpg">
            
            <input type="file" id="file_picker_${rowId}" accept="image/*" style="display:none;" onchange="handleFileSelect(this, 'img_input_${rowId}')">
            <label for="file_picker_${rowId}" class="btn-file-custom" title="Выбрать файл">📁</label>
            
            <button type="button" class="btn-remove-row" onclick="removeColorRow(this)">✕</button>
        `;
        container.appendChild(row);
    }

    function handleFileSelect(fileInput, targetInputId) {
        if (fileInput.files && fileInput.files[0]) {
            const fileName = fileInput.files[0].name;
            document.getElementById(targetInputId).value = 'img/' + fileName;
        }
    }

    function addSpecRow(containerId, specName = '', specValue = '') {
        const container = document.getElementById(containerId);
        if (!container) return;
        const row = document.createElement('div');
        row.className = 'spec-item-row';
        row.innerHTML = `
            <input type="text" name="spec_names[]" class="form-control" value="${specName.replace(/"/g, '&quot;')}" placeholder="Параметр">
            <input type="text" name="spec_values[]" class="form-control" value="${specValue.replace(/"/g, '&quot;')}" placeholder="Значение">
            <button type="button" class="btn-remove-row" onclick="removeColorRow(this)">✕</button>
        `;
        container.appendChild(row);
    }

    function removeColorRow(btn) {
        btn.closest('.color-image-row, .spec-item-row').remove();
    }

    function parseColorsSafe(raw) {
        if (!raw) return {};
        let clean = raw.replace(/\\\\+/g, '/').replace(/\\+/g, '/').replace(/&quot;/g, '"');
        
        try {
            let res = JSON.parse(clean);
            if (typeof res === 'string') res = JSON.parse(res);
            if (typeof res === 'object' && res !== null) return res;
        } catch(e) {}

        let map = {};
        let regex = /"([^"]+)":\s*"([^"]+)"/g;
        let match;
        while ((match = regex.exec(clean)) !== null) {
            map[match[1]] = cleanJsPath(match[2]);
        }
        return map;
    }

    function openEditModal(prod) {
        document.getElementById('edit_id').value = prod.id;
        document.getElementById('edit_name').value = prod.name || '';
        document.getElementById('edit_brand').value = prod.brand || '';
        document.getElementById('edit_category').value = prod.category || '';
        document.getElementById('edit_price').value = prod.price || '';
        document.getElementById('edit_release_year').value = prod.release_year || '';
        document.getElementById('edit_image_url').value = cleanJsPath(prod.image_url || '');
        document.getElementById('edit_storage_options').value = prod.storage_options || '';
        document.getElementById('edit_ram').value = prod.ram || '';
        document.getElementById('edit_description').value = prod.description || '';

        const colorsContainer = document.getElementById('modal_colors_container');
        colorsContainer.innerHTML = '';
        
        let parsedColors = parseColorsSafe(prod.colors);
        let colorKeys = Object.keys(parsedColors);

        if (colorKeys.length > 0) {
            colorKeys.forEach(colorName => { 
                addColorRow('modal_colors_container', colorName, parsedColors[colorName] || ''); 
            });
        } else {
            addColorRow('modal_colors_container');
        }

        const specsContainer = document.getElementById('modal_specs_container');
        specsContainer.innerHTML = '';
        
        let parsedSpecs = parseColorsSafe(prod.specs);
        let specKeys = Object.keys(parsedSpecs);

        if (specKeys.length > 0) {
            specKeys.forEach(sName => { 
                addSpecRow('modal_specs_container', sName, parsedSpecs[sName] || ''); 
            });
        } else {
            addDefaultSpecs(specsContainer);
        }

        document.getElementById('editModal').classList.add('show');
    }

    function addDefaultSpecs(container) {
        if (!container) return;
        const defaultSpecs = [
            ['Дисплей', 'LTPO OLED / Super Retina XDR (1-120 Гц)'],
            ['Процессор', 'Snapdragon 8 Elite / Apple A19 Pro'],
            ['Запись видео', '4K @ 60/120 fps, Log / Dolby Vision HDR'],
            ['Основная камера', '50 МП (OIS) + 50 МП Перископ + 50 МП Ширик'],
            ['Фронтальная камера', '32 МП с автофокусом'],
            ['Емкость батареи', '5500–6000 мАч'],
            ['Зарядка', 'Быстрая 90–120 Вт + Беспроводная Qi2 / MagSafe'],
            ['Защита корпуса', 'IP68 / IP69'],
            ['Материалы корпуса', 'Авиационный Титан / Закаленное стекло'],
            ['Связь и SIM', '5G, Wi-Fi 7, Bluetooth 5.4, e-SIM, NFC'],
            ['Безопасность', '3D Face ID / Ультразвуковой сканер под экраном']
        ];

        container.innerHTML = '';
        defaultSpecs.forEach(([name, val]) => {
            addSpecRow(container.id, name, val);
        });
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('show');
    }

    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    document.addEventListener("DOMContentLoaded", () => {
        const addColorsContainer = document.getElementById('add_colors_container');
        if (addColorsContainer && addColorsContainer.children.length === 0) {
            addColorRow('add_colors_container');
        }

        const addSpecsContainer = document.getElementById('add_specs_container');
        if (addSpecsContainer && addSpecsContainer.children.length === 0) {
            addDefaultSpecs(addSpecsContainer);
        }
    });
</script>
</body>
</html>