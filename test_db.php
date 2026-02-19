<?php
// test_db.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

echo "🔍 ПРОВЕРКА БАЗЫ ДАННЫХ\n";
echo "=======================\n\n";

$db = Database::getInstance();

if ($db->isConnected()) {
    echo "✅ Подключение к базе данных успешно!\n";
    
    // Проверяем создание пользователя
    $testUserId = 123456789;
    $user = $db->getOrCreateUser($testUserId, 'test_user', 'Test', 'User');
    
    if ($user) {
        echo "✅ Создание пользователя работает\n";
        echo "   Пользователь: " . $user['first_name'] . "\n";
    }
} else {
    echo "❌ Ошибка подключения к базе данных\n";
    echo "\n🔧 Проверьте:\n";
    echo "1. MySQL запущен в XAMPP\n";
    echo "2. База данных 'movie_bot' существует\n";
    echo "3. Пароль в .env файле правильный\n";
}