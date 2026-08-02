<?php
session_start();

try {
    $db = new PDO('mysql:host=localhost;dbname=tech_shop;charset=utf8', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// Загрузка данных авторизованного пользователя (для аватарки)
$is_logged_in = !empty($_SESSION['user_id']);
$user_avatar = 'img/default.jpg';

if ($is_logged_in) {
    $u_stmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
    $u_stmt->execute([$_SESSION['user_id']]);
    $u_data = $u_stmt->fetch();
    if (!empty($u_data['avatar']) && file_exists($u_data['avatar'])) {
        $user_avatar = $u_data['avatar'];
    }
}

function getCatalogColorSuffix($color_name) {
    $clean_color = mb_strtolower(trim($color_name));
    
    if (strpos($clean_color, 'liquid') !== false) return 'liquidsilver';
    if (strpos($clean_color, 'ultramarine') !== false || strpos($clean_color, 'ультрамарин') !== false) return 'ultramarine'; 
    if (strpos($clean_color, 'teal') !== false || strpos($clean_color, 'бирюз') !== false) return 'teal';        
    if (strpos($clean_color, 'ocean') !== false || strpos($clean_color, 'blue') !== false || strpos($clean_color, 'син') !== false) return 'blue';
    if (strpos($clean_color, 'purple') !== false || strpos($clean_color, 'фиолет') !== false) return 'purple';
    if (strpos($clean_color, 'green') !== false || strpos($clean_color, 'зелен') !== false) return 'green';
    if (strpos($clean_color, 'pink') !== false || strpos($clean_color, 'роз') !== false) return 'pink';
    if (strpos($clean_color, 'gold') !== false || strpos($clean_color, 'золот') !== false) return 'gold';
    if (strpos($clean_color, 'black') !== false || strpos($clean_color, 'черн') !== false) return 'black';
    if (strpos($clean_color, 'white') !== false || strpos($clean_color, 'бел') !== false) return 'white';
    if (strpos($clean_color, 'titanium') !== false || strpos($clean_color, 'титан') !== false) return 'gray';
    return 'gray';
}

function getExistingCatalogImage($model, $suffix, $db_url = '') {
    if (!empty($db_url) && file_exists($db_url)) return $db_url;
    
    $paths = [
        "img/{$model}_{$suffix}.jpg", "img/{$model}_{$suffix}.png",
        "img/{$model}_gray.jpg", "img/{$model}.jpg", "img/default.jpg"
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) return $path;
    }
    return "img/default.jpg";
}

// ЭНДПОИНТ ДЛЯ СМЕНЫ АКЦИОННОГО ТОВАРА
if (isset($_GET['ajax_get_new_deal'])) {
    $exclude_id = (int)($_GET['exclude_id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM products WHERE price >= 20000 AND id != ? ORDER BY RAND() LIMIT 1");
    $stmt->execute([$exclude_id]);
    $new_deal = $stmt->fetch();

    if (!$new_deal) {
        $stmt_fallback = $db->prepare("SELECT * FROM products WHERE id != ? ORDER BY RAND() LIMIT 1");
        $stmt_fallback->execute([$exclude_id]);
        $new_deal = $stmt_fallback->fetch();
    }

    if ($new_deal) {
        $model_clean = mb_strtolower(str_replace(' ', '', $new_deal['name'])); 
        $colors_list = !empty($new_deal['colors']) ? explode(', ', $new_deal['colors']) : ['gray'];
        $suffix = getCatalogColorSuffix($colors_list[0]);
        $new_deal['catalog_img'] = getExistingCatalogImage($model_clean, $suffix, $new_deal['image_url'] ?? '');
    }

    header('Content-Type: application/json');
    echo json_encode($new_deal ?: []);
    exit;
}

if (isset($_GET['ajax_quick_view'])) {
    $qv_id = (int)$_GET['ajax_quick_view'];
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$qv_id]);
    $qv_product = $stmt->fetch();

    if ($qv_product) {
        $model_clean = mb_strtolower(str_replace(' ', '', $qv_product['name'])); 
        $colors_list = !empty($qv_product['colors']) ? explode(', ', $qv_product['colors']) : ['gray'];
        $suffix = getCatalogColorSuffix($colors_list[0]);
        $qv_product['catalog_img'] = getExistingCatalogImage($model_clean, $suffix, $qv_product['image_url'] ?? '');
    }

    header('Content-Type: application/json');
    echo json_encode($qv_product ?: []);
    exit;
}

if (isset($_GET['ajax_search'])) {
    $query = trim($_GET['ajax_search']);
    if (mb_strlen($query) >= 2) {
        $stmt = $db->prepare("SELECT id, name, price, brand, image_url, colors FROM products WHERE name LIKE ? OR brand LIKE ? LIMIT 5");
        $stmt->execute(['%' . $query . '%', '%' . $query . '%']);
        $suggestions = $stmt->fetchAll();
        
        foreach ($suggestions as &$s) {
            $model_clean = mb_strtolower(str_replace(' ', '', $s['name'])); 
            $colors_list = !empty($s['colors']) ? explode(', ', $s['colors']) : ['gray'];
            $suffix = getCatalogColorSuffix($colors_list[0]);
            $s['catalog_img'] = getExistingCatalogImage($model_clean, $suffix, $s['image_url'] ?? '');
        }
        unset($s);

        header('Content-Type: application/json');
        echo json_encode($suggestions);
    } else {
        echo json_encode([]);
    }
    exit;
}

// ЗАФИКСИРОВАННЫЙ ТОВАР ДНЯ
$seed = (int)date('Ymd');
$global_deal_stmt = $db->query("SELECT * FROM products WHERE price >= 25000 ORDER BY RAND($seed) LIMIT 1");
$global_deal_product = $global_deal_stmt->fetch();

if (!$global_deal_product) {
    $fallback_stmt = $db->query("SELECT * FROM products ORDER BY RAND($seed) LIMIT 1");
    $global_deal_product = $fallback_stmt->fetch();
}

if ($global_deal_product) {
    $model_clean_deal = mb_strtolower(str_replace(' ', '', $global_deal_product['name'])); 
    $colors_list_deal = !empty($global_deal_product['colors']) ? explode(', ', $global_deal_product['colors']) : ['gray'];
    $suffix_deal = getCatalogColorSuffix($colors_list_deal[0]);
    $global_deal_product['catalog_img'] = getExistingCatalogImage($model_clean_deal, $suffix_deal, $global_deal_product['image_url'] ?? '');
}

// Получаем список брендов
$brands_stmt = $db->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' AND LOWER(brand) != 'apple' ORDER BY brand ASC");
$all_brands = $brands_stmt->fetchAll(PDO::FETCH_COLUMN);

$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$brand = isset($_GET['brand']) ? trim($_GET['brand']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : ''; 
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

if (!empty($category)) {
    $sql .= " AND category = :category";
    $params[':category'] = $category;
}

if (!empty($brand)) {
    $sql .= " AND LOWER(brand) = :brand";
    $params[':brand'] = mb_strtolower($brand);
}

if (!empty($search)) {
    $sql .= " AND (name LIKE :search OR brand LIKE :search OR description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($sort === 'price_asc') {
    $sql .= " ORDER BY price ASC";
} elseif ($sort === 'price_desc') {
    $sql .= " ORDER BY price DESC";
} else {
    $sql .= " ORDER BY release_year DESC";
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$cart_count = 0;
if (!empty($_SESSION['cart_details'])) {
    foreach ($_SESSION['cart_details'] as $c_item) {
        $cart_count += ($c_item['quantity'] ?? 1);
    }
} elseif (!empty($_SESSION['cart'])) {
    $cart_count = array_sum($_SESSION['cart']);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberPhone — Флагманские Смартфоны</title>
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
            --border-color: rgba(255, 255, 255, 0.08);
            --text-main: #ffffff;
            --text-muted: #8a99ad;
            --card-img-bg: #1a2234;
            --radial-gradient-1: radial-gradient(circle at 50% 30%, rgba(37, 99, 235, 0.12) 0%, transparent 50%);
            --shadow: rgba(0, 0, 0, 0.5);
            --accent-green: #00e676;
            --accent-blue: #2563eb;
            --accent-red: #ef4444;
        }

        [data-theme="light"] {
            --bg-color: #f3f4f6;
            --panel-bg: #ffffff;
            --border-color: rgba(0, 0, 0, 0.08);
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --card-img-bg: #f9fafb;
            --radial-gradient-1: radial-gradient(circle at 50% 30%, rgba(37, 99, 235, 0.05) 0%, transparent 60%);
            --shadow: rgba(0, 0, 0, 0.06);
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            transition: background-color 0.3s, color 0.3s;
            background-image: var(--radial-gradient-1);
        }

        header {
            background-color: var(--panel-bg);
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.3s, border-color 0.3s;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* НАВБАР КНОПКИ */
        .nav-buttons { 
            display: flex; 
            gap: 10px; 
            align-items: center; 
        }

        .nav-link-btn {
            background-color: var(--card-img-bg);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 38px;
            box-sizing: border-box;
            text-decoration: none;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            color: var(--text-main) !important;
            cursor: pointer;
        }

        /* ЭФФЕКТ ПРИБЛИЖЕНИЯ ПРИ НАВЕДЕНИИ */
        .nav-link-btn:hover {
            border-color: rgba(37, 99, 235, 0.4);
            background-color: rgba(37, 99, 235, 0.15);
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.25);
            transform: scale(1.06);
        }

        /* Модификатор кнопки выхода */
        .nav-link-btn.btn-logout {
            border-color: rgba(239, 68, 68, 0.25);
            color: #ef4444 !important;
        }

        .nav-link-btn.btn-logout:hover {
            background-color: rgba(239, 68, 68, 0.15);
            border-color: var(--accent-red);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.25);
            transform: scale(1.06);
        }

        /* Модификатор кнопки профиля */
        .profile-nav-btn {
            padding: 4px 16px 4px 6px;
            border-color: rgba(37, 99, 235, 0.3);
        }

        .header-avatar {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--accent-blue);
            background: var(--panel-bg);
        }

        .theme-toggle-btn {
            background: var(--card-img-bg);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            font-size: 16px;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.25s;
            box-sizing: border-box;
        }

        .theme-toggle-btn:hover {
            border-color: rgba(255, 255, 255, 0.25);
            transform: scale(1.1);
        }

        .badge {
            background-color: #ef4444;
            color: white;
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 10px;
            font-weight: bold;
        }

        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

        .deal-banner {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.2) 0%, rgba(0, 230, 118, 0.15) 100%);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 25px 35px;
            margin-bottom: 35px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            box-shadow: 0 10px 30px var(--shadow);
            position: relative;
            overflow: hidden;
            transition: background 0.3s, border-color 0.3s;
        }

        .deal-left-box { display: flex; align-items: center; gap: 25px; }

        .deal-img-box {
            width: 110px; height: 110px;
            background-color: var(--card-img-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        .deal-img-box img { max-width: 100%; max-height: 100%; object-fit: contain; }

        .deal-badge {
            position: absolute; top: 15px; left: 15px;
            background: #ef4444; color: white;
            padding: 4px 12px; border-radius: 20px;
            font-size: 12px; font-weight: bold;
            text-transform: uppercase; letter-spacing: 1px;
        }

        .deal-info h2 { margin: 10px 0 5px 0; font-size: 26px; }

        .deal-price-wrapper { display: flex; align-items: baseline; gap: 15px; margin-top: 8px; }
        .deal-discount-price { font-size: 26px; font-weight: 800; color: #00e676; }
        .deal-old-price { text-decoration: line-through; color: var(--text-muted); font-size: 16px; font-weight: 600; opacity: 0.85; }

        .timer-box { display: flex; gap: 12px; margin-top: 15px; }

        .timer-unit {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            padding: 8px 12px; border-radius: 10px;
            text-align: center; min-width: 50px;
            transition: background-color 0.3s, border-color 0.3s;
        }

        .timer-num { font-size: 20px; font-weight: 800; color: #00e676; }
        .timer-label { font-size: 10px; color: var(--text-muted); text-transform: uppercase; }

        .search-container { max-width: 600px; margin: 0 auto 30px auto; position: relative; }
        .search-form { display: flex; gap: 10px; }

        .search-input {
            flex: 1;
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 12px 20px; border-radius: 25px;
            font-size: 16px; outline: none;
            transition: background-color 0.3s, color 0.3s, border-color 0.3s;
        }

        .search-btn {
            background-color: #2563eb; color: #ffffff;
            border: none; padding: 12px 25px; border-radius: 25px;
            font-weight: 600; cursor: pointer;
            transition: transform 0.2s;
        }
        .search-btn:hover { transform: scale(1.05); }

        .search-suggestions {
            position: absolute; top: 100%; left: 0; right: 0;
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px; margin-top: 8px;
            max-height: 350px; overflow-y: auto; z-index: 999;
            box-shadow: 0 10px 30px var(--shadow);
            display: none;
        }

        .suggestion-item {
            padding: 10px 15px; color: var(--text-main);
            text-decoration: none; display: flex; align-items: center; gap: 15px;
            border-bottom: 1px solid var(--border-color); transition: background 0.2s;
        }

        .suggestion-item:hover { background-color: var(--card-img-bg); }

        .suggestion-img-box {
            width: 42px; height: 42px; background-color: var(--card-img-bg);
            border: 1px solid var(--border-color); border-radius: 8px;
            padding: 4px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }

        .suggestion-img-box img { max-width: 100%; max-height: 100%; object-fit: contain; }

        .controls-bar { display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px; }
        .controls-top-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .filter-group { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

        .filter-btn {
            background-color: var(--panel-bg); color: var(--text-main);
            border: 1px solid var(--border-color); padding: 8px 16px;
            border-radius: 20px; cursor: pointer; text-decoration: none;
            font-weight: 500; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 8px; font-size: 14px;
        }

        .filter-btn img { width: 24px; height: 24px; object-fit: contain; }
        .filter-btn:hover { transform: scale(1.05); background-color: #2563eb; color: #ffffff; border-color: #2563eb; }
        .filter-btn.active { background-color: #2563eb; color: #ffffff; border-color: #2563eb; }

        .brand-filter-row {
            display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
            padding-top: 12px; border-top: 1px dashed var(--border-color);
        }

        .brand-logo-btn {
            background-color: var(--panel-bg); color: var(--text-main);
            border: 1px solid var(--border-color); min-width: 90px; height: 50px;
            padding: 0 14px; border-radius: 16px; font-size: 14px; font-weight: 600;
            text-decoration: none; display: inline-flex; align-items: center; justify-content: center;
            gap: 10px; box-sizing: border-box; transition: all 0.2s;
        }

        .brand-logo-btn img { width: 32px; height: 32px; object-fit: contain; }
        .brand-logo-btn:hover { transform: scale(1.05); background-color: #3b82f6; color: #ffffff; border-color: #3b82f6; box-shadow: 0 0 12px rgba(59, 130, 246, 0.3); }
        .brand-logo-btn.active { background: #2563eb; border-color: #2563eb; box-shadow: 0 10px 20px rgba(37, 99, 235, 0.25); color: #ffffff; }

        .sort-select {
            background-color: var(--panel-bg); color: var(--text-main);
            border: 1px solid var(--border-color); padding: 10px 15px;
            border-radius: 20px; outline: none; cursor: pointer; font-weight: 500;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .product-card {
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: transform 0.2s, border-color 0.2s, background-color 0.3s;
        }

        .product-card:hover { transform: translateY(-4px); border-color: #3b82f6; }

        .card-promo-badge {
            display: none;
            position: absolute;
            top: 15px; left: 60px;
            background: linear-gradient(135deg, #ef4444 0%, #ff1744 100%);
            color: #fff;
            font-size: 11px; font-weight: 800;
            padding: 4px 10px; border-radius: 12px;
            text-transform: uppercase;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4);
            z-index: 10;
        }

        .card-top-btns {
            position: absolute;
            top: 15px; left: 15px; right: 15px;
            display: flex; justify-content: space-between; align-items: center;
            z-index: 10;
        }

        .icon-btn {
            background: var(--card-img-bg);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            border-radius: 50%;
            width: 36px; height: 36px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
            transition: all 0.2s;
        }
        .icon-btn:hover { transform: scale(1.15); }

        .icon-btn.liked { color: #ef4444 !important; border-color: #ef4444; background: rgba(239, 68, 68, 0.1); }

        .product-image-link {
            text-decoration: none;
            display: block;
            margin: 25px 0 15px 0;
            background-color: var(--card-img-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            height: 200px;
            padding: 15px;
            display: flex; align-items: center; justify-content: center;
            transition: background-color 0.3s, border-color 0.3s;
        }

        .product-image-link img { max-width: 100%; max-height: 100%; object-fit: contain; }

        .product-title-link { color: var(--text-main); text-decoration: none; font-size: 20px; font-weight: 600; }

        .card-description {
            color: var(--text-muted);
            font-size: 13px; line-height: 1.4;
            margin: 8px 0 4px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            transition: all 0.3s;
        }

        .card-description.expanded { display: block; overflow: visible; }

        .toggle-desc-btn {
            background: none; border: none; color: #2563eb;
            font-size: 12px; font-weight: 600; cursor: pointer;
            padding: 0; margin-bottom: 10px; text-align: left;
        }

        .toggle-desc-btn:hover { text-decoration: underline; }

        .product-footer {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 10px; padding-top: 15px; border-top: 1px solid var(--border-color);
        }

        .product-price { font-size: 20px; font-weight: 800; color: #00e676; }
        .product-old-price { display: none; font-size: 13px; text-decoration: line-through; color: var(--text-muted); font-weight: 600; margin-top: 2px; }

        .buy-btn {
            background-color: #00e676; color: #000; border: none;
            padding: 10px 18px; border-radius: 8px; font-weight: bold; cursor: pointer;
            transition: all 0.2s;
        }

        .buy-btn:hover { background-color: #00c853; transform: scale(1.05); }

        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8); display: none; align-items: center; justify-content: center;
            z-index: 10000; backdrop-filter: blur(8px);
        }

        .modal-content {
            background: var(--panel-bg); 
            border: 1px solid var(--border-color);
            border-radius: 20px; 
            max-width: 720px; width: 90%; padding: 30px;
            position: relative; box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }

        .modal-close {
            position: absolute; top: 15px; right: 20px; font-size: 24px;
            cursor: pointer; color: var(--text-muted); transition: color 0.2s;
        }

        .modal-close:hover { color: #ef4444; }

        .qv-grid { display: flex; gap: 25px; align-items: center; }

        .qv-image-box {
            flex: 0 0 220px; height: 240px; background-color: var(--card-img-bg);
            border: 1px solid var(--border-color); border-radius: 14px; padding: 15px;
            display: flex; align-items: center; justify-content: center; box-sizing: border-box;
        }

        .qv-image-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .qv-info-box { flex: 1; }

        .history-section { margin-top: 60px; border-top: 1px solid var(--border-color); padding-top: 30px; }
        .history-title { font-size: 20px; font-weight: 700; margin-bottom: 20px; }

        .floating-cart {
            position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px;
            background-color: #00e676; border-radius: 50%; display: flex;
            align-items: center; justify-content: center; text-decoration: none;
            z-index: 9999; box-shadow: 0 8px 25px rgba(0, 230, 118, 0.4);
            transition: transform 0.2s;
        }
        .floating-cart:hover { transform: scale(1.1); }

        .floating-badge {
            position: absolute; top: -3px; right: -3px; background-color: #ef4444;
            color: #ffffff; font-size: 11px; font-weight: 800; min-width: 20px; height: 20px;
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
        }

        .toast-notification {
            position: fixed; bottom: 30px; right: -400px; background-color: var(--panel-bg);
            border: 1px solid #00e676; color: var(--text-main); padding: 15px 25px;
            border-radius: 12px; z-index: 10000; transition: right 0.4s;
        }

        .toast-notification.show { right: 30px; }
    </style>
</head>
<body>

<div id="toast" class="toast-notification"><span id="toast-message">Товар добавлен!</span></div>

<a href="cart.php" class="floating-cart" id="floating-cart">
    <span style="font-size: 24px;">🛒</span>
    <span id="floating-cart-count" class="floating-badge"><?php echo $cart_count; ?></span>
</a>

<header>
    <div class="header-content">
        <div>
            <h1 style="margin:0; font-size: 28px;">
                <a href="index.php" style="text-decoration:none; color:inherit;">Cyber<span style="color: #2563eb;">Phone</span></a>
            </h1>
        </div>
        <div class="nav-buttons">
            <button id="theme-toggle" class="theme-toggle-btn" title="Сменить тему">🌙</button>
            <a href="wishlist.php" class="nav-link-btn">❤️ Избранное <span id="wishlist-count" class="badge">0</span></a>
            <a href="cart.php" class="nav-link-btn" style="border-color: rgba(0, 230, 118, 0.3);">🛒 Корзина <span id="cart-count" class="badge" style="background-color: #00e676; color: #000;"><?php echo $cart_count; ?></span></a>
            
            <?php if (!empty($_SESSION['is_admin'])): ?>
                <a href="admin.php" class="nav-link-btn" style="border-color: rgba(59, 130, 246, 0.3);">⚙️ Админка</a>
            <?php endif; ?>

            <!-- ПРОВЕРКА АВТОРИЗАЦИИ ПОЛЬЗОВАТЕЛЯ -->
            <?php if ($is_logged_in): ?>
                <a href="profile.php" class="nav-link-btn profile-nav-btn">
                    <img src="<?= htmlspecialchars($user_avatar); ?>" alt="Аватар" class="header-avatar" onerror="this.src='img/default.jpg';">
                    <span>Кабинет</span>
                </a>
                <a href="logout.php" class="nav-link-btn btn-logout" title="Выйти из аккаунта">🚪 Выход</a>
            <?php else: ?>
                <a href="auth.php" class="nav-link-btn" style="border-color: #00e676; background: rgba(0, 230, 118, 0.15); color: #00e676 !important;">🔑 Войти</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="container">

    <!-- БАННЕР ТОВАР ДНЯ -->
    <?php if (!empty($global_deal_product)): ?>
        <div class="deal-banner" id="deal-banner">
            <div class="deal-badge">🔥 Скидка Дня</div>
            
            <div class="deal-left-box">
                <div class="deal-img-box">
                    <img id="deal-img" src="<?= htmlspecialchars($global_deal_product['catalog_img']); ?>" alt="<?= htmlspecialchars($global_deal_product['name']); ?>" onerror="this.src='img/default.jpg';">
                </div>
                <div class="deal-info">
                    <h2 id="deal-title"><?= htmlspecialchars($global_deal_product['name']); ?></h2>
                    <div class="deal-price-wrapper">
                        <span class="deal-discount-price"><span id="deal-price-discount"><?= number_format(round($global_deal_product['price'] * 0.93), 0, '', ' '); ?></span> грн.</span>
                        <span class="deal-old-price" id="deal-price-old"><?= number_format($global_deal_product['price'], 0, '', ' '); ?> грн.</span>
                    </div>
                    <div class="timer-box">
                        <div class="timer-unit"><div class="timer-num" id="t-hours">00</div><div class="timer-label">Часов</div></div>
                        <div class="timer-unit"><div class="timer-num" id="t-mins">00</div><div class="timer-label">Минут</div></div>
                        <div class="timer-unit"><div class="timer-num" id="t-secs">00</div><div class="timer-label">Секунд</div></div>
                    </div>
                </div>
            </div>

            <div>
                <a id="deal-link" href="product.php?id=<?= $global_deal_product['id']; ?>&is_deal=1" class="buy-btn" style="padding: 15px 30px; font-size: 16px; text-decoration: none; display: inline-block;">Успеть купить ⚡</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="search-container">
        <form action="index.php" method="GET" class="search-form" autocomplete="off">
            <input type="text" id="search-input" name="search" class="search-input" 
                   placeholder="Начните писать название смартфона..." 
                   value="<?= htmlspecialchars($search); ?>">
            <button type="submit" class="search-btn">Найти</button>
        </form>
        <div id="search-suggestions" class="search-suggestions"></div>
    </div>

    <div class="controls-bar">
        <div class="controls-top-row">
            <div class="filter-group">
                <a href="index.php?sort=<?= $sort; ?>" class="filter-btn <?= (empty($category) && empty($brand)) ? 'active' : ''; ?>">🌐 Все</a>
                
                <a href="index.php?category=iphone&sort=<?= $sort; ?>" class="filter-btn <?= $category === 'iphone' ? 'active' : ''; ?>">
                    <?php 
                    $iphone_img = file_exists('img/iphone.jpg') ? 'img/iphone.jpg' : (file_exists('img/iphone.png') ? 'img/iphone.png' : '');
                    if (!empty($iphone_img)): ?>
                        <img src="<?= $iphone_img; ?>" alt="iPhone" onerror="this.style.display='none';">
                    <?php endif; ?>
                    iPhone
                </a>
                
                <a href="index.php?category=android&sort=<?= $sort; ?>" class="filter-btn <?= $category === 'android' ? 'active' : ''; ?>">
                    <?php 
                    $android_img = file_exists('img/android.jpg') ? 'img/android.jpg' : (file_exists('img/android.png') ? 'img/android.png' : '');
                    if (!empty($android_img)): ?>
                        <img src="<?= $android_img; ?>" alt="Android" onerror="this.style.display='none';">
                    <?php endif; ?>
                    Android
                </a>
            </div>

            <div>
                <select class="sort-select" onchange="sessionStorage.setItem('scrollPosition', window.scrollY); location = this.value;">
                    <option value="index.php?category=<?= $category; ?>&brand=<?= urlencode($brand); ?>&sort=newest" <?= $sort === 'newest' ? 'selected' : ''; ?>>🕒 Сначала новые</option>
                    <option value="index.php?category=<?= $category; ?>&brand=<?= urlencode($brand); ?>&sort=price_asc" <?= $sort === 'price_asc' ? 'selected' : ''; ?>>💰 Сначала дешевле</option>
                    <option value="index.php?category=<?= $category; ?>&brand=<?= urlencode($brand); ?>&sort=price_desc" <?= $sort === 'price_desc' ? 'selected' : ''; ?>>💎 Сначала дороже</option>
                </select>
            </div>
        </div>

        <?php if ($category !== 'iphone'): ?>
            <div class="brand-filter-row">
                <?php foreach ($all_brands as $b_name): ?>
                    <?php
                    $clean_b = htmlspecialchars($b_name);
                    $is_active = (mb_strtolower($brand) === mb_strtolower($b_name));
                    
                    $logo_filename_jpg = 'img/' . mb_strtolower(preg_replace('/[^a-z0-9]/', '', $b_name)) . '.jpg';
                    $logo_filename_png = 'img/' . mb_strtolower(preg_replace('/[^a-z0-9]/', '', $b_name)) . '.png';
                    $logo_src = file_exists($logo_filename_jpg) ? $logo_filename_jpg : (file_exists($logo_filename_png) ? $logo_filename_png : '');
                    ?>
                    <a href="index.php?category=android&brand=<?= urlencode($b_name); ?>&sort=<?= $sort; ?>" 
                       class="brand-logo-btn <?= $is_active ? 'active' : ''; ?>">
                        <?php if (!empty($logo_src)): ?>
                            <img src="<?= $logo_src; ?>" alt="<?= $clean_b; ?>" onerror="this.style.display='none';">
                        <?php endif; ?>
                        <span><?= ucfirst($clean_b); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="products-grid">
        <?php foreach ($products as $product): ?>
            <?php
            $model_clean = mb_strtolower(str_replace(' ', '', $product['name'])); 
            $colors_list = !empty($product['colors']) ? explode(', ', $product['colors']) : ['gray'];
            $suffix = getCatalogColorSuffix($colors_list[0]);
            $catalog_img = getExistingCatalogImage($model_clean, $suffix, $product['image_url'] ?? '');
            ?>
            <div class="product-card" data-id="<?= $product['id']; ?>" data-price="<?= $product['price']; ?>">
                <div class="card-top-btns">
                    <button class="icon-btn" onclick="openQuickView(<?= $product['id']; ?>)" title="Быстрый просмотр">👁️</button>
                    <button class="icon-btn wishlist-btn" onclick="toggleWishlist(<?= $product['id']; ?>, this)" title="В избранное">❤</button>
                </div>

                <div class="card-promo-badge" id="badge-<?= $product['id']; ?>">🔥 Скидка дня</div>

                <a href="product.php?id=<?= $product['id']; ?>" class="product-image-link" onclick="saveToHistory(<?= $product['id']; ?>, '<?= htmlspecialchars($product['name']); ?>', '<?= htmlspecialchars($catalog_img); ?>')">
                    <img src="<?= htmlspecialchars($catalog_img); ?>" alt="<?= htmlspecialchars($product['name']); ?>" onerror="this.src='img/default.jpg';">
                </a>

                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;"><?= htmlspecialchars($product['brand']); ?></div>
                <h3 style="margin: 5px 0 0 0;">
                    <a href="product.php?id=<?= $product['id']; ?>" class="product-title-link"><?= htmlspecialchars($product['name']); ?></a>
                </h3>

                <div class="card-description" id="desc-<?= $product['id']; ?>">
                    <?= htmlspecialchars($product['description'] ?? ''); ?>
                </div>
                <button class="toggle-desc-btn" onclick="toggleDescription(<?= $product['id']; ?>, this)">Подробнее ▼</button>

                <div class="product-footer">
                    <div>
                        <div class="product-price" id="price-val-<?= $product['id']; ?>"><?= number_format($product['price'], 0, '', ' '); ?> грн.</div>
                        <div class="product-old-price" id="price-old-<?= $product['id']; ?>"></div>
                    </div>
                    <button class="buy-btn" onclick="addToCart(<?= $product['id']; ?>)">Купить</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="history-section" id="history-section" style="display: none;">
        <div class="history-title">🕒 Вы недавно смотрели</div>
        <div class="products-grid" id="history-grid"></div>
    </div>
</div>

<div class="modal-overlay" id="quick-view-modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeQuickView()">✕</span>
        <div id="modal-body">Загрузка...</div>
    </div>
</div>

<script>
    document.querySelectorAll('.brand-logo-btn, .filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            sessionStorage.setItem('scrollPosition', window.scrollY);
        });
    });

    const savedPosition = sessionStorage.getItem('scrollPosition');
    if (savedPosition !== null) {
        window.scrollTo(0, parseInt(savedPosition));
        sessionStorage.removeItem('scrollPosition');
    }

    const themeToggleBtn = document.getElementById('theme-toggle');
    if (localStorage.getItem('theme') === 'light') { themeToggleBtn.textContent = '☀️'; } else { themeToggleBtn.textContent = '🌙'; }

    themeToggleBtn.addEventListener('click', () => {
        if (document.documentElement.getAttribute('data-theme') === 'light') {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
            themeToggleBtn.textContent = '🌙';
        } else {
            document.documentElement.setAttribute('data-theme', 'light');
            localStorage.setItem('theme', 'light');
            themeToggleBtn.textContent = '☀️';
        }
    });

    function updateWishlistUI() {
        const wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];
        document.getElementById('wishlist-count').textContent = wishlist.length;

        document.querySelectorAll('.product-card').forEach(card => {
            const id = parseInt(card.getAttribute('data-id'));
            const btn = card.querySelector('.wishlist-btn');
            if (btn) {
                if (wishlist.includes(id)) { btn.classList.add('liked'); } else { btn.classList.remove('liked'); }
            }
        });
    }

    function toggleWishlist(productId, btn) {
        let wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];
        productId = parseInt(productId);

        if (wishlist.includes(productId)) {
            wishlist = wishlist.filter(id => id !== productId);
            btn.classList.remove('liked');
            showToast('💔 Удалено из избранного');
        } else {
            wishlist.push(productId);
            btn.classList.add('liked');
            showToast('❤️ Добавлено в избранное');
        }

        localStorage.setItem('wishlist', JSON.stringify(wishlist));
        document.getElementById('wishlist-count').textContent = wishlist.length;
    }

    document.addEventListener("DOMContentLoaded", () => {
        updateWishlistUI();
    });

    function toggleDescription(id, btn) {
        const desc = document.getElementById('desc-' + id);
        if (desc.classList.contains('expanded')) {
            desc.classList.remove('expanded');
            btn.textContent = 'Подробнее ▼';
        } else {
            desc.classList.add('expanded');
            btn.textContent = 'Свернуть ▲';
        }
    }

    let globalDealProduct = <?= json_encode($global_deal_product); ?>;

    function applyDealProductToUI(prod) {
        if (!prod) return;
        globalDealProduct = prod;

        localStorage.setItem('deal_product_id', prod.id);

        document.getElementById('deal-title').textContent = prod.name;
        document.getElementById('deal-link').href = `product.php?id=${prod.id}&is_deal=1`;
        if (document.getElementById('deal-img')) {
            document.getElementById('deal-img').src = prod.catalog_img || 'img/default.jpg';
        }

        document.querySelectorAll('.card-promo-badge').forEach(b => b.style.display = 'none');
        document.querySelectorAll('.product-old-price').forEach(p => p.style.display = 'none');

        const oldPrice = Number(prod.price);
        const discountPrice = Math.round(oldPrice * 0.93);

        document.getElementById('deal-price-discount').textContent = discountPrice.toLocaleString('ru-RU');
        document.getElementById('deal-price-old').textContent = oldPrice.toLocaleString('ru-RU') + ' грн.';

        const cardBadge = document.getElementById(`badge-${prod.id}`);
        const priceVal = document.getElementById(`price-val-${prod.id}`);
        const priceOld = document.getElementById(`price-old-${prod.id}`);

        if (cardBadge) cardBadge.style.display = 'block';
        if (priceVal) priceVal.textContent = discountPrice.toLocaleString('ru-RU') + ' грн.';
        if (priceOld) {
            priceOld.textContent = oldPrice.toLocaleString('ru-RU') + ' грн.';
            priceOld.style.display = 'block';
        }
    }

    function switchDealProduct() {
        const currentDealId = globalDealProduct ? globalDealProduct.id : 0;
        fetch(`index.php?ajax_get_new_deal=1&exclude_id=${currentDealId}`)
            .then(res => res.json())
            .then(newProd => {
                if (newProd && newProd.id) {
                    applyDealProductToUI(newProd);
                }
            });
    }

    function initRealTimer() {
        if (!globalDealProduct) return;

        let targetTime = localStorage.getItem('deal_end_time');

        if (!targetTime || Date.now() >= parseInt(targetTime)) {
            const durationMs = 12 * 60 * 60 * 1000;
            targetTime = Date.now() + durationMs;
            localStorage.setItem('deal_end_time', targetTime);
        }

        applyDealProductToUI(globalDealProduct);

        function updateCountdown() {
            const now = Date.now();
            const diff = parseInt(targetTime) - now;

            if (diff <= 0) {
                localStorage.removeItem('deal_end_time');
                switchDealProduct();
                initRealTimer();
                return;
            }

            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            document.getElementById('t-hours').textContent = String(hours).padStart(2, '0');
            document.getElementById('t-mins').textContent = String(minutes).padStart(2, '0');
            document.getElementById('t-secs').textContent = String(seconds).padStart(2, '0');
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
    }

    initRealTimer();

    function saveToHistory(id, name, img) {
        let history = JSON.parse(localStorage.getItem('view_history')) || [];
        history = history.filter(item => item.id !== id);
        history.unshift({ id, name, img });
        if (history.length > 4) history.pop();
        localStorage.setItem('view_history', JSON.stringify(history));
    }

    function renderHistory() {
        let history = JSON.parse(localStorage.getItem('view_history')) || [];
        if (history.length === 0) return;
        const grid = document.getElementById('history-grid');
        grid.innerHTML = '';
        history.forEach(item => {
            grid.innerHTML += `
                <div class="product-card">
                    <a href="product.php?id=${item.id}" class="product-image-link">
                        <img src="${item.img}" alt="${item.name}">
                    </a>
                    <a href="product.php?id=${item.id}" class="product-title-link">${item.name}</a>
                </div>
            `;
        });
        document.getElementById('history-section').style.display = 'block';
    }
    renderHistory();

    function openQuickView(id) {
        const modal = document.getElementById('quick-view-modal');
        const body = document.getElementById('modal-body');
        modal.style.display = 'flex';
        body.innerHTML = '<div style="text-align:center; padding: 20px;">Загрузка данных...</div>';

        fetch(`index.php?ajax_quick_view=${id}`)
            .then(res => res.json())
            .then(data => {
                if (!data || !data.name) {
                    body.innerHTML = '<div style="text-align:center; color:#ef4444;">Ошибка загрузки товара.</div>';
                    return;
                }

                const imgUrl = data.catalog_img || data.image_url || 'img/default.jpg';
                const storedDealId = parseInt(localStorage.getItem('deal_product_id'));
                const isDeal = (storedDealId && storedDealId === parseInt(data.id));

                const originalPrice = Number(data.price);
                const activePrice = isDeal ? Math.round(originalPrice * 0.93) : originalPrice;

                body.innerHTML = `
                    <div class="qv-grid">
                        <div class="qv-image-box">
                            <img src="${imgUrl}" alt="${data.name}" onerror="this.src='img/default.jpg';">
                        </div>
                        <div class="qv-info-box">
                            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">${data.brand || ''}</div>
                            <h2 style="margin: 0 0 10px 0; font-size: 24px;">${data.name}</h2>
                            <p style="color: var(--text-muted); font-size: 14px; line-height: 1.5; margin-bottom: 18px;">${data.description}</p>
                            <div style="font-size: 26px; font-weight: 800; color: #00e676; margin-bottom: 20px;">
                                ${activePrice.toLocaleString('ru-RU')} грн.
                                ${isDeal ? `<span style="font-size:16px; text-decoration:line-through; color: var(--text-muted); font-weight:normal; margin-left:10px;">${originalPrice.toLocaleString('ru-RU')} грн.</span>` : ''}
                            </div>
                            <div style="display: flex; gap: 12px;">
                                <button class="buy-btn" style="padding: 12px 20px;" onclick="addToCart(${data.id}); closeQuickView();">🛒 Добавить в корзину</button>
                                <a href="product.php?id=${data.id}${isDeal ? '&is_deal=1' : ''}" class="filter-btn" style="text-decoration:none; display:inline-flex; align-items:center;">Подробнее ➔</a>
                            </div>
                        </div>
                    </div>
                `;
            })
            .catch(() => {
                body.innerHTML = '<div style="text-align:center; color:#ef4444;">Ошибка сети.</div>';
            });
    }

    function closeQuickView() {
        document.getElementById('quick-view-modal').style.display = 'none';
    }

    const searchInput = document.getElementById('search-input');
    const suggestionsBox = document.getElementById('search-suggestions');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const text = this.value.trim();
            if (text.length < 2) { suggestionsBox.style.display = 'none'; return; }

            fetch(`index.php?ajax_search=${encodeURIComponent(text)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.length === 0) { suggestionsBox.style.display = 'none'; return; }
                    suggestionsBox.innerHTML = '';
                    data.forEach(item => {
                        const link = document.createElement('a');
                        link.href = `product.php?id=${item.id}`;
                        link.className = 'suggestion-item';
                        
                        const imgPath = item.catalog_img || item.image_url || 'img/default.jpg';
                        
                        link.innerHTML = `
                            <div class="suggestion-img-box">
                                <img src="${imgPath}" alt="${item.name}" onerror="this.src='img/default.jpg';">
                            </div>
                            <div>
                                <div style="font-weight:600;">${item.name}</div>
                                <div style="font-size:12px; color:#00e676;">${item.price} грн.</div>
                            </div>
                        `;
                        suggestionsBox.appendChild(link);
                    });
                    suggestionsBox.style.display = 'block';
                });
        });
    }

    function addToCart(productId) {
        let formData = new FormData();
        formData.append('product_id', productId);

        let storedDealId = parseInt(localStorage.getItem('deal_product_id'));
        if (storedDealId && storedDealId === parseInt(productId)) {
            if (globalDealProduct) {
                let oldPrice = Number(globalDealProduct.price);
                let discountPrice = Math.round(oldPrice * 0.93);
                formData.append('price', discountPrice);
            }
        }

        fetch('add_to_cart.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('cart-count').textContent = data.total_count;
                document.getElementById('floating-cart-count').textContent = data.total_count;
                showToast('🛒 Товар успешно добавлен в корзину!');
            }
        });
    }

    function showToast(msg) {
        const toast = document.getElementById('toast');
        document.getElementById('toast-message').textContent = msg;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }
</script>
</body>
</html>