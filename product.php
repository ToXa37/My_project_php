<?php
session_start();

try {
    $db = new PDO('mysql:host=localhost;dbname=tech_shop;charset=utf8', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $db->exec("CREATE TABLE IF NOT EXISTS product_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        username VARCHAR(100) NOT NULL,
        rating INT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: index.php');
    exit;
}

$original_price = (float)$product['price'];

// Обработка добавления отзыва
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_review') {
    $rev_rating = (int)($_POST['rating'] ?? 5);
    $rev_comment = trim($_POST['comment'] ?? '');
    $rev_user = $_SESSION['username'] ?? 'Покупатель';

    if (!empty($rev_comment) && $rev_rating >= 1 && $rev_rating <= 5) {
        $ins_stmt = $db->prepare("INSERT INTO product_reviews (product_id, username, rating, comment) VALUES (?, ?, ?, ?)");
        $ins_stmt->execute([$id, $rev_user, $rev_rating, $rev_comment]);
        header("Location: product.php?id={$id}&review_added=1#reviews-block");
        exit;
    }
}

// Получение отзывов
$rev_stmt = $db->prepare("SELECT * FROM product_reviews WHERE product_id = ? ORDER BY created_at DESC");
$rev_stmt->execute([$id]);
$reviews = $rev_stmt->fetchAll();

$reviews_count = count($reviews);
$avg_rating = 0;
if ($reviews_count > 0) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avg_rating = round($sum / $reviews_count, 1);
}

$storage_arr = !empty($product['storage_options']) ? explode(', ', $product['storage_options']) : ['256 ГБ'];
$ram_arr = !empty($product['ram']) ? explode(', ', $product['ram']) : ['12 ГБ'];

// --- РАСПАКОВКА ЦВЕТОВ И КАРТИНОК ---
$color_map = [];
$raw_colors_data = trim($product['colors'] ?? '');

if (!empty($raw_colors_data)) {
    $clean_str = html_entity_decode($raw_colors_data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $clean_str = stripslashes($clean_str);

    $parsed_colors = json_decode($clean_str, true);
    if (is_string($parsed_colors)) {
        $parsed_colors = json_decode($parsed_colors, true);
    }

    if (is_array($parsed_colors) && !empty($parsed_colors)) {
        foreach ($parsed_colors as $c_name => $c_img) {
            $c_name_clean = trim($c_name);
            $c_img_clean = trim(str_replace('\/', '/', $c_img));
            if (!empty($c_name_clean)) {
                $color_map[$c_name_clean] = !empty($c_img_clean) ? $c_img_clean : (!empty($product['image_url']) ? $product['image_url'] : 'img/default.jpg');
            }
        }
    } elseif (preg_match_all('/"([^"]+)":\s*"([^"]+)"/u', $clean_str, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $c_name_clean = trim($match[1]);
            $c_img_clean = trim(str_replace('\/', '/', $match[2]));
            if (!empty($c_name_clean)) {
                $color_map[$c_name_clean] = !empty($c_img_clean) ? $c_img_clean : (!empty($product['image_url']) ? $product['image_url'] : 'img/default.jpg');
            }
        }
    } elseif (strpos($clean_str, '{') === false) {
        $raw_colors = explode(',', $clean_str);
        foreach ($raw_colors as $c) {
            $c_clean = trim($c);
            if (!empty($c_clean)) {
                $color_map[$c_clean] = !empty($product['image_url']) ? $product['image_url'] : 'img/default.jpg';
            }
        }
    }
}

if (empty($color_map)) {
    $color_map['Стандартный'] = !empty($product['image_url']) ? $product['image_url'] : 'img/default.jpg';
}

$default_db_img = reset($color_map);
if (empty($default_db_img)) {
    $default_db_img = !empty($product['image_url']) ? $product['image_url'] : 'img/default.jpg';
}

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
    <title><?= htmlspecialchars($product['name']); ?> — CyberPhone</title>

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
            --card-img-bg: #1a2234;
            --radial-gradient-1: radial-gradient(circle at 50% 30%, rgba(37, 99, 235, 0.12) 0%, transparent 50%);
            --shadow: rgba(0, 0, 0, 0.4);
            --accent-green: #00e676;
            --accent-red: #ef4444;
        }

        [data-theme="light"] {
            --bg-color: #f3f4f6;
            --panel-bg: #ffffff;
            --border-color: #e5e7eb;
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
            transition: background-color 0.3s ease, color 0.3s ease;
            background-image: var(--radial-gradient-1);
        }

        header {
            background-color: var(--panel-bg);
            padding: 20px;
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

        .logo-link {
            color: var(--text-main);
            text-decoration: none;
            font-size: 28px;
            font-weight: bold;
            transition: color 0.2s;
        }

        .nav-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .nav-link-btn {
            background-color: var(--card-img-bg);
            border: 1px solid var(--border-color);
            padding: 10px 18px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .nav-link-btn, .nav-link-btn * {
            color: var(--text-main) !important;
        }

        .nav-link-btn:hover {
            background-color: #2563eb;
            border-color: #2563eb;
            box-shadow: 0 0 15px rgba(37, 99, 235, 0.4);
            transform: translateY(-2px);
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

        .badge {
            background-color: #ef4444;
            color: white;
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 10px;
            font-weight: bold;
        }

        .tabs-header {
            background: var(--panel-bg);
            border-bottom: 1px solid var(--border-color);
        }

        .tabs-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            gap: 10px;
            padding: 0 20px;
        }

        .tab-btn {
            padding: 15px 20px;
            text-decoration: none;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 15px;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }

        .tab-btn:hover {
            color: var(--text-main);
        }

        .tab-btn.active {
            color: var(--accent-green);
            border-bottom-color: var(--accent-green);
            background: rgba(0, 230, 118, 0.05);
        }

        .product-container {
            max-width: 1200px;
            margin: 30px auto 50px auto;
            padding: 0 20px;
            display: flex;
            gap: 50px;
            flex-wrap: wrap;
        }

        .image-section {
            flex: 1;
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 450px;
            min-width: 300px;
            box-shadow: 0 4px 20px var(--shadow);
            transition: background-color 0.3s, border-color 0.3s;
            overflow: hidden;
            position: relative;
            cursor: zoom-in;
        }

        .image-section img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.15s ease-out;
            pointer-events: none;
            transform-origin: center center;
        }

        .info-section {
            flex: 1;
            min-width: 300px;
        }

        .brand-tag {
            font-size: 14px;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 2px;
            margin-bottom: 10px;
        }

        .rating-box {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
            font-weight: bold;
            color: #f59e0b;
        }

        h2 {
            font-size: 36px;
            margin: 0 0 10px 0;
            font-weight: 700;
        }

        .price-wrapper {
            display: flex;
            align-items: baseline;
            gap: 15px;
            margin-bottom: 30px;
        }

        .price-tag {
            font-size: 32px;
            font-weight: 800;
            color: #00e676;
            text-shadow: 0 0 15px rgba(0, 230, 118, 0.15);
        }

        .old-price-tag {
            font-size: 20px;
            text-decoration: line-through;
            color: var(--text-muted);
            font-weight: 600;
            display: none;
        }

        .selector-group {
            margin-bottom: 25px;
        }

        .selector-label {
            font-size: 12px;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 1px;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .options-flex {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .option-btn {
            background-color: var(--panel-bg);
            color: var(--text-main);
            border: 1px solid var(--border-color);
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }

        .option-btn:hover {
            border-color: #3b82f6;
        }

        .option-btn.active {
            background-color: #2563eb;
            border-color: #2563eb;
            box-shadow: 0 0 12px rgba(37, 99, 235, 0.4);
            color: #ffffff;
        }

        .desc-box {
            margin-bottom: 35px;
        }

        .desc-text {
            color: var(--text-muted);
            font-size: 16px;
            line-height: 1.6;
            margin: 0;
        }

        .actions-flex {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .main-buy-btn {
            background-color: #00e676;
            color: #000000;
            border: none;
            padding: 15px 35px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0, 230, 118, 0.2);
            flex: 1;
            justify-content: center;
        }

        .main-buy-btn:hover {
            background-color: #00c853;
            box-shadow: 0 0 25px rgba(0, 230, 118, 0.5);
            transform: translateY(-2px);
        }

        .main-wishlist-btn {
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 15px 20px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .main-wishlist-btn:hover {
            border-color: var(--accent-red);
            color: var(--accent-red);
            transform: translateY(-2px);
        }

        .main-wishlist-btn.liked {
            background-color: rgba(239, 68, 68, 0.12);
            border-color: var(--accent-red);
            color: var(--accent-red);
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.25);
        }

        .reviews-wrapper {
            max-width: 1200px;
            margin: 40px auto 60px auto;
            padding: 0 20px;
            border-top: 1px solid var(--border-color);
            padding-top: 40px;
        }

        .reviews-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .review-form-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 35px;
            box-shadow: 0 4px 20px var(--shadow);
        }

        .star-rating-picker {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 6px;
        }

        .star-rating-picker input {
            display: none;
        }

        .star-rating-picker label {
            font-size: 28px;
            color: #4b5563;
            cursor: pointer;
            transition: color 0.15s ease-in-out, transform 0.15s;
        }

        .star-rating-picker label:hover,
        .star-rating-picker label:hover ~ label,
        .star-rating-picker input:checked ~ label {
            color: #f59e0b;
        }

        .star-rating-picker label:hover {
            transform: scale(1.2);
        }

        .review-textarea {
            width: 100%;
            background: var(--card-img-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-main);
            padding: 12px 15px;
            margin-bottom: 15px;
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
        }

        .review-card-item {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 15px;
        }

        .review-user-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .toast-notification {
            position: fixed;
            bottom: -150px;
            right: 30px;
            background-color: var(--panel-bg);
            border: 1px solid #00e676;
            color: var(--text-main);
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 230, 118, 0.2);
            z-index: 10000;
            width: 340px;
            transition: bottom 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s;
            opacity: 0;
        }

        .toast-notification.show {
            bottom: 30px;
            opacity: 1;
        }

        .toast-title {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toast-body {
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.4;
        }
    </style>
</head>
<body>

<header>
    <div class="header-content">
        <h1><a href="index.php" class="logo-link">Cyber<span style="color: #2563eb;">Phone</span></a></h1>
        <div class="nav-buttons">
            <button id="theme-toggle" class="theme-toggle-btn">🌙</button>
            <a href="index.php" class="nav-link-btn">🏠 На главную</a>
            <a href="wishlist.php" class="nav-link-btn">❤️ Избранное <span id="wishlist-count" class="badge">0</span></a>
            <a href="cart.php" class="nav-link-btn" style="border-color: #00e676;">🛒 Корзина <span id="cart-count" class="badge" style="background-color: #00e676; color: #000000;"><?= $cart_count; ?></span></a>
        </div>
    </div>
</header>

<div class="tabs-header">
    <div class="tabs-container">
        <a href="product.php?id=<?= $product['id']; ?>" class="tab-btn active">Обзор</a>
        <a href="specs.php?id=<?= $product['id']; ?>" class="tab-btn">Характеристики</a>
    </div>
</div>

<div class="product-container">
    <div class="image-section" id="image-zoom-container">
        <img id="main-product-image" src="<?= htmlspecialchars($default_db_img); ?>" alt="<?= htmlspecialchars($product['name']); ?>" onerror="this.src='img/default.jpg';">
    </div>

    <div class="info-section">
        <div class="brand-tag"><?= htmlspecialchars($product['brand']); ?></div>
        <h2><?= htmlspecialchars($product['name']); ?></h2>
        
        <div class="rating-box">
            <?php if ($reviews_count > 0): ?>
                ⭐ <?= $avg_rating; ?> / 5.0 
            <?php endif; ?>
            <span style="color: var(--text-muted); font-size: 14px; font-weight: normal;">(Отзывов: <?= $reviews_count; ?>)</span>
        </div>

        <div class="price-wrapper">
            <div class="price-tag"><span id="dynamic-price"><?= number_format($original_price, 0, '', ' '); ?></span> грн.</div>
            <div class="old-price-tag" id="old-price-display"><?= number_format($original_price, 0, '', ' '); ?> грн.</div>
        </div>

        <div class="selector-group">
            <div class="selector-label">Выберите цвет:</div>
            <div class="options-flex" id="color-selector">
                <?php 
                $is_first = true;
                foreach ($color_map as $color_name => $img_path): 
                    $active_class = $is_first ? 'active' : '';
                    $is_first = false;
                ?>
                    <button class="option-btn <?= $active_class; ?>" 
                            data-image="<?= htmlspecialchars($img_path); ?>" 
                            onclick="selectColor(this)">
                        <?= htmlspecialchars($color_name); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="selector-group">
            <div class="selector-label">Оперативная память (RAM):</div>
            <div class="options-flex" id="ram-selector">
                <?php foreach ($ram_arr as $index => $ram): ?>
                    <?php 
                    $ram_addon = 0;
                    if (strpos($ram, '16') !== false && $index > 0) $ram_addon = 3500;
                    ?>
                    <button class="option-btn <?= $index === 0 ? 'active' : ''; ?>" data-add="<?= $ram_addon; ?>" onclick="selectRam(this)"><?= htmlspecialchars($ram); ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="selector-group">
            <div class="selector-label">Встроенная память (ROM):</div>
            <div class="options-flex" id="storage-selector">
                <?php foreach ($storage_arr as $index => $storage): ?>
                    <?php 
                    $addon = 0;
                    if (strpos($storage, '256') !== false) $addon = 1500;
                    if (strpos($storage, '512') !== false) $addon = 3000;
                    if (strpos($storage, '1 ТБ') !== false || strpos($storage, '1TB') !== false || strpos($storage, '1024') !== false) $addon = 6500;
                    ?>
                    <button class="option-btn <?= $index === 0 ? 'active' : ''; ?>" data-add="<?= $addon; ?>" onclick="selectStorage(this)"><?= htmlspecialchars($storage); ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="desc-box">
            <div class="selector-label">Описание девайса:</div>
            <p class="desc-text"><?= htmlspecialchars($product['description']); ?></p>
        </div>

        <div class="actions-flex">
            <button class="main-buy-btn" onclick="triggerAddToCart(<?= $product['id']; ?>)">🛒 Добавить в корзину</button>
            <button id="wishlist-btn-page" class="main-wishlist-btn" onclick="toggleWishlistPage(<?= $product['id']; ?>)">
                <span class="heart-icon">🤍</span> Избранное
            </button>
        </div>
    </div>
</div>

<div class="reviews-wrapper" id="reviews-block">
    <div class="reviews-title">💬 Отзывы покупателей (<?= count($reviews); ?>)</div>

    <form method="POST" class="review-form-card">
        <input type="hidden" name="action" value="add_review">
        <div style="margin-bottom: 15px;">
            <label class="selector-label">Ваша оценка:</label>
            <div class="star-rating-picker">
                <input type="radio" id="star5" name="rating" value="5" checked><label for="star5" title="Отлично (5/5)">★</label>
                <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="Хорошо (4/5)">★</label>
                <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="Нормально (3/5)">★</label>
                <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="Плохо (2/5)">★</label>
                <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="Ужасно (1/5)">★</label>
            </div>
        </div>
        <textarea name="comment" class="review-textarea" rows="3" placeholder="Напишите ваш отзыв о девайсе..." required></textarea>
        <button type="submit" class="option-btn active" style="background: #2563eb; color: #fff;">Отправить отзыв</button>
    </form>

    <?php if (empty($reviews)): ?>
        <p style="color: var(--text-muted);">Пока отзывов нет. Будьте первым, кто оставит впечатление!</p>
    <?php else: ?>
        <?php foreach ($reviews as $rev): ?>
            <div class="review-card-item">
                <div class="review-user-row">
                    <strong>👤 <?= htmlspecialchars($rev['username']); ?></strong>
                    <span style="color: #f59e0b;"><?= str_repeat('★', $rev['rating']); ?></span>
                </div>
                <div style="color: var(--text-muted); font-size: 15px; margin-top: 8px;">
                    <?= htmlspecialchars($rev['comment']); ?>
                </div>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 10px;">
                    <?= date('d.m.Y H:i', strtotime($rev['created_at'])); ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
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

    const currentProdId = <?= (int)$product['id']; ?>;
    let originalPrice = <?= (float)$original_price; ?>;
    let basePrice = originalPrice;
    let isDealProduct = false;

    const urlParams = new URLSearchParams(window.location.search);
    let isDealUrl = urlParams.get('is_deal') === '1';

    let storedIndex = localStorage.getItem('deal_product_index');
    
    if (isDealUrl) {
        isDealProduct = true;
    } else if (storedIndex !== null) {
        let savedDealId = localStorage.getItem('deal_product_id');
        if (savedDealId && parseInt(savedDealId) === currentProdId) {
            isDealProduct = true;
        }
    }

    if (isDealProduct) {
        basePrice = Math.round(originalPrice * 0.93);
    }

    let storageAddon = 0;
    let ramAddon = 0;

    document.addEventListener("DOMContentLoaded", () => {
        if (isDealUrl) {
            localStorage.setItem('deal_product_id', currentProdId);
        }

        updateWishlistStatus(currentProdId);

        const activeRamBtn = document.querySelector('#ram-selector .option-btn.active');
        if (activeRamBtn) ramAddon = parseInt(activeRamBtn.getAttribute('data-add')) || 0;

        const activeStorageBtn = document.querySelector('#storage-selector .option-btn.active');
        if (activeStorageBtn) storageAddon = parseInt(activeStorageBtn.getAttribute('data-add')) || 0;

        updatePriceDisplay();
        initImageZoom();
    });

    function updateWishlistStatus(productId) {
        const wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];
        const wishlistBadge = document.getElementById('wishlist-count');
        const wishlistBtn = document.getElementById('wishlist-btn-page');

        if (wishlistBadge) wishlistBadge.textContent = wishlist.length;

        if (wishlistBtn) {
            if (wishlist.includes(productId)) {
                wishlistBtn.classList.add('liked');
                wishlistBtn.querySelector('.heart-icon').textContent = '❤️';
            } else {
                wishlistBtn.classList.remove('liked');
                wishlistBtn.querySelector('.heart-icon').textContent = '🤍';
            }
        }
    }

    function toggleWishlistPage(productId) {
        let wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];
        productId = parseInt(productId);

        let isAdded = false;

        if (wishlist.includes(productId)) {
            wishlist = wishlist.filter(id => id !== productId);
            isAdded = false;
        } else {
            wishlist.push(productId);
            isAdded = true;
        }

        localStorage.setItem('wishlist', JSON.stringify(wishlist));
        updateWishlistStatus(productId);

        showToastNotification(
            isAdded ? '❤️ Добавлено в избранное!' : '💔 Удалено из избранного',
            isAdded ? '#ef4444' : '#8a99ad',
            isAdded ? 'Товар сохранен в вашем списке желаемого.' : 'Товар был удален из списка.'
        );
    }

    function initImageZoom() {
        const container = document.getElementById('image-zoom-container');
        const img = document.getElementById('main-product-image');

        if (!container || !img) return;

        container.addEventListener('mousemove', (e) => {
            const rect = container.getBoundingClientRect();
            const x = ((e.clientX - rect.left) / rect.width) * 100;
            const y = ((e.clientY - rect.top) / rect.height) * 100;

            img.style.transformOrigin = `${x}% ${y}%`;
            img.style.transform = 'scale(2.2)';
        });

        container.addEventListener('mouseleave', () => {
            img.style.transform = 'scale(1)';
            img.style.transformOrigin = 'center center';
        });
    }

    function updatePriceDisplay() {
        const currentTotal = basePrice + storageAddon + ramAddon;
        document.getElementById('dynamic-price').textContent = currentTotal.toLocaleString('ru-RU');

        const oldPriceDisplay = document.getElementById('old-price-display');
        if (isDealProduct) {
            const oldTotal = originalPrice + storageAddon + ramAddon;
            oldPriceDisplay.textContent = oldTotal.toLocaleString('ru-RU') + ' грн.';
            oldPriceDisplay.style.display = 'inline-block';
        } else {
            oldPriceDisplay.style.display = 'none';
        }
    }

    function selectColor(button) {
        document.querySelectorAll('#color-selector .option-btn').forEach(b => b.classList.remove('active'));
        button.classList.add('active');
        
        const newImgPath = button.getAttribute('data-image');
        const imgEl = document.getElementById('main-product-image');
        
        if (imgEl && newImgPath) {
            imgEl.src = newImgPath;
        }
    }

    function selectRam(button) {
        document.querySelectorAll('#ram-selector .option-btn').forEach(b => b.classList.remove('active'));
        button.classList.add('active');
        ramAddon = parseInt(button.getAttribute('data-add')) || 0;
        updatePriceDisplay();
    }

    function selectStorage(button) {
        document.querySelectorAll('#storage-selector .option-btn').forEach(b => b.classList.remove('active'));
        button.classList.add('active');
        storageAddon = parseInt(button.getAttribute('data-add')) || 0;
        updatePriceDisplay();
    }

    function showToastNotification(title, color, text) {
        const existingToast = document.querySelector('.toast-notification');
        if (existingToast) existingToast.remove();

        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.style.borderColor = color;

        toast.innerHTML = `
            <div class="toast-title" style="color: ${color}">${title}</div>
            <div class="toast-body">${text}</div>
        `;

        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400);
        }, 3500);
    }

    function triggerAddToCart(productId) {
        const activeColorBtn = document.querySelector('#color-selector .option-btn.active');
        const activeRamBtn = document.querySelector('#ram-selector .option-btn.active');
        const activeStorageBtn = document.querySelector('#storage-selector .option-btn.active');
        const mainImg = document.getElementById('main-product-image');

        const activeColor = activeColorBtn ? activeColorBtn.textContent.trim() : '';
        const activeRam = activeRamBtn ? activeRamBtn.textContent.trim() : '';
        const activeStorage = activeStorageBtn ? activeStorageBtn.textContent.trim() : '';
        
        const rawPriceText = document.getElementById('dynamic-price').textContent.replace(/\s+/g, '');
        const activePrice = parseFloat(rawPriceText) || 0;
        const activeImage = mainImg ? mainImg.getAttribute('src') : '';
        
        let formData = new FormData();
        formData.append('product_id', productId);
        formData.append('color', activeColor);
        formData.append('ram', activeRam);
        formData.append('storage', activeStorage);
        formData.append('price', activePrice);
        formData.append('image', activeImage);

        fetch('add_to_cart.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const cartCount = document.getElementById('cart-count');
                if (cartCount) cartCount.textContent = data.total_count;

                let titleText = '🛒 Успешно добавлено!';
                let borderColor = '#00e676';

                if (data.already_in_cart) {
                    titleText = '🔥 Уже в корзине (+1 шт.)';
                    borderColor = '#ef4444';
                }

                showToastNotification(
                    titleText,
                    borderColor,
                    `Конфигурация: ${activeColor} / ${activeRam} / ${activeStorage}<br><b>Цена: ${document.getElementById('dynamic-price').textContent} грн.</b>`
                );
            }
        });
    }
</script>
</body>
</html>