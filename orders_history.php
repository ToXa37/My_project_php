<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>История заказов | CyberPhone</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .history-container { max-width: 800px; margin: 40px auto; min-height: 65vh; }
        .order-card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border-left: 5px solid #34495e; }
        .order-meta { display: flex; justify-content: space-between; font-size: 0.9rem; color: #7f8c8d; margin-bottom: 10px; border-bottom: 1px dashed #eee; padding-bottom: 10px; }
        .order-goods { font-size: 1.1rem; color: #2c3e50; font-weight: 500; margin-bottom: 15px; }
        .order-footer { display: flex; justify-content: space-between; align-items: center; }
        .order-price { font-size: 1.2rem; font-weight: bold; color: #2c3e50; }
        
        .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; text-transform: uppercase; }
        .status-assembly { background-color: #ffeaa7; color: #d63031; }
        .status-sent { background-color: #74b9ff; color: #0984e3; }
        .status-delivered { background-color: #55efc4; color: #00b894; }
    </style>
</head>
<body>

    <header>
        <div class="container">
            <h1>📋 История ваших заказов</h1>
            <p><a href="index.php" style="color: white; text-decoration: underline;">← Вернуться на главную</a></p>
        </div>
    </header>

    <main class="container history-container">
        <?php if (empty($orders)): ?>
            <div style="text-align: center; margin-top: 60px;">
                <h2>Вы ещё ничего не заказывали 🧐</h2>
                <p>Ваша история покупок пуста. Самое время это исправить!</p>
                <a href="index.php" class="btn" style="display: inline-block; margin-top: 20px;">В каталог товаров</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): 
                $status_class = 'status-assembly';
                if ($order['status'] === 'Отправлен') { $status_class = 'status-sent'; }
                if ($order['status'] === 'Доставлен') { $status_class = 'status-delivered'; }
            ?>
                <div class="order-card">
                    <div class="order-meta">
                        <span><strong>Заказ №<?php echo $order['id']; ?></strong></span>
                        <span>Дата: <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></span>
                    </div>
                    <div class="order-goods">
                        📦 <?php echo htmlspecialchars($order['product_list']); ?>
                    </div>
                    <div class="order-footer">
                        <div class="order-price">Сумма: <?php echo number_format($order['total_price'], 0, '.', ' '); ?> грн.</div>
                        <div>
                            <span class="status-badge <?php echo $status_class; ?>">
                                ⏰ <?php echo htmlspecialchars($order['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> CyberPhone. Все права защищены.</p>
        </div>
    </footer>

</body>
</html>