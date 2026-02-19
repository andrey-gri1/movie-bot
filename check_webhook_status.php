<?php
// check_webhook_status.php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use TelegramBot\Api\BotApi;

echo "🔍 ПРОВЕРКА СТАТУСА ВЕБХУКА\n";
echo "============================\n\n";

$telegram = new BotApi(BOT_TOKEN);

// 1. Проверяем информацию о вебхуке
$webhookInfo = $telegram->getWebhookInfo();
echo "📡 Информация о вебхуке:\n";
echo "   URL: " . ($webhookInfo->getUrl() ?: "не установлен") . "\n";
echo "   Ожидает обновлений: " . $webhookInfo->getPendingUpdateCount() . "\n";
echo "   Последняя ошибка: " . ($webhookInfo->getLastErrorMessage() ?: "нет") . "\n\n";

// 2. Принудительно удаляем вебхук
echo "🔄 Принудительное удаление вебхука...\n";
$result = $telegram->deleteWebhook();
echo $result ? "   ✅ Вебхук удален\n" : "   ❌ Ошибка удаления\n";
sleep(2);

// 3. Проверяем еще раз
$webhookInfo2 = $telegram->getWebhookInfo();
echo "\n📡 После удаления:\n";
echo "   URL: " . ($webhookInfo2->getUrl() ?: "не установлен") . "\n\n";

// 4. Пробуем получить обновления
echo "📨 Попытка получить обновления...\n";
try {
    $updates = $telegram->getUpdates(0, 1, 0);
    echo "   Найдено обновлений: " . count($updates) . "\n";
    
    if (!empty($updates)) {
        foreach ($updates as $update) {
            echo "   ✅ Есть обновление ID: " . $update->getUpdateId() . "\n";
            if ($message = $update->getMessage()) {
                echo "   Текст: " . $message->getText() . "\n";
            }
        }
    } else {
        echo "   ⚠️ Нет обновлений. Отправьте /start боту в Telegram!\n";
    }
} catch (Exception $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n";
}