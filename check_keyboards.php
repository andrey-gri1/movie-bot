<?php
// check_keyboards.php
require_once __DIR__ . '/vendor/autoload.php';

echo "🔍 ПРОВЕРКА ПАПКИ Types/Inline:\n\n";

$inlineFiles = glob('vendor/telegram-bot/api/src/Types/Inline/*.php');
foreach ($inlineFiles as $file) {
    echo "📁 $file\n";
    $content = file_get_contents($file);
    
    if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
        $namespace = $matches[1];
        echo "   Namespace: $namespace\n";
    }
    
    if (preg_match('/class\s+(\w+)/', $content, $matches)) {
        $class = $matches[1];
        echo "   Класс: $class\n";
        echo "   Полное имя: $namespace\\$class\n\n";
    }
}

echo "\n🔍 ПРОВЕРКА НАЛИЧИЯ КЛАССОВ:\n";
try {
    if (class_exists('TelegramBot\Api\Types\Inline\InlineKeyboardMarkup')) {
        echo "✅ InlineKeyboardMarkup доступен\n";
    } else {
        echo "❌ InlineKeyboardMarkup НЕ доступен\n";
    }
    
    if (class_exists('TelegramBot\Api\Types\Inline\InlineKeyboardButton')) {
        echo "✅ InlineKeyboardButton доступен\n";
    } else {
        echo "❌ InlineKeyboardButton НЕ доступен\n";
    }
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}