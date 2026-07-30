<?php
session_start();

header('Content-Type: application/json');

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$color = isset($_POST['color']) ? trim($_POST['color']) : '';
$ram = isset($_POST['ram']) ? trim($_POST['ram']) : '';
$storage = isset($_POST['storage']) ? trim($_POST['storage']) : '';
$price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
$image = isset($_POST['image']) ? trim($_POST['image']) : '';

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Неверный ID товара']);
    exit;
}

// Если цена не была передана через JS, запрашиваем из базы
if ($price <= 0) {
    try {
        $db = new PDO('mysql:host=localhost;dbname=tech_shop;charset=utf8', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $stmt = $db->prepare("SELECT price, name, image_url, colors, ram, storage_options FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $prod = $stmt->fetch();

        if ($prod) {
            $price = (float)$prod['price'];
            
            // Если дефолтные значения не пришли, заполняем из БД
            if (empty($image)) {
                $image = $prod['image_url'] ?? 'img/default.jpg';
            }
            if (empty($ram)) {
                $ram_arr = !empty($prod['ram']) ? explode(',', $prod['ram']) : ['12 ГБ'];
                $ram = trim($ram_arr[0]);
            }
            if (empty($storage)) {
                $st_arr = !empty($prod['storage_options']) ? explode(',', $prod['storage_options']) : ['256 ГБ'];
                $storage = trim($st_arr[0]);
            }
            if (empty($color)) {
                $color = 'Black';
            }
        }
    } catch (PDOException $e) {
        // Игнорируем ошибку подключения
    }
}

if (!isset($_SESSION['cart_details'])) {
    $_SESSION['cart_details'] = [];
}
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Уникальный ключ позиции (чтобы учитывать опции)
$item_key = $product_id . '_' . md5($color . '_' . $ram . '_' . $storage . '_' . $price);

if (isset($_SESSION['cart_details'][$item_key])) {
    $_SESSION['cart_details'][$item_key]['quantity'] += 1;
} else {
    $_SESSION['cart_details'][$item_key] = [
        'product_id' => $product_id,
        'color'      => $color,
        'ram'        => $ram,
        'storage'    => $storage,
        'price'      => $price,
        'image'      => $image,
        'quantity'   => 1
    ];
}

// Синхронизируем со старым массивом
$_SESSION['cart'][$product_id] = ($_SESSION['cart'][$product_id] ?? 0) + 1;

// Подсчитываем общее количество
$total_count = 0;
foreach ($_SESSION['cart_details'] as $item) {
    $total_count += $item['quantity'];
}

echo json_encode([
    'success' => true,
    'total_count' => $total_count
]);