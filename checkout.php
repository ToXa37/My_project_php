<?php
require_once 'db.php';
session_start();

if ((empty($_SESSION['cart']) && empty($_SESSION['cart_details'])) || !isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// --- ОБРАБОТКА AJAX-ЗАПРОСА ДЛЯ ПРОВЕРКИ ПРОМОКОДА ---
if (isset($_GET['action']) && $_GET['action'] === 'check_promo') {
    header('Content-Type: application/json; charset=utf-8');
    
    $code = strtoupper(trim($_GET['code'] ?? ''));
    
    $valid_promos = [
        'CYBER3'   => ['type' => 'percent', 'val' => 0.03, 'label' => '3%'],
        'CYBER5'   => ['type' => 'percent', 'val' => 0.05, 'label' => '5%'],
        'CYBER500' => ['type' => 'fixed',   'val' => 500,  'label' => '500 грн']
    ];

    if (!array_key_exists($code, $valid_promos)) {
        echo json_encode(['success' => false, 'message' => '❌ Неверный или истекший промокод']);
        exit;
    }

    // Ищем использование промокода и в JSON-формате ("promo":"CODE"), и в старом строковом формате ([Промокод: CODE])
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND (product_list LIKE ? OR product_list LIKE ?)");
    $check_stmt->execute([$user_id, '%"promo":"' . $code . '"%', "%[Промокод: {$code}]%"]);
    $used_count = $check_stmt->fetchColumn();

    if ($used_count > 0) {
        echo json_encode(['success' => false, 'message' => '⚠️ Вы уже активировали этот промокод ранее!']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'code'    => $code,
        'type'    => $valid_promos[$code]['type'],
        'val'     => $valid_promos[$code]['val'],
        'label'   => $valid_promos[$code]['label']
    ]);
    exit;
}

$total_price = 0;
$items_text = [];
$order_items_data = [];

// 1. Проверяем детализированную корзину
if (!empty($_SESSION['cart_details']) && is_array($_SESSION['cart_details'])) {
    $p_ids = array_unique(array_column($_SESSION['cart_details'], 'product_id'));

    if (!empty($p_ids)) {
        $in = implode(',', array_fill(0, count($p_ids), '?'));
        $stmt = $pdo->prepare("SELECT id, name, price, image_url, ram, storage_options FROM products WHERE id IN ($in)");
        $stmt->execute(array_values($p_ids));
        $db_products = $stmt->fetchAll(PDO::FETCH_UNIQUE);

        foreach ($_SESSION['cart_details'] as $item) {
            $pid = $item['product_id'];
            if (isset($db_products[$pid])) {
                $product = $db_products[$pid];
                
                $price = !empty($item['price']) && $item['price'] > 0 ? (float)$item['price'] : (float)$product['price'];
                $quantity = (int)($item['quantity'] ?? 1);
                
                $total_price += $price * $quantity;

                $spec_info = [];
                if (!empty($item['color'])) $spec_info[] = "Цвет: " . $item['color'];
                
                $ram = !empty($item['ram']) ? $item['ram'] : ($product['ram'] ?? '');
                $storage = !empty($item['storage']) ? $item['storage'] : ($product['storage_options'] ?? '');
                if (!empty($ram) || !empty($storage)) {
                    $spec_info[] = "Память: " . trim($ram . ' / ' . $storage, ' /');
                }

                $spec_str = !empty($spec_info) ? ' [' . implode(', ', $spec_info) . ']' : '';
                $items_text[] = $product['name'] . $spec_str . " (" . $quantity . " шт.)";

                // Данные для детального отображения в профиле
                $order_items_data[] = [
                    'id'       => $pid,
                    'name'     => $product['name'],
                    'price'    => $price,
                    'quantity' => $quantity,
                    'image'    => !empty($item['image']) ? $item['image'] : ($product['image_url'] ?? 'img/default.jpg'),
                    'color'    => $item['color'] ?? '',
                    'memory'   => trim($ram . ' / ' . $storage, ' /')
                ];
            }
        }
    }
} 
// 2. Запасной вариант для простой корзины
elseif (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $in = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("SELECT id, name, price, image_url, ram, storage_options FROM products WHERE id IN ($in)");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    foreach ($products as $product) {
        $p_id = $product['id'];
        $quantity = (int)$_SESSION['cart'][$p_id];
        $price = (float)$product['price'];
        $total_price += $price * $quantity;
        $items_text[] = $product['name'] . " (" . $quantity . " шт.)";

        $ram = $product['ram'] ?? '';
        $storage = $product['storage_options'] ?? '';

        $order_items_data[] = [
            'id'       => $p_id,
            'name'     => $product['name'],
            'price'    => $price,
            'quantity' => $quantity,
            'image'    => $product['image_url'] ?? 'img/default.jpg',
            'color'    => '',
            'memory'   => trim($ram . ' / ' . $storage, ' /')
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name  = trim($_POST['customer_name']);
    $customer_phone = trim($_POST['customer_phone']);
    $customer_email = trim($_POST['customer_email']);
    $delivery_city  = trim($_POST['delivery_city']);
    $delivery_post  = trim($_POST['delivery_post']);
    
    $final_price = isset($_POST['final_price_input']) ? (float)$_POST['final_price_input'] : $total_price;
    $promo_code  = isset($_POST['promo_code_input']) ? strtoupper(trim($_POST['promo_code_input'])) : '';

    // Серверная повторная проверка промокода перед оформлением
    if (!empty($promo_code)) {
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND (product_list LIKE ? OR product_list LIKE ?)");
        $check_stmt->execute([$user_id, '%"promo":"' . $promo_code . '"%', "%[Промокод: {$promo_code}]%"]);
        if ($check_stmt->fetchColumn() > 0) {
            // Если промокод уже использовался ранее, сбрасываем скидку к исходной
            $final_price = $total_price;
            $promo_code = '';
        }
    }

    // Формируем структуру с товарами и промокодом
    $order_data = [
        'items' => $order_items_data,
        'promo' => !empty($promo_code) ? $promo_code : null
    ];

    $product_list = json_encode($order_data, JSON_UNESCAPED_UNICODE);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_phone, customer_email, delivery_city, delivery_post, product_list, total_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $customer_name, $customer_phone, $customer_email, $delivery_city, $delivery_post, $product_list, $final_price]);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, customer_phone, delivery_city, delivery_post, product_list, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $customer_name, $customer_phone . " (Email: " . $customer_email . ")", $delivery_city, $delivery_post, $product_list, $final_price]);
    }
    
    unset($_SESSION['cart']);
    unset($_SESSION['cart_details']);

    header('Location: cart.php?payment_success=1');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оформление заказа | CyberPhone Премиум</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
    <style>
        :root {
            --bg-color: #0b0f19;
            --panel-bg: rgba(20, 30, 54, 0.45);
            --panel-solid: #131926;
            --border-color: rgba(255, 255, 255, 0.08);
            --input-border: rgba(31, 41, 61, 0.8);
            --input-bg: #070a12;
            --text-main: #ffffff;
            --text-muted: #8a99ad;
            --card-img-bg: rgba(7, 10, 18, 0.6);
            --autofill-bg: #131926;
            --radial-gradient-1: radial-gradient(circle at 50% 30%, rgba(37, 99, 235, 0.12) 0%, transparent 50%), radial-gradient(circle at 50% 70%, rgba(0, 230, 118, 0.04) 0%, transparent 50%);
        }

        [data-theme="light"] {
            --bg-color: #f3f4f6;
            --panel-bg: #ffffff;
            --panel-solid: #ffffff;
            --border-color: #e5e7eb;
            --input-border: #cbd5e1;
            --input-bg: #f8fafc;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --card-img-bg: #f8fafc;
            --autofill-bg: #ffffff;
            --radial-gradient-1: radial-gradient(circle at 50% 30%, rgba(37, 99, 235, 0.05) 0%, transparent 60%);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            transition: background-color 0.3s, color 0.3s;
            background-image: var(--radial-gradient-1);
        }
        
        .checkout-layout { 
            display: flex; 
            gap: 40px; 
            max-width: 1100px; 
            margin: 40px auto; 
            padding: 0 20px;
            box-sizing: border-box;
            flex-wrap: wrap;
        }
        
        .checkout-box { 
            background: var(--panel-bg); 
            padding: 35px; 
            border-radius: 24px; 
            border: 1px solid var(--border-color); 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); 
            flex: 1.2; 
            min-width: 320px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-sizing: border-box;
            transition: background-color 0.3s, border-color 0.3s;
        }
        
        .checkout-box.right-panel {
            flex: 0.8;
            background: var(--panel-solid);
        }

        .checkout-box h3 { 
            margin-top: 0; 
            margin-bottom: 30px; 
            color: var(--text-main); 
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: 0.5px;
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: var(--text-muted); 
            font-size: 0.82rem; 
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }
        
        .input-icon {
            position: absolute;
            left: 18px;
            color: var(--text-muted);
            font-size: 1.1rem;
            pointer-events: none;
            z-index: 2;
        }
        
        /* Единый стиль для инпутов и селектов */
        .form-input, .checkout-select { 
            width: 100%; 
            padding: 14px 14px 14px 48px; 
            background-color: var(--input-bg) !important;
            border: 1px solid var(--input-border); 
            border-radius: 12px; 
            box-sizing: border-box; 
            font-size: 0.95rem; 
            color: var(--text-main) !important;
            outline: none;
            transition: all 0.3s ease; 
        }

        .checkout-select {
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%238a99ad' viewBox='0 0 16 16'><path d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/></svg>") !important;
            background-repeat: no-repeat !important;
            background-position: calc(100% - 20px) center !important;
        }
        
        .form-input:focus, .checkout-select:focus { 
            border-color: #3b82f6; 
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.25); 
        }

        /* Полное устранение высветления от автозаполнения браузера */
        .form-input:-webkit-autofill,
        .form-input:-webkit-autofill:hover, 
        .form-input:-webkit-autofill:focus,
        .checkout-select:-webkit-autofill {
            -webkit-text-fill-color: var(--text-main) !important;
            -webkit-box-shadow: 0 0 0px 1000px var(--autofill-bg) inset !important;
            transition: background-color 5000s ease-in-out 0s !important;
        }

        .checkout-select option {
            background-color: var(--panel-solid);
            color: var(--text-main);
            padding: 12px;
        }

        .credit-card {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 25px; 
            border-radius: 20px; 
            color: white;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4); 
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .card-chip {
            width: 42px;
            height: 32px;
            background: linear-gradient(135deg, #e2e8f0, #94a3b8);
            border-radius: 6px;
            margin-bottom: 25px;
        }
        
        .card-label { 
            font-size: 0.72rem; 
            text-transform: uppercase; 
            color: #8a99ad; 
            margin-bottom: 8px; 
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .card-input { 
            background: rgba(7, 10, 18, 0.5); 
            border: 1px solid rgba(255, 255, 255, 0.1); 
            border-radius: 10px; 
            padding: 12px 14px; 
            color: white; 
            font-size: 1.05rem; 
            width: 100%; 
            box-sizing: border-box; 
            margin-bottom: 20px; 
            font-family: 'Courier New', monospace;
            letter-spacing: 2.5px;
            transition: all 0.2s;
            outline: none;
        }
        
        .card-input:focus {
            background: rgba(7, 10, 18, 0.7);
            border-color: #3b82f6;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.2);
        }
        
        .card-row { display: flex; gap: 20px; }
        .card-col { flex: 1; }

        .promo-box {
            background: var(--card-img-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 20px;
            box-sizing: border-box;
        }

        .promo-input-group {
            display: flex;
            gap: 8px;
            width: 100%;
            box-sizing: border-box;
        }

        .promo-input {
            flex: 1;
            min-width: 0;
            padding: 12px 14px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 10px;
            color: var(--text-main);
            font-size: 0.9rem;
            outline: none;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 1px;
            box-sizing: border-box;
        }

        .promo-btn {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 0.9rem;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
            box-sizing: border-box;
        }

        .promo-btn:hover {
            background: #1d4ed8;
            box-shadow: 0 0 12px rgba(37, 99, 235, 0.4);
        }

        .promo-msg {
            font-size: 0.85rem;
            margin-top: 10px;
            display: none;
            font-weight: 600;
        }

        .promo-msg.success { color: #00e676; display: block; }
        .promo-msg.error { color: #ef4444; display: block; }

        .price-summary-box {
            background: var(--card-img-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 25px;
            transition: background-color 0.3s, border-color 0.3s;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.95rem;
            color: var(--text-muted);
        }

        .discount-row {
            display: none;
            color: #00e676;
            font-weight: 600;
        }
        
        .pay-amount { 
            font-size: 1.8rem; 
            color: #00e676; 
            font-weight: 700; 
            text-align: center; 
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px dashed var(--input-border);
            text-shadow: 0 0 15px rgba(0, 230, 118, 0.2);
        }

        .old-price-line {
            text-decoration: line-through;
            color: var(--text-muted);
            font-size: 1.2rem;
            display: block;
            margin-bottom: -5px;
        }
        
        .btn-pay { 
            width: 100%; 
            background: #00e676; 
            color: #000000; 
            border: none; 
            padding: 16px; 
            border-radius: 12px; 
            font-size: 1.1rem; 
            font-weight: 700; 
            cursor: pointer; 
            box-shadow: 0 4px 15px rgba(0, 230, 118, 0.15);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); 
        }
        
        .btn-pay:hover { 
            background: #00c853; 
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 230, 118, 0.4);
        }

        .loader-overlay { 
            position: fixed; 
            top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(7, 10, 18, 0.95); 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: center; 
            z-index: 9999; 
            display: none; 
            backdrop-filter: blur(10px);
        }
        
        .spinner { 
            width: 55px; 
            height: 55px; 
            border: 4px solid rgba(255, 255, 255, 0.05); 
            border-top: 4px solid #00e676; 
            border-radius: 50%; 
            animation: spin 0.8s cubic-bezier(0.4, 0, 0.2, 1) infinite; 
            margin-bottom: 25px; 
            box-shadow: 0 0 15px rgba(0, 230, 118, 0.2);
        }
        
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .loader-text { 
            font-size: 1.15rem; 
            font-weight: 600; 
            color: #ffffff; 
        }
    </style>
</head>
<body>

    <div id="paymentLoader" class="loader-overlay">
        <div class="spinner"></div>
        <div class="loader-text">Проверка данных доставки и адреса склада...</div>
    </div>

    <header style="background: var(--panel-solid); padding: 30px 0; text-align: center; border-bottom: 1px solid var(--border-color); transition: background-color 0.3s, border-color 0.3s;">
        <div class="container">
            <h1 style="margin: 0; font-size: 1.8rem; font-weight: 700; color: var(--text-main); letter-spacing: 0.5px;">🛡️ Безопасное оформление заказа</h1>
            <p style="margin: 10px 0 0 0;"><a href="cart.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: color 0.2s;">← Вернуться в корзину</a></p>
        </div>
    </header>

    <form id="checkoutForm" method="POST" onsubmit="processCheckout(event)">
        <input type="hidden" id="final_price_input" name="final_price_input" value="<?php echo $total_price; ?>">
        <input type="hidden" id="promo_code_input" name="promo_code_input" value="">

        <div class="checkout-layout">
            
            <div class="checkout-box">
                <h3>👤 Данные получателя и доставка</h3>
                
                <div class="form-group">
                    <label>ФИО Получателя</label>
                    <div class="input-wrapper">
                        <span class="input-icon">👤</span>
                        <input type="text" name="customer_name" class="form-input" placeholder="Иванов Иван Иванович" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Контактный телефон</label>
                    <div class="input-wrapper">
                        <span class="input-icon">📞</span>
                        <input type="tel" name="customer_phone" class="form-input" placeholder="+38 (0XX) XXX-XX-XX" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Электронная почта (Email)</label>
                    <div class="input-wrapper">
                        <span class="input-icon">✉️</span>
                        <input type="email" name="customer_email" class="form-input" placeholder="example@gmail.com" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Город доставки</label>
                    <div class="input-wrapper">
                        <span class="input-icon">📍</span>
                        <select name="delivery_city" class="checkout-select" required>
                            <option value="" disabled selected>Выберите ваш город...</option>
                            <option value="Александрия">Александрия</option>
                            <option value="Алчевск">Алчевск</option>
                            <option value="Бахмут">Бахмут</option>
                            <option value="Белая Церковь">Белая Церковь</option>
                            <option value="Бердичев">Бердичев</option>
                            <option value="Бердянск">Бердянск</option>
                            <option value="Бровары">Бровары</option>
                            <option value="Винница">Винница</option>
                            <option value="Горловка">Горловка</option>
                            <option value="Днепр">Днепр</option>
                            <option value="Донецк">Донецк</option>
                            <option value="Евпатория">Евпатория</option>
                            <option value="Житомир">Житомир</option>
                            <option value="Запорожье">Запорожье</option>
                            <option value="Ивано-Франковск">Ивано-Франковск</option>
                            <option value="Измаил">Измаил</option>
                            <option value="Каменец-Подольский">Каменец-Подольский</option>
                            <option value="Каменское">Каменское</option>
                            <option value="Керчь">Керчь</option>
                            <option value="Киев">Киев</option>
                            <option value="Ковель">Ковель</option>
                            <option value="Краматорск">Краматорск</option>
                            <option value="Кременчуг">Кременчуг</option>
                            <option value="Кривой Рог">Кривой Рог</option>
                            <option value="Кропивницкий">Кропивницкий</option>
                            <option value="Луганск">Луганск</option>
                            <option value="Луцк">Луцк</option>
                            <option value="Львов">Львов</option>
                            <option value="Макеевка">Макеевка</option>
                            <option value="Мариуполь">Мариуполь</option>
                            <option value="Мелитополь">Мелитополь</option>
                            <option value="Мукачево">Мукачево</option>
                            <option value="Николаев">Николаев</option>
                            <option value="Никополь">Никополь</option>
                            <option value="Одесса">Одесса</option>
                            <option value="Павлоград">Павлоград</option>
                            <option value="Полтава">Полтава</option>
                            <option value="Ровно">Ровно</option>
                            <option value="Севастополь">Севастополь</option>
                            <option value="Северодонецк">Северодонецк</option>
                            <option value="Симферополь">Симферополь</option>
                            <option value="Славянск">Славянск</option>
                            <option value="Сумы">Сумы</option>
                            <option value="Тернополь">Тернополь</option>
                            <option value="Ужгород">Ужгород</option>
                            <option value="Умань">Умань</option>
                            <option value="Феодосия">Феодосия</option>
                            <option value="Харьков">Харьков</option>
                            <option value="Херсон">Херсон</option>
                            <option value="Хмельницкий">Хмельницкий</option>
                            <option value="Черкассы">Черкассы</option>
                            <option value="Чернигов">Чернигов</option>
                            <option value="Черновцы">Черновцы</option>
                            <option value="Шостка">Шостка</option>
                            <option value="Ялта">Ялта</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Отделение Новой Почты</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🏢</span>
                        <input type="text" name="delivery_post" class="form-input" placeholder="№ 5, ул. Центральная, 12" required>
                    </div>
                </div>
            </div>
            
            <div class="checkout-box right-panel">
                <h3>💳 Способ оплаты</h3>
                
                <div class="credit-card">
                    <div class="card-chip"></div>
                    
                    <div class="card-label">Номер карты</div>
                    <input type="text" class="card-input" placeholder="0000 0000 0000 0000" maxlength="19" required oninput="formatCardNumber(this)">

                    <div class="card-row">
                        <div class="card-col">
                            <div class="card-label">Срок действия</div>
                            <input type="text" class="card-input" placeholder="ММ/ГГ" maxlength="5" required oninput="formatExpiry(this)">
                        </div>
                        <div class="card-col">
                            <div class="card-label">CVC код</div>
                            <input type="password" class="card-input" placeholder="***" maxlength="3" required>
                        </div>
                    </div>
                </div>

                <!-- ФОРМА ВВОДА ПРОМОКОДА -->
                <div class="promo-box">
                    <label style="display:block; margin-bottom:8px; font-weight:600; font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Промокод на скидку</label>
                    <div class="promo-input-group">
                        <input type="text" id="promoInput" class="promo-input" placeholder="Например: CYBER5">
                        <button type="button" class="promo-btn" onclick="applyPromo()">Применить</button>
                    </div>
                    <div id="promoMsg" class="promo-msg"></div>
                </div>
                
                <div class="price-summary-box">
                    <div class="price-row">
                        <span>Товары в заказе:</span>
                        <span style="font-weight: 600; color: var(--text-main);"><?php echo count($order_items_data); ?> шт.</span>
                    </div>
                    <div class="price-row">
                        <span>Доставка Новой Почтой:</span>
                        <span style="color: #00e676; font-weight: 600;">Бесплатно</span>
                    </div>
                    <div class="price-row discount-row" id="discountRow">
                        <span>Скидка по промокоду:</span>
                        <span id="discountVal">-0 грн.</span>
                    </div>
                    <div class="pay-amount">
                        <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500; display: block; margin-bottom: 5px; text-transform: uppercase;">Всего к оплате:</span>
                        <span id="oldPriceDisplay" class="old-price-line" style="display:none;"></span>
                        <span id="finalPriceDisplay"><?php echo number_format($total_price, 0, '.', ' '); ?> грн.</span>
                    </div>
                </div>

                <button type="submit" class="btn-pay">Оплатить заказ</button>
            </div>

        </div>
    </form>

    <script>
    const originalTotal = <?php echo (float)$total_price; ?>;

    function applyPromo() {
        const input = document.getElementById('promoInput');
        const msg = document.getElementById('promoMsg');
        const discountRow = document.getElementById('discountRow');
        const discountVal = document.getElementById('discountVal');
        const finalPriceDisplay = document.getElementById('finalPriceDisplay');
        const oldPriceDisplay = document.getElementById('oldPriceDisplay');
        const finalPriceInput = document.getElementById('final_price_input');
        const promoCodeInput = document.getElementById('promo_code_input');

        const code = input.value.trim().toUpperCase();

        if (!code) return;

        fetch(`checkout.php?action=check_promo&code=${encodeURIComponent(code)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    let discountAmount = 0;

                    if (data.type === 'percent') {
                        discountAmount = Math.round(originalTotal * data.val);
                    } else if (data.type === 'fixed') {
                        discountAmount = data.val;
                    }

                    if (discountAmount > originalTotal) discountAmount = originalTotal;

                    const newTotal = originalTotal - discountAmount;

                    discountRow.style.display = 'flex';
                    discountVal.textContent = `- ${discountAmount.toLocaleString('ru-RU')} грн. (${data.label})`;

                    oldPriceDisplay.style.display = 'block';
                    oldPriceDisplay.textContent = `${originalTotal.toLocaleString('ru-RU')} грн.`;
                    finalPriceDisplay.textContent = `${newTotal.toLocaleString('ru-RU')} грн.`;

                    finalPriceInput.value = newTotal;
                    promoCodeInput.value = data.code;

                    msg.className = 'promo-msg success';
                    msg.textContent = `✓ Промокод ${data.code} применен! Скидка ${data.label}`;
                } else {
                    discountRow.style.display = 'none';
                    oldPriceDisplay.style.display = 'none';
                    finalPriceDisplay.textContent = `${originalTotal.toLocaleString('ru-RU')} грн.`;
                    finalPriceInput.value = originalTotal;
                    promoCodeInput.value = '';

                    msg.className = 'promo-msg error';
                    msg.textContent = data.message;
                }
            })
            .catch(() => {
                msg.className = 'promo-msg error';
                msg.textContent = '❌ Ошибка проверки промокода.';
            });
    }

    function formatCardNumber(input) {
        let value = input.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
        let matches = value.match(/\d{4,16}/g);
        let match = matches && matches[0] || '';
        let parts = [];
        for (let i = 0, len = match.length; i < len; i += 4) { parts.push(match.substring(i, i + 4)); }
        input.value = parts.length > 0 ? parts.join(' ') : value;
    }

    function formatExpiry(input) {
        let value = input.value.replace(/\//g, '').replace(/[^0-9]/gi, '');
        input.value = value.length >= 2 ? value.substring(0, 2) + '/' + value.substring(2, 4) : value;
    }

    function processCheckout(event) {
        event.preventDefault();
        let loader = document.getElementById('paymentLoader');
        let loaderText = loader.querySelector('.loader-text');
        
        loader.style.display = 'flex';
        
        setTimeout(() => {
            loaderText.innerText = 'Связь с банковским шлюзом Visa/Mastercard...';
        }, 1200);

        setTimeout(() => {
            loaderText.innerText = 'Проведение транзакции и шифрование данных...';
        }, 2400);

        setTimeout(() => {
            document.getElementById('checkoutForm').submit();
        }, 3600);
    }
    </script>
</body>
</html>