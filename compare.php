<?php
session_start();
require_once 'db.php';

// Получаем ID для сравнения
$ids = isset($_GET['ids']) ? array_map('intval', explode(',', $_GET['ids'])) : [];

$products = [];
if (!empty($ids)) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($in)");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сравнение товаров — CyberPhone</title>
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
        }

        [data-theme="light"] {
            --bg-color: #f3f4f6;
            --panel-bg: #ffffff;
            --border-color: #e5e7eb;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --card-img-bg: #f9fafb;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0; padding: 0;
        }

        header {
            background-color: var(--panel-bg);
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .header-content {
            max-width: 1200px; margin: 0 auto;
            display: flex; justify-content: space-between; align-items: center;
        }

        .logo-link { color: var(--text-main); text-decoration: none; font-size: 28px; font-weight: bold; }

        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }

        .compare-table {
            width: 100%; border-collapse: collapse;
            background: var(--panel-bg);
            border-radius: 16px; border: 1px solid var(--border-color);
            overflow: hidden; margin-top: 20px;
        }

        .compare-table td, .compare-table th {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            text-align: center;
            width: 25%;
        }

        .compare-table td:first-child, .compare-table th:first-child {
            text-align: left;
            font-weight: bold;
            color: var(--text-muted);
            width: 20%;
        }

        .product-img { height: 140px; object-fit: contain; margin-bottom: 10px; }
        .price { font-size: 22px; font-weight: 800; color: var(--accent-green); }

        .btn-remove {
            background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #ef4444;
            padding: 6px 12px; border-radius: 8px; cursor: pointer; font-weight: bold;
            margin-top: 10px;
        }

        .empty-state { text-align: center; padding: 80px 20px; color: var(--text-muted); }
    </style>
</head>
<body>

<header>
    <div class="header-content">
        <h1><a href="index.php" class="logo-link">Cyber<span style="color:#3b82f6;">Phone</span></a></h1>
        <div><a href="index.php" class="filter-btn" style="color:var(--text-main); text-decoration:none;">🏠 На главную</a></div>
    </div>
</header>

<div class="container">
    <h2>⚖️ Сравнение смартфонов</h2>

    <div id="compare-content">
        <div class="empty-state">Загрузка сравнения...</div>
    </div>
</div>

<script>
    function renderCompare() {
        const compareIds = JSON.parse(localStorage.getItem('compare_list')) || [];
        const container = document.getElementById('compare-content');

        if (compareIds.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <h3>Список сравнения пуст</h3>
                    <p>Добавляйте смартфоны значком ⚖️ на карточке в каталоге!</p>
                    <a href="index.php" style="color:#3b82f6; font-weight:bold;">Перейти в каталог ➔</a>
                </div>
            `;
            return;
        }

        if (!window.location.search.includes('ids=')) {
            window.location.href = `compare.php?ids=${compareIds.join(',')}`;
            return;
        }

        const products = <?= json_encode($products); ?>;

        if (products.length === 0) {
            container.innerHTML = `<div class="empty-state">Товары не найдены</div>`;
            return;
        }

        let html = `<table class="compare-table">
            <tr>
                <td>Товар</td>
                ${products.map(p => `
                    <td>
                        <img src="${p.image_url || 'img/default.jpg'}" class="product-img"><br>
                        <strong>${p.name}</strong><br>
                        <button class="btn-remove" onclick="removeFromCompare(${p.id})">Удалить ✕</button>
                    </td>
                `).join('')}
            </tr>
            <tr>
                <td>Цена</td>
                ${products.map(p => `<td class="price">${Number(p.price).toLocaleString('ru-RU')} грн.</td>`).join('')}
            </tr>
            <tr>
                <td>Бренд</td>
                ${products.map(p => `<td>${p.brand}</td>`).join('')}
            </tr>
            <tr>
                <td>Год выпуска</td>
                ${products.map(p => `<td>${p.release_year || '2026'}</td>`).join('')}
            </tr>
            <tr>
                <td>Память</td>
                ${products.map(p => `<td>${p.ram || ''} / ${p.storage_options || ''}</td>`).join('')}
            </tr>
            <tr>
                <td>Описание</td>
                ${products.map(p => `<td style="font-size:13px; color:var(--text-muted);">${p.description}</td>`).join('')}
            </tr>
        </table>`;

        container.innerHTML = html;
    }

    function removeFromCompare(id) {
        let compare = JSON.parse(localStorage.getItem('compare_list')) || [];
        compare = compare.filter(itemId => itemId !== id);
        localStorage.setItem('compare_list', JSON.stringify(compare));
        window.location.href = compare.length > 0 ? `compare.php?ids=${compare.join(',')}` : 'compare.php';
    }

    renderCompare();
</script>
</body>
</html>