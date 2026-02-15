<?php
// set_webhook.php

require_once __DIR__ . '/config.php';

use Telegram\Bot\Api;

$telegram = new Api(BOT_TOKEN);

$webhookUrl = 'https://your-domain.com/movie-bot/webhook.php';

try {
    $result = $telegram->setWebhook(['url' => $webhookUrl]);
    
    if ($result) {
        echo "✅ Webhook установлен успешно!\n";
    } else {
        echo "❌ Ошибка установки webhook\n";
    }
    
    // Проверяем информацию о webhook
    $info = $telegram->getWebhookInfo();
    print_r($info);
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}