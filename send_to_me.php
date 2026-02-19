<?php
// send_to_me.php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use TelegramBot\Api\BotApi;

// Узнайте ваш Chat ID через @userinfobot и вставьте сюда
$myChatId = '1970640553'; // Например: 123456789

if ($myChatId === 'ВАШ_CHAT_ID') {
    echo "❌ Сначала узнайте ваш Chat ID через @userinfobot\n";
    echo "   1. Найдите @userinfobot в Telegram\n";
    echo "   2. Отправьте ему /start\n";
    echo "   3. Скопируйте ваш ID (число) и вставьте в этот файл\n";
    exit;
}

try {
    $telegram = new BotApi(BOT_TOKEN);
    
    // Тест 1: Простое сообщение
    $result = $telegram->sendMessage($myChatId, "🔔 Тестовое сообщение 1");
    echo "✅ Тест 1: простое сообщение отправлено\n";
    
    // Тест 2: Сообщение с Markdown
    $result = $telegram->sendMessage(
        $myChatId,
        "*Тест 2:* Сообщение с *Markdown*",
        'Markdown'
    );
    echo "✅ Тест 2: Markdown сообщение отправлено\n";
    
    // Тест 3: Получение обновлений
    $updates = $telegram->getUpdates(0, 1, 0);
    echo "✅ Тест 3: Получено обновлений: " . count($updates) . "\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}