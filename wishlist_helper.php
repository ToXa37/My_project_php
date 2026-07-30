<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

if (isset($_GET['ids']) && !empty($_GET['ids'])) {
    global $pdo;

    if (!$pdo) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Переменная pdo не инициализирована в db.php"]);
        exit;
    }

    $raw_ids = trim($_GET['ids']);
    $id_strings = explode(',', $raw_ids);
    $ids = array_map('intval', $id_strings);

    if (empty($ids)) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Вспомогательная функция для определения суффикса (копия из product.php)
    function getHelperColorSuffix($color_name) {
        $clean = mb_strtolower(trim($color_name));
        if (strpos($clean, 'red') !== false || strpos($clean, 'красн') !== false) return 'red';
        if (strpos($clean, 'yellow') !== false || strpos($clean, 'желт') !== false) return 'yellow';
        if (strpos($clean, 'orange') !== false || strpos($clean, 'оранж') !== false || strpos($clean, 'sunset') !== false || strpos($clean, 'canyon') !== false) return 'orange';
        if (strpos($clean, 'startrail') !== false || strpos($clean, 'sapphire') !== false) return 'blue';
        if (strpos($clean, 'vulcan') !== false || strpos($clean, 'cosmos') !== false || strpos($clean, 'umber') !== false || strpos($clean, 'charcoal') !== false) return 'black';
        if (strpos($clean, 'moonlight') !== false || strpos($clean, 'silk') !== false) return 'white';
        if (strpos($clean, 'liquid') !== false) return 'liquidsilver';
        if (strpos($clean, 'ocean') !== false || strpos($clean, 'blue') !== false || strpos($clean, 'син') !== false || strpos($clean, 'голуб') !== false) return 'blue';
        if (strpos($clean, 'green') !== false || strpos($clean, 'зелен') !== false || strpos($clean, 'mint') !== false) return 'green';
        if (strpos($clean, 'black') !== false || strpos($clean, 'черн') !== false || strpos($clean, 'dark') !== false || strpos($clean, 'midnight') !== false) return 'black';
        if (strpos($clean, 'white') !== false || strpos($clean, 'бел') !== false) return 'white';
        return 'gray';
    }

    try {
        // Вытягиваем также цвета (colors) из базы, чтобы узнать дефолтный цвет
        $sql = "SELECT id, name, brand, price, description, colors, image_url FROM products WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $products_raw = $stmt->fetchAll();

        $response_products = [];

        foreach ($products_raw as $prod) {
            $model_clean = mb_strtolower(str_replace(' ', '', $prod['name']));
            
            // Определяем первый доступный цвет товара
            $colors_arr = !empty($prod['colors']) ? explode(', ', $prod['colors']) : ['gray'];
            $first_color = $colors_arr[0];
            $suffix = getHelperColorSuffix($first_color);

            // Ищем существующую картинку
            $img_path = "img/{$model_clean}_{$suffix}.jpg";
            if (!file_exists($img_path)) {
                $img_path = "img/{$model_clean}_gray.jpg";
            }
            if (!file_exists($img_path)) {
                $img_path = "img/{$model_clean}.jpg";
            }
            if (!file_exists($img_path)) {
                $img_path = (!empty($prod['image_url']) && file_exists($prod['image_url'])) ? $prod['image_url'] : 'img/default.jpg';
            }

            $response_products[] = [
                'id'          => (int)$prod['id'],
                'name'        => $prod['name'],
                'brand'       => $prod['brand'],
                'price'       => (float)$prod['price'],
                'description' => $prod['description'],
                'image'       => $img_path
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response_products, JSON_UNESCAPED_UNICODE);
        exit;

    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(["error" => "Ошибка: " . $e->getMessage()]);
        exit;
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}