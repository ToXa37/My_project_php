<?php
// 1. Включаем отображение ошибок
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Инициализируем сессию
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Полностью очищаем массив сессии
$_SESSION = array();

// 4. Уничтожаем куку сессии PHPSESSID в браузере
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 86400, '/');
}

// 5. Уничтожаем куку "Запомнить меня"
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 86400, '/');
}

// 6. Уничтожаем саму сессию на сервере
session_destroy();

// 7. Сбрасываем кэш браузера
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// 8. Перенаправляем пользователя СТРОГО на страницу авторизации!
header("Location: auth.php");
exit;