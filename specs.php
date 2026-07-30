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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_deal = isset($_GET['is_deal']) && $_GET['is_deal'] == 1;

$stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: index.php');
    exit;
}

// --- НАДЕЖНАЯ РАСПАКОВКА ЦВЕТОВ И КАРТИНОК ---
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
    }
}

if (empty($color_map)) {
    $color_map['Стандартный'] = !empty($product['image_url']) ? $product['image_url'] : 'img/default.jpg';
}

$default_db_img = reset($color_map);

// --- Читаем характеристики из БД ---
$custom_specs = [];
$raw_specs = !empty($product['specifications']) ? $product['specifications'] : (!empty($product['specs']) ? $product['specs'] : '');

if (!empty($raw_specs)) {
    $clean_specs_str = html_entity_decode($raw_specs, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $clean_specs_str = stripslashes($clean_specs_str);
    $parsed_specs = json_decode($clean_specs_str, true);
    if (is_array($parsed_specs)) {
        $custom_specs = $parsed_specs;
    }
}

// Считаем количество в корзине
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
    <title>Характеристики <?= htmlspecialchars($product['name']); ?> — CyberPhone</title>

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
            --card-img-bg: #1a2234;
            --shadow: rgba(0, 0, 0, 0.4);
            --option-btn-bg: #1a2234;
        }

        [data-theme="light"] {
            --bg-color: #f3f4f6;
            --panel-bg: #ffffff;
            --border-color: #e5e7eb;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --card-img-bg: #f9fafb;
            --shadow: rgba(0, 0, 0, 0.06);
            --option-btn-bg: #f1f5f9;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            transition: background-color 0.3s ease, color 0.3s ease;
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
        }

        .nav-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
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
            border-color: #3b82f6;
        }

        .nav-link-btn {
            background-color: var(--card-img-bg);
            border: 1px solid var(--border-color);
            padding: 10px 18px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .nav-link-btn:hover {
            background-color: #2563eb;
            border-color: #2563eb;
            color: #fff;
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
            margin-bottom: 30px;
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

        .main-layout {
            max-width: 1200px;
            margin: 0 auto 50px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 40px;
            align-items: start;
        }

        .specs-title {
            font-size: 26px;
            margin: 0 0 30px 0;
            font-weight: 700;
        }

        .section-group {
            margin-bottom: 30px;
        }

        .section-heading {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #3b82f6;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .spec-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--panel-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .spec-table tr {
            border-bottom: 1px solid var(--border-color);
        }

        .spec-table tr:last-child {
            border-bottom: none;
        }

        .spec-table td {
            padding: 14px 20px;
            font-size: 14px;
        }

        .spec-label {
            color: var(--text-muted);
            width: 40%;
        }

        .spec-val {
            color: var(--text-main);
            font-weight: 600;
        }

        .sidebar-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 25px;
            position: sticky;
            top: 20px;
            box-shadow: 0 4px 20px var(--shadow);
            text-align: center;
        }

        .sidebar-img-box {
            height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            position: relative;
        }

        .sidebar-img-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: opacity 0.2s ease-in-out;
        }

        .deal-tag {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            background: linear-gradient(135deg, #ef4444 0%, #ff1744 100%);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 12px;
            text-transform: uppercase;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4);
        }

        .color-selector-box {
            margin-bottom: 20px;
            text-align: left;
        }

        .color-label {
            font-size: 12px;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 1px;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .color-options-flex {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .color-btn {
            background-color: var(--option-btn-bg);
            color: var(--text-main);
            border: 1px solid var(--border-color);
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .color-btn:hover {
            border-color: #3b82f6;
        }

        .color-btn.active {
            background-color: #2563eb;
            border-color: #2563eb;
            color: #ffffff;
            box-shadow: 0 0 10px rgba(37, 99, 235, 0.4);
        }

        .price-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .current-price {
            font-size: 30px;
            font-weight: 800;
            color: var(--accent-green);
        }

        .old-price {
            display: none;
            font-size: 16px;
            text-decoration: line-through;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .btn-buy-now {
            background-color: var(--accent-green);
            color: #000;
            border: none;
            width: 100%;
            padding: 16px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-buy-now:hover {
            background-color: #00c853;
            box-shadow: 0 0 20px rgba(0, 230, 118, 0.4);
        }

        .toast-notification {
            position: fixed;
            bottom: -150px;
            right: 30px;
            background-color: var(--panel-bg);
            border: 1px solid #00e676;
            color: var(--text-main);
            padding: 18px 22px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 230, 118, 0.25);
            z-index: 10000;
            width: 320px;
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
            margin-bottom: 4px;
            color: #00e676;
        }

        .toast-body {
            color: var(--text-muted);
            font-size: 13px;
        }
    </style>
</head>
<body>

<header>
    <div class="header-content">
        <h1><a href="index.php" class="logo-link">Cyber<span style="color:#3b82f6;">Phone</span></a></h1>
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
        <a id="tab-overview" href="product.php?id=<?= $product['id']; ?>" class="tab-btn">Обзор</a>
        <a id="tab-specs" href="specs.php?id=<?= $product['id']; ?>" class="tab-btn active">Характеристики</a>
    </div>
</div>

<div class="main-layout">
    <div>
        <h2 class="specs-title">Характеристики Смартфон <?= htmlspecialchars($product['name']); ?></h2>

        <div class="section-group">
            <div class="section-heading">⚡ Полная спецификация флагмана</div>
            <table class="spec-table">
                <?php if (!empty($custom_specs)): ?>
                    <?php foreach ($custom_specs as $s_name => $s_val): ?>
                        <tr>
                            <td class="spec-label"><?= htmlspecialchars($s_name); ?></td>
                            <td class="spec-val" style="<?= (strpos($s_val, '4K') !== false || strpos($s_val, 'OLED') !== false || strpos($s_val, 'IP68') !== false || strpos($s_val, 'Титан') !== false) ? 'color:#00e676;' : ''; ?>">
                                <?= htmlspecialchars($s_val); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            Характеристики для данного товара пока не заполнены в панели администратора.
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="sidebar-card">
        <div class="sidebar-img-box">
            <div id="deal-tag" class="deal-tag">🔥 Скидка дня</div>
            <img id="sidebar-product-img" src="<?= htmlspecialchars($default_db_img); ?>" alt="<?= htmlspecialchars($product['name']); ?>" onerror="this.src='img/default.jpg';">
        </div>

        <div class="color-selector-box">
            <div class="color-label">Выберите цвет:</div>
            <div class="color-options-flex">
                <?php 
                $is_first = true;
                foreach ($color_map as $c_name => $c_img): 
                    $active_class = $is_first ? 'active' : '';
                    $is_first = false;
                ?>
                    <button class="color-btn <?= $active_class; ?>" 
                            data-img="<?= htmlspecialchars($c_img); ?>" 
                            onclick="changeProductColor(this)">
                        <?= htmlspecialchars($c_name); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="price-box">
            <span class="current-price" id="current-price-display"><?= number_format($product['price'], 0, '', ' '); ?> ₴</span>
            <span class="old-price" id="old-price-display"></span>
        </div>

        <button class="btn-buy-now" onclick="addToCart(<?= $product['id']; ?>)">
            🛒 Купить
        </button>
    </div>
</div>

<script>
    const originalPrice = <?= (float)$product['price']; ?>;
    let finalActivePrice = originalPrice;

    // --- ПРОВЕРКА АКЦИИ СКИДКИ ДНЯ ---
    const productId = <?= $product['id']; ?>;
    const isUrlDeal = <?= $is_deal ? 'true' : 'false'; ?>;
    const storedDealId = parseInt(localStorage.getItem('deal_product_id'));

    if (isUrlDeal || (storedDealId && storedDealId === productId)) {
        finalActivePrice = Math.round(originalPrice * 0.93);
        
        document.getElementById('deal-tag').style.display = 'block';
        document.getElementById('current-price-display').textContent = finalActivePrice.toLocaleString('ru-RU') + ' ₴';
        
        const oldPriceEl = document.getElementById('old-price-display');
        oldPriceEl.textContent = originalPrice.toLocaleString('ru-RU') + ' ₴';
        oldPriceEl.style.display = 'block';

        // Прокидываем &is_deal=1 на вкладку "Обзор"
        document.getElementById('tab-overview').href = `product.php?id=${productId}&is_deal=1`;
        document.getElementById('tab-specs').href = `specs.php?id=${productId}&is_deal=1`;
    }

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

    document.addEventListener("DOMContentLoaded", () => {
        const wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];
        const wishlistBadge = document.getElementById('wishlist-count');
        if (wishlistBadge) wishlistBadge.textContent = wishlist.length;
    });

    function changeProductColor(button) {
        document.querySelectorAll('.color-btn').forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');

        const newImgSrc = button.getAttribute('data-img');
        const imgEl = document.getElementById('sidebar-product-img');

        if (imgEl && newImgSrc) {
            imgEl.style.opacity = '0.3';
            setTimeout(() => {
                imgEl.src = newImgSrc;
                imgEl.style.opacity = '1';
            }, 150);
        }
    }

    function addToCart(productId) {
        const activeColorBtn = document.querySelector('.color-btn.active');
        const activeColor = activeColorBtn ? activeColorBtn.textContent.trim() : '';
        const imgEl = document.getElementById('sidebar-product-img');
        const activeImage = imgEl ? imgEl.getAttribute('src') : '';

        let formData = new FormData();
        formData.append('product_id', productId);
        formData.append('color', activeColor);
        formData.append('price', finalActivePrice);
        formData.append('image', activeImage);

        fetch('add_to_cart.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const cartCount = document.getElementById('cart-count');
                if (cartCount) cartCount.textContent = data.total_count;

                const existingToast = document.querySelector('.toast-notification');
                if (existingToast) existingToast.remove();

                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.innerHTML = `
                    <div class="toast-title">🛒 Успешно добавлено!</div>
                    <div class="toast-body">
                        Товар «<?= htmlspecialchars($product['name']); ?>» (${activeColor}) добавлен в корзину.
                    </div>
                `;
                
                document.body.appendChild(toast);
                setTimeout(() => toast.classList.add('show'), 10);
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 400);
                }, 3500);
            }
        });
    }
</script>
</body>
</html>