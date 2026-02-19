<?php
// investigate.php

require_once __DIR__ . '/vendor/autoload.php';

echo "🔍 ИССЛЕДОВАНИЕ БИБЛИОТЕКИ\n";
echo "===========================\n\n";

// Смотрим все загруженные классы
echo "Все загруженные классы с 'Telegram':\n";
$classes = get_declared_classes();
foreach ($classes as $class) {
    if (strpos($class, 'Telegram') !== false) {
        echo "   ✅ $class\n";
    }
}

echo "\n\n🔍 Поиск файлов в vendor/telegram-bot/api/src:\n";
$files = glob('vendor/telegram-bot/api/src/*.php');
foreach ($files as $file) {
    echo "   📁 $file\n";
    $content = file_get_contents($file);
    
    // Ищем namespace
    if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
        $namespace = $matches[1];
        echo "      Namespace: $namespace\n";
    }
    
    // Ищем классы
    if (preg_match('/class\s+(\w+)/', $content, $matches)) {
        $class = $matches[1];
        echo "      Класс: $class\n";
        echo "      Полное имя: $namespace\\$class\n";
    }
    echo "\n";
}

// Пробуем разные варианты
echo "\n🔍 Тестирование разных вариантов:\n";
$variants = [
    'Telegram\Bot\Api',
    'Telegram\Bot\BotApi',
    'Telegram\Bot\Client',
    'Telegram\Bot\Types\Message',
    'Telegram\Bot\Bot',
    'TelegramBot\Api',
    'TelegramBot\BotApi',
];

foreach ($variants as $variant) {
    echo "   Проверка: $variant - ";
    if (class_exists($variant)) {
        echo "✅ НАЙДЕН!\n";
    } else {
        echo "❌ не найден\n";
    }
}