<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$show_success_modal = false;
if (isset($_GET['payment_success'])) {
    $show_success_modal = true;
}

// Очистка всей корзины
if (isset($_GET['clear'])) {
    $p_id = (int) $_GET['clear'];
    if ($p_id === 1) {
        unset($_SESSION['cart']);
        unset($_SESSION['cart_details']);
    }
    header('Location: cart.php');
    exit;
}

// Удаление отдельной позиции из корзины
if (isset($_GET['remove_key'])) {
    $remove_key = $_GET['remove_key'];
    if (isset($_SESSION['cart_details'][$remove_key])) {
        $pid = $_SESSION['cart_details'][$remove_key]['product_id'];
        unset($_SESSION['cart_details'][$remove_key]);
        unset($_SESSION['cart'][$pid]);
    } elseif (isset($_SESSION['cart'][$remove_key])) {
        unset($_SESSION['cart'][$remove_key]);
    }
    header('Location: cart.php');
    exit;
}

function formatSingleMemory($raw_ram, $raw_storage) {
    $clean_ram = trim($raw_ram);
    $clean_storage = trim($raw_storage);

    if (strpos($clean_ram, ',') !== false) {
        $parts = explode(',', $clean_ram);
        $clean_ram = trim($parts[0]);
    }
    if (strpos($clean_storage, ',') !== false) {
        $parts = explode(',', $clean_storage);
        $clean_storage = trim($parts[0]);
    }

    if (!empty($clean_ram) && !empty($clean_storage)) {
        return $clean_ram . ' / ' . $clean_storage;
    } elseif (!empty($clean_storage)) {
        return $clean_storage;
    } elseif (!empty($clean_ram)) {
        return $clean_ram;
    }
    return '';
}

function getCartColorSuffix($color_name) {
    $clean_color = mb_strtolower(trim($color_name));
    if (strpos($clean_color, 'liquid') !== false) return 'liquidsilver';
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

function getExistingCartImage($model, $suffix, $db_url = '') {
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

$cart_products = [];
$total_price = 0;

if (!empty($_SESSION['cart_details']) && is_array($_SESSION['cart_details'])) {
    $p_ids = array_unique(array_column($_SESSION['cart_details'], 'product_id'));

    if (!empty($p_ids)) {
        $in = implode(',', array_fill(0, count($p_ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($in)");
        $stmt->execute(array_values($p_ids));
        $db_products = $stmt->fetchAll(PDO::FETCH_UNIQUE);

        foreach ($_SESSION['cart_details'] as $item_key => $item) {
            $pid = $item['product_id'];
            if (isset($db_products[$pid])) {
                $product = $db_products[$pid];
                
                $price = !empty($item['price']) && $item['price'] > 0 ? (float)$item['price'] : (float)$product['price'];
                $original_price = (float)$product['price'];
                $is_discounted = ($price < $original_price);

                $quantity = (int)($item['quantity'] ?? 1);
                $subtotal = $price * $quantity;
                $total_price += $subtotal;

                $final_img = !empty($item['image']) ? $item['image'] : ($product['image_url'] ?? 'img/default.jpg');

                $ram_val = !empty($item['ram']) ? $item['ram'] : ($product['ram'] ?? '');
                $storage_val = !empty($item['storage']) ? $item['storage'] : ($product['storage_options'] ?? '');

                $spec_memory = formatSingleMemory($ram_val, $storage_val);

                $cart_products[] = [
                    'cart_key'       => $item_key,
                    'id'             => $pid,
                    'name'           => $product['name'],
                    'price'          => $price,
                    'original_price' => $original_price,
                    'is_discounted'   => $is_discounted,
                    'quantity'       => $quantity,
                    'subtotal'       => $subtotal,
                    'final_image'    => $final_img,
                    'color'          => !empty($item['color']) ? $item['color'] : '',
                    'spec_memory'    => $spec_memory
                ];
            }
        }
    }
} elseif (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $in = implode(',', array_fill(0, count($ids), '?'));
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($in)");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    foreach ($products as $product) {
        $quantity = (int)$_SESSION['cart'][$product['id']];
        $subtotal = $product['price'] * $quantity;
        $total_price += $subtotal;
        
        $model_clean = mb_strtolower(str_replace(' ', '', $product['name']));
        $colors_list = !empty($product['colors']) ? explode(', ', $product['colors']) : ['gray'];
        $first_color = $colors_list[0];
        $suffix = getCartColorSuffix($first_color);

        $spec_memory = formatSingleMemory($product['ram'] ?? '', $product['storage_options'] ?? '');

        $cart_products[] = [
            'cart_key'       => $product['id'],
            'id'             => $product['id'],
            'name'           => $product['name'],
            'price'          => $product['price'],
            'original_price' => $product['price'],
            'is_discounted'   => false,
            'quantity'       => $quantity,
            'subtotal'       => $subtotal,
            'final_image'    => getExistingCartImage($model_clean, $suffix, $product['image_url'] ?? ''),
            'color'          => '',
            'spec_memory'    => $spec_memory
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberPhone — Ваша Корзина</title>
    <style>
        :root {
            --bg-color: #0b0f19;
            --panel-bg: #131926;
            --border-color: #1f293d;
            --text-main: #ffffff;
            --text-muted: #8a99ad;
            --card-img-bg: #1a2234;
            --accent-green: #00e676;
            --accent-red: #ef4444;
            --accent-blue: #3b82f6;
        }

        [data-theme="light"] {
            --bg-color: #f3f4f6;
            --panel-bg: #ffffff;
            --border-color: #e5e7eb;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --card-img-bg: #f9fafb;
            --accent-green: #00c853;
            --accent-red: #dc2626;
            --accent-blue: #2563eb;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background-color 0.3s, color 0.3s;
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

        .logo-link:hover { color: #3b82f6; }

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

        .nav-link-btn, .nav-link-btn * { color: var(--text-main) !important; }

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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            flex: 1;
            width: 100%;
            box-sizing: border-box;
        }

        h2 { font-size: 32px; font-weight: 700; margin-top: 0; margin-bottom: 10px; }
        .subtitle { color: var(--text-muted); margin-bottom: 40px; font-size: 16px; }

        .cart-list { display: flex; flex-direction: column; gap: 20px; margin-bottom: 30px; }

        .cart-item {
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            transition: border-color 0.3s ease, background-color 0.3s;
        }

        .cart-item:hover {
            border-color: var(--accent-green);
            box-shadow: 0 4px 20px rgba(0, 230, 118, 0.05);
        }

        .item-image-link {
            background-color: var(--card-img-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            width: 85px;
            height: 85px;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            text-decoration: none;
            transition: background-color 0.3s, border-color 0.3s, transform 0.2s ease;
        }

        .item-image-link:hover {
            border-color: var(--accent-blue);
            transform: scale(1.05);
        }

        .item-image-link img { max-width: 100%; max-height: 100%; object-fit: contain; }
        
        .item-details { flex: 1; }

        .item-title-link {
            color: var(--text-main);
            font-size: 18px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: color 0.2s ease;
        }

        .item-title-link:hover {
            color: var(--accent-blue);
        }

        .item-badges-flex {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .item-color-badge {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 3px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .item-spec-badge {
            background: rgba(0, 230, 118, 0.12);
            color: var(--accent-green);
            border: 1px solid rgba(0, 230, 118, 0.3);
            padding: 3px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .item-deal-badge {
            background: rgba(239, 68, 68, 0.15);
            color: var(--accent-red);
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 3px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .item-meta { color: var(--text-muted); font-size: 14px; margin-top: 6px; }

        .item-price-block {
            text-align: right;
            min-width: 170px;
            display: flex;
            align-items: center;
            gap: 15px;
            justify-content: flex-end;
        }

        .item-subtotal { font-size: 20px; font-weight: 700; color: var(--accent-green); }
        .item-old-price { font-size: 14px; text-decoration: line-through; color: var(--text-muted); font-weight: 600; margin-top: 2px; }

        .btn-remove-single {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--accent-red);
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .btn-remove-single:hover {
            background: var(--accent-red);
            color: #ffffff;
            border-color: var(--accent-red);
            box-shadow: 0 0 12px rgba(239, 68, 68, 0.4);
            transform: scale(1.08);
        }

        .cart-summary {
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            flex-wrap: wrap;
            gap: 20px;
            transition: background-color 0.3s, border-color 0.3s;
        }

        .total-text { font-size: 20px; color: var(--text-muted); }
        .total-price { font-size: 28px; font-weight: 800; color: var(--accent-green); text-shadow: 0 0 15px rgba(0, 230, 118, 0.2); margin-left: 10px; }

        .cart-actions { display: flex; gap: 15px; align-items: center; }

        .btn-clear {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--accent-red);
            text-decoration: none;
            padding: 14px 22px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 15px;
            transition: all 0.2s ease;
        }

        .btn-clear:hover {
            background-color: var(--accent-red);
            color: #ffffff;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.4);
        }
        
        .btn-order {
            background-color: var(--accent-green);
            color: #000000;
            border: none;
            padding: 14px 30px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-block;
        }

        .btn-order:hover {
            background-color: #00c853;
            box-shadow: 0 0 20px rgba(0, 230, 118, 0.4);
            transform: translateY(-1px);
        }

        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(5, 7, 12, 0.85);
            display: flex; justify-content: center; align-items: center;
            opacity: 0; pointer-events: none;
            transition: opacity 0.3s ease;
            z-index: 2000;
            backdrop-filter: blur(8px);
        }
        .modal-overlay.show { opacity: 1; pointer-events: auto; }
        
        .modal-window {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            padding: 30px;
            border-radius: 20px;
            max-width: 560px;
            width: 90%;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            transform: scale(0.85);
            transition: transform 0.3s ease;
            color: var(--text-main);
        }
        .modal-overlay.show .modal-window { transform: scale(1); }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 22px;
            color: var(--accent-green);
        }

        .btn-modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 24px;
            cursor: pointer;
        }

        .confirm-items-list {
            max-height: 280px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
            padding-right: 5px;
        }

        .confirm-item-card {
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--card-img-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 15px;
        }

        .confirm-item-img {
            width: 55px;
            height: 55px;
            object-fit: contain;
        }

        .confirm-item-info {
            flex: 1;
            text-align: left;
        }

        .confirm-item-title {
            font-weight: bold;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .confirm-badges-flex {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 4px;
        }

        .confirm-badge-color {
            background: rgba(59, 130, 246, 0.15);
            color: var(--accent-blue);
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
        }

        .confirm-badge-spec {
            background: rgba(0, 230, 118, 0.12);
            color: var(--accent-green);
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
        }

        .modal-total-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 230, 118, 0.08);
            border: 1px solid rgba(0, 230, 118, 0.2);
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 16px;
            font-weight: 600;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-modal-cancel {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--accent-red);
            color: var(--accent-red);
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-modal-confirm {
            background: var(--accent-green);
            color: #000;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .success-icon-circle {
            width: 80px;
            height: 80px;
            background: rgba(0, 230, 118, 0.12);
            border: 2px solid var(--accent-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px auto;
            color: var(--accent-green);
            box-shadow: 0 0 30px rgba(0, 230, 118, 0.25);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--panel-bg);
            border: 1px dashed var(--border-color);
            border-radius: 16px;
            max-width: 500px;
            margin: 40px auto;
            transition: background-color 0.3s, border-color 0.3s;
        }
        .empty-icon { font-size: 64px; margin-bottom: 20px; display: inline-block; }

        footer {
            text-align: center;
            padding: 30px;
            color: var(--text-muted);
            font-size: 14px;
            border-top: 1px solid var(--border-color);
            margin-top: auto;
        }
    </style>
</head>
<body>

    <div id="confirmOrderModal" class="modal-overlay">
        <div class="modal-window">
            <div class="modal-header">
                <h3>📋 Подтверждение заказа</h3>
                <button class="btn-modal-close" onclick="closeConfirmModal()">✕</button>
            </div>

            <div style="text-align: left; margin-bottom: 15px; color: var(--text-muted); font-size: 14px;">
                Пожалуйста, проверьте состав вашего заказа перед переходом к оплате:
            </div>

            <div class="confirm-items-list">
                <?php foreach ($cart_products as $item): ?>
                    <div class="confirm-item-card">
                        <img src="<?= htmlspecialchars($item['final_image']); ?>" class="confirm-item-img" alt="Device" onerror="this.src='img/default.jpg';">
                        <div class="confirm-item-info">
                            <div class="confirm-item-title"><?= htmlspecialchars($item['name']); ?></div>
                            
                            <div class="confirm-badges-flex">
                                <?php if (!empty($item['color'])): ?>
                                    <span class="confirm-badge-color">🎨 Цвет: <?= htmlspecialchars($item['color']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['spec_memory'])): ?>
                                    <span class="confirm-badge-spec">💾 Память: <?= htmlspecialchars($item['spec_memory']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">
                                Количество: <?= $item['quantity']; ?> шт.
                            </div>
                        </div>
                        <div style="font-weight: bold; color: var(--accent-green); font-size:16px;">
                            <?= number_format($item['subtotal'], 0, '.', ' '); ?> ₴
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="modal-total-box">
                <span>Итоговая сумма:</span>
                <span style="font-size: 22px; color: var(--accent-green); font-weight: 800;"><?= number_format($total_price, 0, '.', ' '); ?> ₴</span>
            </div>

            <div class="modal-actions">
                <button class="btn-modal-cancel" onclick="closeConfirmModal()">Изменить</button>
                <a href="checkout.php" class="btn-modal-confirm">💳 Подтвердить и оплатить</a>
            </div>
        </div>
    </div>

    <div id="paymentSuccessModal" class="modal-overlay <?= $show_success_modal ? 'show' : ''; ?>">
        <div class="modal-window" style="text-align: center; max-width: 480px;">
            <div class="success-icon-circle">✓</div>
            <h2 style="color: var(--accent-green); margin-bottom: 10px; font-size: 26px;">Оплата прошла успешно!</h2>
            <p style="color: var(--text-muted); line-height: 1.6; margin-bottom: 25px; font-size: 15px;">
                Ваш заказ успешно принят и отправлен в обработку. Наш менеджер свяжется с вами в ближайшее время для подтверждения доставки!
            </p>
            <a href="index.php" class="btn-order" style="width: 100%; box-sizing: border-box; text-decoration: none;">🏠 Вернуться в каталог</a>
        </div>
    </div>

    <header>
        <div class="header-content">
            <div>
                <h1 style="margin:0;"><a href="index.php" class="logo-link">CyberPhone</a></h1>
            </div>
            <div class="nav-buttons">
                <button id="theme-toggle" class="theme-toggle-btn">🌙</button>
                <a href="index.php" class="nav-link-btn">🏠 В каталог</a>
                <a href="wishlist.php" class="nav-link-btn">❤️ Избранное</a>
            </div>
        </div>
    </header>

    <div class="container">
        <h2>🛒 Ваша корзина</h2>
        
        <?php if (empty($cart_products)): ?>
            <div class="empty-state">
                <span class="empty-icon">🛒</span>
                <h2>Ваша корзина пуста</h2>
                <p style="color: var(--text-muted);">Вы еще не добавили ни одного гаджета. Самое время это исправить!</p>
                <a href="index.php" class="btn-order" style="display: inline-flex; justify-content: center; margin: 0 auto; text-decoration: none;">Перейти в каталог</a>
            </div>
        <?php else: ?>
            <div class="subtitle">Выбранные товары премиум-класса (<?php echo count($cart_products); ?> шт.)</div>

            <div class="cart-list">
                <?php foreach ($cart_products as $item): ?>
                    <div class="cart-item">
                        <a href="product.php?id=<?php echo $item['id']; ?><?php echo $item['is_discounted'] ? '&is_deal=1' : ''; ?>" class="item-image-link" title="Перейти к товару">
                            <img src="<?php echo htmlspecialchars($item['final_image']); ?>" alt="Product" onerror="this.src='img/default.jpg';">
                        </a>
                        
                        <div class="item-details">
                            <h3 style="margin: 0;">
                                <a href="product.php?id=<?php echo $item['id']; ?><?php echo $item['is_discounted'] ? '&is_deal=1' : ''; ?>" class="item-title-link" title="Перейти к товару">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>
                            </h3>
                            
                            <div class="item-badges-flex">
                                <?php if ($item['is_discounted']): ?>
                                    <span class="item-deal-badge">🔥 Скидка дня</span>
                                <?php endif; ?>
                                <?php if (!empty($item['color'])): ?>
                                    <span class="item-color-badge">🎨 Цвет: <?php echo htmlspecialchars($item['color']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['spec_memory'])): ?>
                                    <span class="item-spec-badge">💾 Память: <?php echo htmlspecialchars($item['spec_memory']); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="item-meta">
                                Цена: <?php echo number_format($item['price'], 0, '.', ' '); ?> грн. | 
                                Количество: <?php echo $item['quantity']; ?> шт.
                            </div>
                        </div>
                        
                        <div class="item-price-block">
                            <div>
                                <div class="item-subtotal"><?php echo number_format($item['subtotal'], 0, '.', ' '); ?> грн.</div>
                                <?php if ($item['is_discounted']): ?>
                                    <div class="item-old-price"><?php echo number_format($item['original_price'] * $item['quantity'], 0, '.', ' '); ?> грн.</div>
                                <?php endif; ?>
                            </div>
                            <a href="cart.php?remove_key=<?php echo urlencode($item['cart_key']); ?>" class="btn-remove-single" title="Удалить товар из корзины">✕</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary">
                <div class="total-text">Итого к оплате: <span class="total-price"><?php echo number_format($total_price, 0, '.', ' '); ?> грн.</span></div>
                
                <div class="cart-actions">
                    <a href="cart.php?clear=1" class="btn-clear">Очистить корзину</a>
                    
                    <button type="button" class="btn-order" onclick="openConfirmModal()">Оформить и оплатить</button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> CyberPhone. Все права защищены.</p>
    </footer>

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

    function openConfirmModal() {
        document.getElementById('confirmOrderModal').classList.add('show');
    }

    function closeConfirmModal() {
        document.getElementById('confirmOrderModal').classList.remove('show');
    }

    document.getElementById('confirmOrderModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeConfirmModal();
        }
    });
    </script>
</body>
</html>