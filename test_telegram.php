<?php
// test_telegram_fixed.php

echo "🔍 ДЕТАЛЬНАЯ ПРОВЕРКА\n";
echo "=====================\n\n";

// Проверка 1: Автозагрузка
echo "1. Проверка autoload.php:\n";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "   ✅ vendor/autoload.php найден\n";
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    echo "   ❌ vendor/autoload.php НЕ найден\n";
    exit(1);
}

// Проверка 2: Ищем все классы в библиотеке
echo "\n2. Поиск классов Telegram:\n";
$files = glob('vendor/telegram-bot/api/src/*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (preg_match('/namespace\s+([^;]+)/', $content, $ns)) {
        if (preg_match('/class\s+(\w+)/', $content, $class)) {
            $fullClass = $ns[1] . '\\' . $class[1];
            echo "   📍 Найден класс: $fullClass\n";
            
            // Пробуем загрузить
            if (class_exists($fullClass)) {
                echo "     ✅ Успешно загружен\n";
            }
        }
    }
}

// Проверка 3: Пробуем разные варианты имени класса
echo "\n3. Тестирование разных вариантов:\n";

$variants = [
    'Telegram\Bot\BotApi',
    'Telegram\Bot\Api',
    'Telegram\Bot\Client',
    'Telegram\Bot\Types\Message',
];

foreach ($variants as $variant) {
    echo "   Проверка: $variant - ";
    if (class_exists($variant)) {
        echo "✅ НАЙДЕН!\n";
    } else {
        echo "❌ не найден\n";
    }
}

// Проверка 4: Смотрим содержимое BotApi.php
echo "\n4. Анализ BotApi.php:\n";
$botApiFile = 'vendor/telegram-bot/api/src/BotApi.php';
if (file_exists($botApiFile)) {
    echo "   ✅ Файл найден\n";
    $content = file_get_contents($botApiFile);
    if (preg_match('/namespace\s+([^;]+)/', $content, $ns)) {
        echo "   📍 Namespace: " . $ns[1] . "\n";
    }
    if (preg_match('/class\s+(\w+)/', $content, $class)) {
        echo "   📍 Class: " . $class[1] . "\n";
        echo "   📍 Полное имя: " . $ns[1] . '\\' . $class[1] . "\n";
    }
}

echo "\n🎉 Проверка завершена!\n";