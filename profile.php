<?php
session_start();
require_once 'db.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);

// Если пользователь не авторизован — перенаправляем на вход
if ($user_id <= 0) {
    header("Location: login.php");
    exit;
}

$message = '';
$message_type = '';

// ОБРАБОТКА ЗАГРУЗКИ АВАТАРКИ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar_file'])) {
    $file = $_FILES['avatar_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        
        if (in_array($file['type'], $allowed_types)) {
            $upload_dir = 'uploads/avatars/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
            $target_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmt->execute([$target_path, $user_id]);

                    $message = "Аватарка успешно обновлена!";
                    $message_type = "success";
                } catch (PDOException $e) {
                    $message = "Ошибка при сохранении в БД: " . $e->getMessage();
                    $message_type = "error";
                }
            } else {
                $message = "Ошибка при сохранении файла на сервер.";
                $message_type = "error";
            }
        } else {
            $message = "Допустимы только форматы JPG, PNG, WEBP и GIF.";
            $message_type = "error";
        }
    } else {
        $message = "Пожалуйста, выберите файл для загрузки.";
        $message_type = "error";
    }
}

// Получаем актуальные данные пользователя
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

$avatar_path = !empty($user_data['avatar']) && file_exists($user_data['avatar']) ? $user_data['avatar'] : 'img/default.jpg';

// Получаем историю заказов
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет — CyberPhone</title>
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
            --accent-blue: #3b82f6;
            --accent-red: #ef4444;
            --card-img-bg: #1a2234;
        }

        [data-theme="light"] {
            --bg-color: #f3f4f6;
            --panel-bg: #ffffff;
            --border-color: #e5e7eb;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --card-img-bg: #f8fafc;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0; padding: 0;
            transition: background-color 0.3s, color 0.3s;
        }

        header {
            background-color: var(--panel-bg);
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .header-content {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title-group {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .logo-link {
            color: var(--text-main);
            text-decoration: none;
            font-size: 26px;
            font-weight: bold;
        }

        .title-divider {
            width: 1px;
            height: 22px;
            background-color: var(--border-color);
        }

        .page-header-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* КАРТОЧКА ПРОФИЛЯ */
        .profile-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .profile-box {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }

        .avatar-container {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--accent-blue);
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
            background: var(--card-img-bg);
            flex-shrink: 0;
        }

        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .custom-file-btn {
            background: rgba(59, 130, 246, 0.15);
            border: 1px dashed var(--accent-blue);
            color: var(--accent-blue);
            padding: 8px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
            transition: all 0.2s;
        }
        .custom-file-btn:hover { background: rgba(59, 130, 246, 0.25); }

        .btn-save-avatar {
            background: var(--accent-green);
            color: #000;
            border: none;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            display: none;
            margin-top: 8px;
        }

        .alert {
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        .alert.success { background: rgba(0, 230, 118, 0.15); border: 1px solid var(--accent-green); color: var(--accent-green); }
        .alert.error { background: rgba(239, 68, 68, 0.15); border: 1px solid var(--accent-red); color: var(--accent-red); }

        /* ИСТОРИЯ ЗАКАЗОВ */
        .order-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
        }

        .order-status {
            background: rgba(0, 230, 118, 0.15);
            color: var(--accent-green);
            border: 1px solid rgba(0, 230, 118, 0.3);
            padding: 4px 12px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 13px;
        }

        .order-footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn-toggle-details {
            background: var(--card-img-bg);
            border: 1px solid var(--border-color);
            color: var(--accent-blue);
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-toggle-details:hover {
            background: var(--accent-blue);
            color: #ffffff;
            border-color: var(--accent-blue);
        }

        .total-price-tag {
            font-size: 20px;
            font-weight: 800;
            color: var(--accent-green);
        }

        .promo-badge {
            background: rgba(37, 99, 235, 0.15);
            color: var(--accent-blue);
            border: 1px solid rgba(37, 99, 235, 0.3);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .order-details-body {
            display: none;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed var(--border-color);
        }

        .order-details-body.show {
            display: block;
        }

        .delivery-info-box {
            background: var(--card-img-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .items-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .item-row {
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--card-img-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 15px;
        }

        .item-img {
            width: 60px;
            height: 60px;
            object-fit: contain;
            background: var(--panel-bg);
            border-radius: 8px;
            padding: 5px;
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 4px;
        }

        .item-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .badge-color {
            background: rgba(59, 130, 246, 0.15);
            color: var(--accent-blue);
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-spec {
            background: rgba(0, 230, 118, 0.12);
            color: var(--accent-green);
            border: 1px solid rgba(0, 230, 118, 0.3);
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--panel-bg);
            border: 1px dashed var(--border-color);
            border-radius: 16px;
        }
    </style>
</head>
<body>

<header>
    <div class="header-content">
        <div class="header-title-group">
            <a href="index.php" class="logo-link">Cyber<span style="color:#2563eb;">Phone</span></a>
            <div class="title-divider"></div>
            <div class="page-header-title">👤 Личный кабинет <span style="opacity: 0.5;">/</span> Профиль</div>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="index.php" style="color:var(--text-main); text-decoration:none; font-weight:600; background:var(--card-img-bg); border:1px solid var(--border-color); padding:10px 18px; border-radius:20px;">🏠 На главную</a>
            <a href="logout.php" style="color:var(--accent-red); text-decoration:none; font-weight:600; background:var(--card-img-bg); border:1px solid var(--border-color); padding:10px 18px; border-radius:20px;">🚪 Выйти</a>
        </div>
    </div>
</header>

<div class="container">

    <?php if (!empty($message)): ?>
        <div class="alert <?= $message_type; ?>"><?= $message; ?></div>
    <?php endif; ?>

    <!-- КАРТОЧКА ПРОФИЛЯ -->
    <div class="profile-card">
        <div class="profile-box">
            <div class="avatar-container">
                <img id="avatar-preview" src="<?= htmlspecialchars($avatar_path); ?>" alt="Аватарка" class="avatar-img" onerror="this.src='img/default.jpg';">
            </div>

            <div style="flex: 1;">
                <h2 style="margin: 0 0 5px 0; font-size: 24px;"><?= htmlspecialchars($user_data['username'] ?? $_SESSION['username'] ?? 'Пользователь'); ?></h2>
                <p style="color: var(--text-muted); margin: 0 0 15px 0; font-size: 14px;">Email: <?= htmlspecialchars($user_data['email'] ?? '—'); ?></p>

                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <input type="file" name="avatar_file" id="avatar_file" accept="image/*" style="display: none;" onchange="previewAvatar(this)">
                    <label for="avatar_file" class="custom-file-btn">📸 Выбрать фото профиля</label>
                    <div>
                        <button type="submit" id="save-avatar-btn" class="btn-save-avatar">Сохранить фото</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <h2 style="font-size: 20px; margin-bottom: 20px;">📦 История ваших заказов</h2>

    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <h3 style="margin-top:0;">История заказов пуста</h3>
            <p style="color:var(--text-muted);">Вы ещё не совершали покупок в нашем магазине.</p>
            <a href="index.php" style="color:#2563eb; font-weight:bold; text-decoration:none;">Перейти в каталог ➔</a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <?php
            $raw_data = $order['product_list'] ?? '';
            $decoded_data = json_decode($raw_data, true);
            
            $items = [];
            $applied_promo = null;
            $has_valid_items = false;

            if (isset($decoded_data['items']) && is_array($decoded_data['items'])) {
                $items = $decoded_data['items'];
                $applied_promo = $decoded_data['promo'] ?? null;
            } elseif (is_array($decoded_data)) {
                $items = $decoded_data;
            }

            if (is_array($items)) {
                foreach ($items as $check_item) {
                    if (!empty($check_item['name'])) {
                        $has_valid_items = true;
                        break;
                    }
                }
            }
            ?>
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <strong>Заказ #<?= $order['id']; ?></strong> 
                        <span style="color:var(--text-muted); font-size:13px; margin-left:12px;"><?= $order['created_at'] ?? ''; ?></span>
                    </div>
                    <span class="order-status"><?= htmlspecialchars($order['status'] ?? 'На сборке'); ?></span>
                </div>

                <div class="order-footer-row">
                    <button class="btn-toggle-details" onclick="toggleDetails(<?= $order['id']; ?>, this)">📦 Детали заказа ▼</button>
                    
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <?php if (!empty($applied_promo)): ?>
                            <span class="promo-badge">
                                🎟️ Промокод: <strong><?= htmlspecialchars($applied_promo); ?></strong>
                            </span>
                        <?php endif; ?>

                        <div class="total-price-tag">
                            Итого: <?= number_format((float)$order['total_price'], 0, '.', ' '); ?> грн.
                        </div>
                    </div>
                </div>

                <div class="order-details-body" id="details-<?= $order['id']; ?>">
                    <div class="delivery-info-box">
                        <div><strong>👤 Получатель:</strong> <?= htmlspecialchars($order['customer_name'] ?? 'Не указано'); ?> (<?= htmlspecialchars($order['customer_phone'] ?? ''); ?>)</div>
                        <div><strong>📍 Доставка:</strong> г. <?= htmlspecialchars($order['delivery_city'] ?? ''); ?>, отделение № <?= htmlspecialchars($order['delivery_post'] ?? ''); ?></div>
                    </div>

                    <div style="font-weight:600; margin-bottom:12px;">Состав заказа:</div>

                    <div class="items-grid">
                        <?php if ($has_valid_items): ?>
                            <?php foreach ($items as $item): ?>
                                <?php if (empty($item['name'])) continue; ?>
                                <div class="item-row">
                                    <img src="<?= htmlspecialchars(!empty($item['image']) ? $item['image'] : 'img/default.jpg'); ?>" class="item-img" alt="Product" onerror="this.src='img/default.jpg';">
                                    <div class="item-info">
                                        <div class="item-name"><?= htmlspecialchars($item['name']); ?></div>
                                        <div class="item-badges">
                                            <?php if (!empty($item['color'])): ?>
                                                <span class="badge-color">🎨 Цвет: <?= htmlspecialchars($item['color']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($item['memory'])): ?>
                                                <span class="badge-spec">💾 Память: <?= htmlspecialchars($item['memory']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-weight:bold; color:var(--accent-green); font-size:16px;">
                                            <?= number_format((float)($item['price'] ?? 0), 0, '.', ' '); ?> грн.
                                        </div>
                                        <div style="font-size:12px; color:var(--text-muted);">
                                            <?= (int)($item['quantity'] ?? 1); ?> шт.
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="item-row">
                                <div class="item-info">
                                    <div class="item-name" style="font-size:14px; font-weight:normal; line-height:1.5;">
                                        <?= htmlspecialchars($raw_data); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatar-preview').src = e.target.result;
            document.getElementById('save-avatar-btn').style.display = 'inline-block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function toggleDetails(orderId, btn) {
    const detailsBox = document.getElementById('details-' + orderId);
    if (detailsBox.classList.contains('show')) {
        detailsBox.classList.remove('show');
        btn.textContent = '📦 Детали заказа ▼';
    } else {
        detailsBox.classList.add('show');
        btn.textContent = '📦 Свернуть детали ▲';
    }
}
</script>

</body>
</html>