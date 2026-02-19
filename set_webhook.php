<?php
// set_webhook_fixed.php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use TelegramBot\Api\BotApi;

// URL вашего сайта (замените на реальный)
$webhookUrl = 'https://your-domain.com/movie-bot/webhook.php';

try {
    $telegram = new BotApi(BOT_TOKEN);
    
    // Устанавливаем веб-хук
    $result = $telegram->setWebhook($webhookUrl);
    
    if ($result) {
        echo "✅ Webhook установлен успешно!\n";
        echo "URL: $webhookUrl\n";
    } else {
        echo "❌ Ошибка установки webhook\n";
    }
    
    // Проверяем информацию о webhook
    $info = $telegram->getWebhookInfo();
    echo "\n📊 Информация о webhook:\n";
    echo "URL: " . $info->getUrl() . "\n";
    echo "Ожидает обновлений: " . $info->getPendingUpdateCount() . "\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}