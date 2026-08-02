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

// ----------------------------------------------------
// AJAX ПРОВЕРКА ЛОГИНА И EMAIL НА ЛЕТУ
// ----------------------------------------------------
if (isset($_GET['ajax_check'])) {
    header('Content-Type: application/json');
    $type = $_GET['ajax_check'];
    $value = trim($_GET['value'] ?? '');

    if ($type === 'username') {
        if (mb_strlen($value) < 3) {
            echo json_encode(['status' => 'error', 'message' => '⚠️ Мин. 3 символа']);
            exit;
        }
        $stmt = $db->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?)');
        $stmt->execute([$value]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => '❌ Этот логин уже занят']);
        } else {
            echo json_encode(['status' => 'ok', 'message' => '✅ Логин свободен']);
        }
    } elseif ($type === 'email') {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => '⚠️ Некорректный email']);
            exit;
        }
        $u_cols = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('email', $u_cols)) {
            $stmt = $db->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?)');
            $stmt->execute([$value]);
            if ($stmt->fetch()) {
                echo json_encode(['status' => 'error', 'message' => '❌ Данный email уже используется']);
                exit;
            }
        }
        echo json_encode(['status' => 'ok', 'message' => '✅ Email свободен']);
    }
    exit;
}

$error = '';
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    header('Location: index.php');
    exit;
}

// ОБРАБОТКА ФОРМЫ (ОТПРАВКА НА СЕРВЕР)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($action === 'register') {
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password_confirm = trim($_POST['password_confirm'] ?? '');
        $captcha_verified = ($_POST['captcha_verified'] ?? '0') === '1';
        $terms = isset($_POST['terms']);

        if (!$captcha_verified) {
            $error = 'Пожалуйста, передвиньте пазл проверки!';
        } elseif (!$terms) {
            $error = 'Вы должны принять условия использования.';
        } elseif (empty($username) || empty($email) || empty($password)) {
            $error = 'Заполните все обязательные поля.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Введите корректный адрес Email.';
        } elseif (mb_strlen($username) < 3) {
            $error = 'Логин должен быть не короче 3 символов.';
        } elseif (mb_strlen($password) < 6) {
            $error = 'Пароль слишком короткий.';
        } elseif ($password !== $password_confirm) {
            $error = 'Введенные пароли не совпадают.';
        } else {
            // Обработка аватара
            $avatar_path = '';
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
                    $new_name = 'avatar_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    $avatar_path = 'uploads/' . $new_name;
                    move_uploaded_file($_FILES['avatar']['tmp_name'], $avatar_path);
                }
            }

            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            try {
                $cols = ['username', 'password'];
                $vals = [$username, $hashed_password];

                $u_schema = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

                if (in_array('email', $u_schema)) { $cols[] = 'email'; $vals[] = $email; }
                if (in_array('full_name', $u_schema)) { $cols[] = 'full_name'; $vals[] = $full_name; }
                if (in_array('phone', $u_schema)) { $cols[] = 'phone'; $vals[] = $phone; }
                if (in_array('avatar', $u_schema) && $avatar_path) { $cols[] = 'avatar'; $vals[] = $avatar_path; }

                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $sql = "INSERT INTO users (" . implode(',', $cols) . ") VALUES ($placeholders)";
                
                $stmt = $db->prepare($sql);
                $stmt->execute($vals);
                $new_user_id = $db->lastInsertId();

                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['username'] = $username;
                $_SESSION['is_admin'] = (mb_strtolower($username) === 'admin');

                $show_welcome_modal = true;
            } catch (\PDOException $e) {
                $error = 'Ошибка создания аккаунта. Возможно, логин или email уже заняты.';
            }
        }
    } elseif ($action === 'login') {
        if (empty($username) || empty($password)) {
            $error = 'Заполните логин и пароль.';
        } else {
            $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = (mb_strtolower($user['username']) === 'admin');

                header('Location: index.php');
                exit;
            } else {
                $error = 'Неверный логин или пароль.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход & Регистрация | CyberPhone</title>
    <style>
        :root {
            --bg-color: #0b0f19;
            --panel-bg: rgba(19, 25, 38, 0.85);
            --border-color: rgba(255, 255, 255, 0.12);
            --accent-blue: #2563eb;
            --accent-green: #00e676;
            --accent-red: #ef4444;
            --text-main: #ffffff;
            --text-muted: #8a99ad;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0; padding: 20px 0;
            min-height: 100vh;
            display: flex; justify-content: center; align-items: center;
            background-image: 
                radial-gradient(circle at 50% 30%, rgba(37, 99, 235, 0.25) 0%, transparent 60%),
                radial-gradient(circle at 50% 70%, rgba(0, 230, 118, 0.1) 0%, transparent 60%);
        }

        .auth-container {
            width: 440px; max-width: 90%;
            background: var(--panel-bg);
            padding: 35px; border-radius: 24px;
            border: 1px solid var(--border-color);
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
            backdrop-filter: blur(20px);
            box-sizing: border-box;
        }

        .auth-logo-header {
            text-align: center;
            margin-bottom: 25px;
        }
        .auth-logo-link {
            text-decoration: none;
            color: #ffffff;
            font-size: 32px;
            font-weight: 800;
            letter-spacing: 0.5px;
            transition: opacity 0.2s;
        }
        .auth-logo-link:hover {
            opacity: 0.9;
        }
        .auth-logo-link span {
            color: var(--accent-blue);
        }

        .auth-tabs {
            display: flex; background: rgba(7, 10, 18, 0.6);
            border: 1px solid var(--border-color);
            border-radius: 14px; margin-bottom: 25px; padding: 4px;
        }

        .tab-btn {
            flex: 1; padding: 12px; background: none; border: none;
            color: var(--text-muted); font-weight: 600; cursor: pointer;
            border-radius: 10px; transition: all 0.3s; font-size: 14px;
        }

        .tab-btn.active {
            background: var(--accent-blue); color: #fff;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
        }

        .form-group { margin-bottom: 18px; position: relative; }
        .form-group label {
            display: block; font-size: 11px; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; font-weight: 700;
        }

        .form-control {
            width: 100%; padding: 12px 16px; background: rgba(7, 10, 18, 0.6);
            border: 1px solid var(--border-color); border-radius: 12px;
            color: #fff; font-size: 14px; outline: none; box-sizing: border-box;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 12px rgba(37, 99, 235, 0.3);
        }

        .password-wrapper { position: relative; }
        .password-toggle {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            cursor: pointer; opacity: 0.6; user-select: none; font-size: 16px;
        }

        .strength-bar-wrapper {
            height: 5px; background: rgba(255,255,255,0.1); border-radius: 3px;
            margin-top: 8px; overflow: hidden; display: none;
        }
        .strength-bar-fill { height: 100%; width: 0%; transition: all 0.3s; background: var(--accent-red); }
        .strength-text { font-size: 11px; margin-top: 4px; color: var(--text-muted); display: none; font-weight: 600; }

        .rule-list { font-size: 11px; margin-top: 8px; display: none; grid-template-columns: 1fr 1fr; gap: 4px; }
        .rule-item { color: var(--text-muted); }
        .rule-item.valid { color: var(--accent-green); }

        .drop-zone {
            border: 2px dashed var(--border-color); border-radius: 14px;
            padding: 15px; text-align: center; background: rgba(7, 10, 18, 0.4);
            cursor: pointer; transition: all 0.3s;
        }
        .drop-zone-text { font-size: 12px; color: var(--text-muted); pointer-events: none; }
        .avatar-preview { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; display: none; margin: 0 auto 6px auto; }

        .captcha-slider-box {
            background: rgba(7, 10, 18, 0.5); border: 1px solid var(--border-color);
            border-radius: 12px; padding: 12px; text-align: center; margin-bottom: 20px;
        }
        .captcha-track {
            height: 40px; background: rgba(255,255,255,0.05); border-radius: 20px;
            position: relative; overflow: hidden; margin-top: 8px;
            display: flex; align-items: center; justify-content: center;
        }
        .captcha-text { font-size: 12px; color: var(--text-muted); pointer-events: none; }
        .captcha-thumb {
            width: 40px; height: 40px; background: var(--accent-blue); border-radius: 50%;
            position: absolute; left: 0; top: 0; cursor: pointer; display: flex;
            align-items: center; justify-content: center; box-shadow: 0 0 10px rgba(37,99,235,0.5);
        }

        .btn-submit {
            width: 100%; padding: 14px; background: var(--accent-green); color: #000;
            border: none; border-radius: 12px; font-weight: 700; font-size: 15px;
            cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 15px rgba(0, 230, 118, 0.2);
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 230, 118, 0.4); }

        .alert {
            padding: 12px 16px; border-radius: 10px; margin-bottom: 20px;
            font-size: 13px; font-weight: 600; text-align: center;
        }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid var(--accent-red); color: var(--accent-red); }
        .status-msg { font-size: 11px; margin-top: 4px; font-weight: 600; }

        .welcome-modal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); display: flex; align-items: center; justify-content: center;
            z-index: 10000; backdrop-filter: blur(10px);
        }
        .welcome-card {
            background: #131926; border: 1px solid var(--accent-green);
            border-radius: 24px; padding: 35px; text-align: center; max-width: 380px; width: 90%;
            box-shadow: 0 0 40px rgba(0,230,118,0.2);
        }
        .promo-badge {
            background: rgba(0, 230, 118, 0.15); border: 1px dashed var(--accent-green);
            color: var(--accent-green); font-size: 22px; font-weight: 800; padding: 10px 20px;
            border-radius: 12px; margin: 15px 0; letter-spacing: 2px; display: inline-block;
        }
    </style>
</head>
<body>

<?php if (isset($show_welcome_modal) && $show_welcome_modal): ?>
<div class="welcome-modal">
    <div class="welcome-card">
        <div style="font-size: 48px; margin-bottom: 10px;">🎉</div>
        <h2 style="margin:0; color:#fff;">Поздравляем!</h2>
        <p style="color:var(--text-muted); font-size: 14px; margin-top:8px;">Ваш профиль CyberPhone успешно зарегистрирован.</p>
        
        <div style="font-size: 12px; color:var(--text-muted); margin-top: 15px;">Приветственный промокод:</div>
        <div class="promo-badge">WELCOME10</div>
        <div style="font-size: 11px; color:var(--accent-green);">Скидка 10% на первый заказ!</div>

        <button onclick="window.location.href='index.php';" class="btn-submit" style="margin-top: 20px;">Перейти в каталог 🚀</button>
    </div>
</div>
<?php endif; ?>

<div class="auth-container">
    <!-- ЛОГОТИП САЙТА -->
    <div class="auth-logo-header">
        <a href="index.php" class="auth-logo-link">Cyber<span>Phone</span></a>
    </div>

    <div class="auth-tabs">
        <button type="button" id="tab-login" class="tab-btn active" onclick="setMode('login')">Вход</button>
        <button type="button" id="tab-register" class="tab-btn" onclick="setMode('register')">Регистрация</button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form id="auth-form" action="auth.php" method="POST" enctype="multipart/form-data" autocomplete="off" onsubmit="return validateOnSubmit(event)">
        <input type="hidden" id="auth-action" name="action" value="login">
        <input type="hidden" id="captcha-verified" name="captcha_verified" value="0">

        <!-- ЛОГИН -->
        <div class="form-group">
            <label>Логин *</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="Например, cyber_user" required>
            <div id="username-status" class="status-msg"></div>
        </div>

        <!-- ПОЛЯ РЕГИСТРАЦИИ -->
        <div id="reg-fields" style="display: none;">
            <div class="form-group">
                <label>Email *</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="example@gmail.com">
                <div id="email-status" class="status-msg"></div>
            </div>

            <div class="form-group">
                <label>Ваше Имя</label>
                <input type="text" name="full_name" class="form-control" placeholder="Антон">
            </div>

            <div class="form-group">
                <label>Телефон</label>
                <input type="text" id="phone" name="phone" class="form-control" placeholder="+380 (__) ___-__-__">
            </div>

            <div class="form-group">
                <label>Фото профиля</label>
                <div class="drop-zone" id="drop-zone" onclick="document.getElementById('avatar-input').click();">
                    <img id="avatar-preview" class="avatar-preview" src="#">
                    <div class="drop-zone-text" id="drop-zone-text">📷 Перетащите фото сюда или нажмите</div>
                    <input type="file" id="avatar-input" name="avatar" accept="image/*" style="display:none;">
                </div>
            </div>
        </div>

        <!-- ПАРОЛЬ -->
        <div class="form-group">
            <label>Пароль *</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" class="form-control" placeholder="Ваш пароль" required>
                <span class="password-toggle" onclick="togglePassword('password')">👁️</span>
            </div>

            <div id="strength-wrapper" class="strength-bar-wrapper">
                <div id="strength-bar" class="strength-bar-fill"></div>
            </div>
            <div id="strength-text" class="strength-text"></div>

            <div id="rule-list" class="rule-list">
                <div class="rule-item" id="r-len">8+ символов</div>
                <div class="rule-item" id="r-num">Есть цифры</div>
                <div class="rule-item" id="r-let">Есть буквы</div>
                <div class="rule-item" id="r-spec">Спецсимвол (!@#$)</div>
            </div>
        </div>

        <!-- ПОВТОР ПАРОЛЯ -->
        <div class="form-group" id="confirm-pwd-group" style="display: none;">
            <label>Повторите пароль *</label>
            <div class="password-wrapper">
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Повторите пароль">
                <span class="password-toggle" onclick="togglePassword('password_confirm')">👁️</span>
            </div>
            <div id="confirm-status" class="status-msg"></div>
        </div>

        <!-- СЛАЙДЕР КАПЧА -->
        <div id="captcha-group" class="captcha-slider-box" style="display: none;">
            <div style="font-size: 12px; color: var(--text-muted); font-weight:600;">🧠 Проверка на робота</div>
            <div class="captcha-track" id="captcha-track">
                <span class="captcha-text" id="captcha-text">Передвиньте пазл ➔</span>
                <div class="captcha-thumb" id="captcha-thumb">🧩</div>
            </div>
        </div>

        <div id="terms-group" style="display: none; margin-bottom: 20px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; text-transform: none; color: var(--text-muted); font-size: 12px;">
                <input type="checkbox" id="terms" name="terms" style="accent-color: var(--accent-blue);"> Я принимаю условия использования
            </label>
        </div>

        <button type="submit" id="btn-submit" class="btn-submit">Войти в аккаунт</button>
    </form>
</div>

<script>
    function setMode(mode) {
        const isReg = (mode === 'register');
        document.getElementById('auth-action').value = mode;
        document.getElementById('reg-fields').style.display = isReg ? 'block' : 'none';
        document.getElementById('confirm-pwd-group').style.display = isReg ? 'block' : 'none';
        document.getElementById('captcha-group').style.display = isReg ? 'block' : 'none';
        document.getElementById('terms-group').style.display = isReg ? 'block' : 'none';
        document.getElementById('strength-wrapper').style.display = isReg ? 'block' : 'none';
        document.getElementById('strength-text').style.display = isReg ? 'block' : 'none';
        document.getElementById('rule-list').style.display = isReg ? 'grid' : 'none';

        document.getElementById('tab-login').classList.toggle('active', !isReg);
        document.getElementById('tab-register').classList.toggle('active', isReg);

        document.getElementById('btn-submit').textContent = isReg ? 'Зарегистрироваться ✨' : 'Войти в аккаунт';
    }

    function validateOnSubmit(e) {
        const mode = document.getElementById('auth-action').value;
        if (mode === 'login') {
            const u = document.getElementById('username').value.trim();
            const p = document.getElementById('password').value.trim();
            if (!u || !p) {
                alert('Пожалуйста, введите логин и пароль.');
                return false;
            }
            return true;
        } else {
            const captchaOK = document.getElementById('captcha-verified').value === '1';
            const termsOK = document.getElementById('terms').checked;
            
            if (!captchaOK) {
                alert('Передвиньте слайдер проверки на робота!');
                return false;
            }
            if (!termsOK) {
                alert('Примите условия использования!');
                return false;
            }
            return true;
        }
    }

    function togglePassword(id) {
        const el = document.getElementById(id);
        el.type = el.type === 'password' ? 'text' : 'password';
    }

    // МАСКА ТЕЛЕФОНА
    document.getElementById('phone').addEventListener('input', function (e) {
        let matrix = "+380 (__) ___-__-__",
            i = 0,
            def = matrix.replace(/\D/g, ""),
            val = this.value.replace(/\D/g, "");
        if (def.length >= val.length) val = def;
        this.value = matrix.replace(/./g, function (a) {
            return /[_\d]/.test(a) && i < val.length ? val.charAt(i++) : i >= val.length ? "" : a
        });
    });

    // AJAX ПРОВЕРКА ЛОГИНА
    let checkTimeout;
    document.getElementById('username').addEventListener('input', function() {
        const status = document.getElementById('username-status');
        const val = this.value.trim();
        clearTimeout(checkTimeout);

        if (document.getElementById('auth-action').value !== 'register') { status.textContent = ''; return; }

        checkTimeout = setTimeout(() => {
            fetch(`auth.php?ajax_check=username&value=${encodeURIComponent(val)}`)
                .then(r => r.json())
                .then(d => {
                    status.textContent = d.message;
                    status.style.color = (d.status === 'ok') ? '#00e676' : '#ef4444';
                });
        }, 300);
    });

    // AJAX ПРОВЕРКА EMAIL
    let emailTimeout;
    document.getElementById('email').addEventListener('input', function() {
        const status = document.getElementById('email-status');
        const val = this.value.trim();
        clearTimeout(emailTimeout);

        if (document.getElementById('auth-action').value !== 'register') { status.textContent = ''; return; }

        emailTimeout = setTimeout(() => {
            fetch(`auth.php?ajax_check=email&value=${encodeURIComponent(val)}`)
                .then(r => r.json())
                .then(d => {
                    status.textContent = d.message;
                    status.style.color = (d.status === 'ok') ? '#00e676' : '#ef4444';
                });
        }, 300);
    });

    // СИЛА ПАРОЛЯ
    document.getElementById('password').addEventListener('input', function() {
        if (document.getElementById('auth-action').value !== 'register') return;

        const val = this.value;
        const bar = document.getElementById('strength-bar');
        const txt = document.getElementById('strength-text');

        const hasLen = val.length >= 8;
        const hasNum = /[0-9]/.test(val);
        const hasLet = /[a-zA-Zа-яА-Я]/.test(val);
        const hasSpec = /[!@#$%^&*(),.?":{}|<>]/.test(val);

        document.getElementById('r-len').classList.toggle('valid', hasLen);
        document.getElementById('r-num').classList.toggle('valid', hasNum);
        document.getElementById('r-let').classList.toggle('valid', hasLet);
        document.getElementById('r-spec').classList.toggle('valid', hasSpec);

        let score = 0;
        if (hasLen) score += 25;
        if (hasNum) score += 25;
        if (hasLet) score += 25;
        if (hasSpec) score += 25;

        bar.style.width = score + '%';

        if (score <= 25) {
            bar.style.background = '#ef4444'; txt.textContent = '🔴 Слабый пароль'; txt.style.color = '#ef4444';
        } else if (score <= 75) {
            bar.style.background = '#f59e0b'; txt.textContent = '🟡 Средняя надежность'; txt.style.color = '#f59e0b';
        } else {
            bar.style.background = '#00e676'; txt.textContent = '🟢 Отличный и надежный!'; txt.style.color = '#00e676';
        }
    });

    // DRAG & DROP АВАТАР
    const dropZone = document.getElementById('drop-zone');
    const avatarInput = document.getElementById('avatar-input');
    const avatarPreview = document.getElementById('avatar-preview');
    const dropZoneText = document.getElementById('drop-zone-text');

    if (dropZone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => { e.preventDefault(); dropZone.classList.add('dragover'); }, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => { e.preventDefault(); dropZone.classList.remove('dragover'); }, false);
        });

        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                avatarInput.files = files;
                showPreview(files[0]);
            }
        });

        avatarInput.addEventListener('change', function() {
            if (this.files.length > 0) showPreview(this.files[0]);
        });
    }

    function showPreview(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            avatarPreview.src = e.target.result;
            avatarPreview.style.display = 'block';
            dropZoneText.textContent = '✓ Фото выбрано!';
        }
        reader.readAsDataURL(file);
    }

    // СЛАЙДЕР КАПЧА
    const thumb = document.getElementById('captcha-thumb');
    const track = document.getElementById('captcha-track');
    let isDragging = false;

    if (thumb && track) {
        thumb.addEventListener('mousedown', () => isDragging = true);
        document.addEventListener('mouseup', () => isDragging = false);

        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            const rect = track.getBoundingClientRect();
            let offsetX = e.clientX - rect.left - 20;
            const maxTrack = rect.width - 40;

            if (offsetX < 0) offsetX = 0;
            if (offsetX > maxTrack) offsetX = maxTrack;

            thumb.style.left = offsetX + 'px';

            if (offsetX >= maxTrack - 5) {
                isDragging = false;
                document.getElementById('captcha-verified').value = '1';
                document.getElementById('captcha-text').textContent = '✅ Проверка пройдена';
                document.getElementById('captcha-text').style.color = '#00e676';
                thumb.style.background = '#00e676';
            }
        });
    }
</script>
</body>
</html>