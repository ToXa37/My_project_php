<?php
session_start();

if (isset($_SESSION['user_logged_in'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($username === 'admin' && $password === 'admin') {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['username'] = 'Администратор';
        
        header("Location: index.php");
        exit;
    } else {
        $error = '❌ Неверный логин или пароль! (Попробуйте admin / admin)';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Авторизация | CyberPhone</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-box {
            max-width: 400px;
            margin: 100px auto;
            background-color: var(--panel-bg, #131926);
            border: 1px solid var(--border-color, #1f293d);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            text-align: center;
        }
        .login-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 25px;
            color: var(--text-main, #fff);
        }
        .form-input {
            width: 100%;
            padding: 12px 20px;
            margin-bottom: 20px;
            background-color: var(--bg-color, #0b0f19);
            border: 1px solid var(--border-color, #1f293d);
            border-radius: 25px;
            color: #fff;
            font-size: 16px;
            outline: none;
        }
        .form-input:focus {
            border-color: #3b82f6;
        }
        .error-msg {
            color: #ef4444;
            font-size: 14px;
            margin-bottom: 15px;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="login-box">
        <div class="login-title">🔐 Вход в CyberPhone</div>
        
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= $error; ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <input type="text" name="username" class="form-input" placeholder="Логин (admin)" required>
            <input type="password" name="password" class="form-input" placeholder="Пароль (admin)" required>
            <button type="submit" class="buy-btn" style="width: 100%; padding: 12px; border-radius: 25px; font-size: 16px;">Войти</button>
        </form>
        
        <div style="margin-top: 20px;">
            <a href="index.php" style="color: var(--text-muted); text-decoration: none; font-size: 14px;">🏠 Вернуться в каталог</a>
        </div>
    </div>
</div>
</body>
</html>