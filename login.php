<?php
session_start();

// Перенаправляем пользователя на новый красивый экран авторизации/регистрации
header('Location: auth.php');
exit;
?>