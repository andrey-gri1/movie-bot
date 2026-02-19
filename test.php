<?php
// test.php
echo "🔍 Проверка бота...\n\n";

// Проверка 1: Файлы
echo "✅ Файлы: " . (file_exists('bot.php') ? 'OK' : 'NO') . "\n";

// Проверка 2: .env
echo "✅ .env: " . (file_exists('.env') ? 'OK' : 'NO') . "\n";

// Проверка 3: Vendor
echo "✅ Vendor: " . (is_dir('vendor') ? 'OK' : 'NO') . "\n";

// Проверка 4: Подключение к БД
try {
    require_once 'config.php';
    require_once 'database.php';
    $db = Database::getInstance();
    echo "✅ База данных: OK\n";
} catch (Exception $e) {
    echo "❌ База данных: " . $e->getMessage() . "\n";
}

// Проверка 5: API Кинопоиска
try {
    require_once 'kinopoisk_api.php';
    $api = new KinopoiskAPI();
    echo "✅ API Кинопоиска: OK\n";
} catch (Exception $e) {
    echo "❌ API Кинопоиска: " . $e->getMessage() . "\n";
}

echo "\n🎬 Все проверки завершены!\n";