<?php
// diagnostic.php - Полная диагностика бота

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use TelegramBot\Api\BotApi;

echo "🔬 ПОЛНАЯ ДИАГНОСТИКА БОТА\n";
echo "==========================\n\n";

try {
    $telegram = new BotApi(BOT_TOKEN);
    
    // 1. Проверка бота
    $me = $telegram->getMe();
    echo "✅ БОТ: @" . $me->getUsername() . " (" . $me->getFirstName() . ")\n\n";
    
    // 2. Проверка вебхука (должен быть пустым для polling)
    $webhookInfo = $telegram->getWebhookInfo();
    echo "📡 ВЕБХУК:\n";
    echo "   URL: " . ($webhookInfo->getUrl() ?: "не установлен") . "\n";
    echo "   Ожидает обновлений: " . $webhookInfo->getPendingUpdateCount() . "\n\n";
    
    // 3. Получение обновлений с разными параметрами
    echo "📨 ПРОВЕРКА ОБНОВЛЕНИЙ:\n";
    
    // Способ 1: без offset (все обновления)
    $updates1 = $telegram->getUpdates(0, 1);
    echo "   getUpdates(0, 1): " . count($updates1) . " обновлений\n";
    
    // Способ 2: с отрицательным offset (все новые)
    $updates2 = $telegram->getUpdates(-1, 1);
    echo "   getUpdates(-1, 1): " . count($updates2) . " обновлений\n";
    
    // Способ 3: с таймаутом
    $updates3 = $telegram->getUpdates(0, 1, 2);
    echo "   getUpdates(0, 1, 2): " . count($updates3) . " обновлений\n\n";
    
    // 4. Если есть обновления, покажем их
    if (!empty($updates1)) {
        echo "📝 ПОСЛЕДНИЕ ОБНОВЛЕНИЯ:\n";
        foreach ($updates1 as $update) {
            echo "   Update ID: " . $update->getUpdateId() . "\n";
            if ($message = $update->getMessage()) {
                echo "   Сообщение: " . $message->getText() . "\n";
                echo "   Chat ID: " . $message->getChat()->getId() . "\n";
            }
            echo "\n";
        }
    } else {
        echo "⚠️ Нет обновлений. Отправьте /start боту в Telegram!\n\n";
    }
    
    // 5. Проверка отправки тестового сообщения
    echo "📤 ПРОВЕРКА ОТПРАВКИ:\n";
    
    // Попробуем отправить сообщение в тестовый чат
    // Нужно узнать ваш chat_id
    echo "   Чтобы проверить отправку, нужно знать ваш Chat ID\n";
    echo "   Узнайте его через @userinfobot в Telegram\n";
    echo "   Затем добавьте в файл test_send.php\n\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}