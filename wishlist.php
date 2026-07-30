<?php
// Запускаем сессию для вывода правильной цифры корзины при загрузке страницы
session_start();
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
    <title>CyberPhone — Ваше Избранное</title>
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background-color 0.3s, color 0.3s;
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
        
        .nav-link-btn:hover * { 
            color: #ffffff !important; 
        }

        .nav-link-btn[style*="border-color: #00e676"]:hover,
        .nav-link-btn[style*="border-color: rgb(0, 230, 118)"]:hover {
            background-color: #00e676 !important;
            border-color: #00e676 !important;
            box-shadow: 0 0 15px rgba(0, 230, 118, 0.4);
        }
        
        .nav-link-btn[style*="border-color: #00e676"]:hover *,
        .nav-link-btn[style*="border-color: rgb(0, 230, 118)"]:hover * {
            color: #000000 !important;
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

        .badge {
            background-color: #00e676;
            color: #000000;
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 10px;
            font-weight: bold;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            flex: 1;
            width: 100%;
            box-sizing: border-box;
        }

        h2 {
            font-size: 32px;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 10px;
        }

        .subtitle {
            color: var(--text-muted);
            margin-bottom: 40px;
            font-size: 16px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 30px;
        }

        .product-card {
            background-color: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: transform 0.3s ease, border-color 0.3s ease, background-color 0.3s, opacity 0.3s;
            box-shadow: 0 4px 20px var(--shadow);
        }

        .product-card:hover {
            transform: translateY(-5px);
            border-color: #3b82f6;
        }

        .remove-wishlist-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.2s;
            z-index: 10;
        }

        .remove-wishlist-btn:hover {
            background: #ef4444;
            color: white;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.4);
        }

        .product-image-container {
            background-color: var(--card-img-bg);
            border-radius: 8px;
            height: 200px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background-color 0.3s, border-color 0.3s, transform 0.2s;
        }

        .product-image-container:hover {
            border-color: #2563eb;
        }

        .product-image-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.2s;
        }

        .product-image-container:hover img {
            transform: scale(1.04);
        }

        .product-brand {
            font-size: 12px;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .product-title {
            color: var(--text-main);
            text-decoration: none;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            display: inline-block;
            transition: color 0.2s;
        }

        .product-title:hover {
            color: #3b82f6;
        }

        .product-desc {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.5;
            margin: 0 0 15px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;  
            overflow: hidden;
        }

        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }

        .product-price {
            font-size: 18px;
            font-weight: 700;
            color: #ef4444;
        }

        .buy-btn {
            background-color: #00e676;
            color: #000000;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .buy-btn:hover {
            background-color: #00c853;
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: var(--panel-bg);
            border: 1px dashed var(--border-color);
            border-radius: 16px;
            max-width: 550px;
            margin: 40px auto;
            transition: background-color 0.3s, border-color 0.3s;
        }

        .empty-icon {
            font-size: 50px;
            margin-bottom: 15px;
            display: inline-block;
        }

        .empty-state h3 {
            font-size: 22px;
            margin: 0 0 10px 0;
        }

        .empty-state p {
            color: var(--text-muted);
            margin: 0 0 25px 0;
            font-size: 14px;
            line-height: 1.5;
        }

        .toast-notification {
            position: fixed;
            bottom: 30px;
            right: -400px;
            background-color: var(--panel-bg);
            border: 1px solid #00e676;
            color: var(--text-main);
            padding: 16px 28px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 230, 118, 0.2);
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 15px;
            transition: right 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .toast-notification.show {
            right: 30px;
        }

        .toast-icon {
            font-size: 20px;
            color: #00e676;
        }

        footer {
            text-align: center;
            padding: 30px;
            color: #4b5563;
            font-size: 14px;
            border-top: 1px solid var(--border-color);
            margin-top: auto;
        }
    </style>
</head>
<body>

<div id="toast" class="toast-notification">
    <span class="toast-icon">✨</span> 
    <span id="toast-message">Товар добавлен!</span>
</div>

<header>
    <div class="header-content">
        <div>
            <h1 style="margin:0;"><a href="index.php" class="logo-link">Cyber<span style="color: #2563eb;">Phone</span></a></h1>
        </div>
        <div class="nav-buttons">
            <button id="theme-toggle" class="theme-toggle-btn">🌙</button>
            <a href="index.php" class="nav-link-btn">🏠 В каталог</a>
            <a href="cart.php" class="nav-link-btn" style="border-color: #00e676;">🛒 Корзина <span id="cart-count" class="badge"><?= $cart_count; ?></span></a>
        </div>
    </div>
</header>

<div class="container">
    <h2>❤️ Ваше Избранное</h2>
    <div class="subtitle">Отложенные товары премиум-класса</div>

    <div class="products-grid" id="wishlist-grid">
        <div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); padding: 40px 0;">
            Секунду, загружаем ваши товары...
        </div>
    </div>
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

document.addEventListener("DOMContentLoaded", () => {
    renderWishlist();
});

function showToast(message, isSuccess = true) {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    const toastIcon = toast.querySelector('.toast-icon');

    toastMessage.textContent = message;
    
    if (!isSuccess) {
        toast.style.borderColor = '#ef4444';
        toast.style.boxShadow = '0 10px 30px rgba(239, 68, 68, 0.2)';
        toastIcon.textContent = '⚠️';
    } else {
        toast.style.borderColor = '#00e676';
        toast.style.boxShadow = '0 10px 30px rgba(0, 230, 118, 0.2)';
        toastIcon.textContent = '🛒';
    }

    toast.classList.add('show');

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

function renderWishlist() {
    const grid = document.getElementById('wishlist-grid');
    const wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];

    if (wishlist.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <span class="empty-icon">❤️</span>
                <h3>Список избранного пуст</h3>
                <p>Вы еще не добавили ни одного гаджета. Вернитесь в каталог премиальной электроники, чтобы исправить это!</p>
                <a href="index.php" class="buy-btn" style="display: inline-flex; justify-content: center; margin: 0 auto; text-decoration: none; padding: 12px 24px;">🛒 Начать покупки</a>
            </div>`;
        return;
    }

    fetch(`wishlist_helper.php?ids=${wishlist.join(',')}`)
        .then(res => res.json())
        .then(products => {
            if (!products || products.length === 0) {
                grid.innerHTML = '<div class="empty-state"><h3>Ошибка получения данных</h3></div>';
                return;
            }

            grid.innerHTML = '';

            products.forEach(product => {
                const card = document.createElement('div');
                card.className = 'product-card';
                
                const formattedPrice = Number(product.price).toLocaleString('ru-RU');

                card.innerHTML = `
                    <button class="remove-wishlist-btn" onclick="removeFromWishlist(${product.id}, this)">✕</button>
                    <div>
                        <a href="product.php?id=${product.id}" class="product-image-container" title="Перейти к товару">
                            <img src="${product.image}" alt="${product.name}" onerror="this.src='img/default.jpg';">
                        </a>
                        <div class="product-brand">${product.brand}</div>
                        <a href="product.php?id=${product.id}" class="product-title">${product.name}</a>
                        <p class="product-desc">${product.description}</p>
                    </div>
                    <div class="product-footer">
                        <div class="product-price">${formattedPrice} грн.</div>
                        <button class="buy-btn" onclick="addToCart(${product.id})">Купить</button>
                    </div>
                `;
                grid.appendChild(card);
            });
        })
        .catch(() => {
            grid.innerHTML = '<div class="empty-state"><h3>Ошибка сети</h3></div>';
        });
}

function removeFromWishlist(productId, btn) {
    let wishlist = JSON.parse(localStorage.getItem('wishlist')) || [];
    wishlist = wishlist.filter(id => id !== productId);
    localStorage.setItem('wishlist', JSON.stringify(wishlist));
    
    const card = btn.closest('.product-card');
    card.style.transform = 'scale(0.8)';
    card.style.opacity = '0';
    setTimeout(() => {
        renderWishlist();
    }, 300);
}

function addToCart(productId) {
    let formData = new FormData();
    formData.append('product_id', productId);

    fetch('add_to_cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Динамически обновляем бейдж с количеством товаров в корзине
            const cartCountBadge = document.getElementById('cart-count');
            if (cartCountBadge) {
                cartCountBadge.textContent = data.total_count;
            }
            
            if (data.already_in_cart) {
                showToast('Этот товар уже в корзине. Добавили ещё одну штуку! 🔥', true);
            } else {
                showToast('Товар успешно добавлен в корзину! 🛒', true);
            }
        } else {
            showToast('Не удалось добавить товар в корзину.', false);
        }
    })
    .catch(() => {
        showToast('Ошибка сети при добавлении в корзину.', false);
    });
}
</script>
</body>
</html>