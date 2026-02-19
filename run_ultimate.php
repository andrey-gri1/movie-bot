<?php
// run_ultimate.php - ИСПРАВЛЕННАЯ ВЕРСИЯ

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/kinopoisk_api.php';
require_once __DIR__ . '/bot_full.php';

use TelegramBot\Api\BotApi;

echo "🎬 MOVIE MASTER BOT - ULTIMATE\n";
echo "===============================\n\n";

try {
    $telegram = new BotApi(BOT_TOKEN);
    
    // ШАГ 1: ПРОВЕРКА ПОДКЛЮЧЕНИЯ
    echo "🔍 ШАГ 1: Проверка подключения к Telegram API\n";
    $me = $telegram->getMe();
    echo "   ✅ Бот: @" . $me->getUsername() . "\n";
    echo "   ✅ ID: " . $me->getId() . "\n\n";
    
    // ШАГ 2: ПРОВЕРКА И УДАЛЕНИЕ ВЕБХУКА
    echo "🔍 ШАГ 2: Проверка вебхука\n";
    $webhookInfo = $telegram->getWebhookInfo();
    if ($webhookInfo->getUrl()) {
        echo "   ⚠️ Обнаружен вебхук: " . $webhookInfo->getUrl() . "\n";
        echo "   🗑️ Удаляем...\n";
        $telegram->deleteWebhook();
        sleep(2);
        echo "   ✅ Вебхук удален\n";
    } else {
        echo "   ✅ Вебхук не установлен\n";
    }
    echo "\n";
    
    // ШАГ 3: ЗАПУСК ПОЛЛИНГА
    echo "🔍 ШАГ 3: Запуск polling\n";
    echo "   📱 Отправьте команду /start боту @" . $me->getUsername() . "\n";
    echo "   " . str_repeat("═", 50) . "\n\n";
    
    $bot = new MovieBotFull();
    $lastUpdateId = 0;
    $noUpdatesCount = 0;
    
    while (true) {
        try {
            // Получаем обновления
            $updates = $telegram->getUpdates($lastUpdateId + 1, 10, 30);
            
            if (!empty($updates)) {
                foreach ($updates as $update) {
                    $lastUpdateId = $update->getUpdateId();
                    $noUpdatesCount = 0;
                    
                    // Преобразуем в массив для обработки
                    $updateArray = json_decode(json_encode($update), true);
                    
                    if (isset($updateArray['message'])) {
                        $message = $updateArray['message'];
                        $text = $message['text'] ?? '';
                        $name = $message['from']['first_name'] ?? 'User';
                        
                        echo date('H:i:s') . " 📨 $name: $text\n";
                        
                        // Отправляем в бот для полной обработки
                        $bot->handleMessage($message);
                    }
                    
                    if (isset($updateArray['callback_query'])) {
                        $data = $updateArray['callback_query']['data'];
                        echo date('H:i:s') . " 🔘 Callback: $data\n";
                        
                        // Отправляем в бот для полной обработки
                        $bot->handleCallback($updateArray['callback_query']);
                    }
                }
            } else {
                $noUpdatesCount++;
                if ($noUpdatesCount % 30 == 0) { // Каждые ~15 минут
                    echo date('H:i:s') . " ⏎ (ожидание сообщений...)\n";
                }
            }
            
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            if (strpos($errorMsg, 'timeout') === false) {
                echo "❌ Ошибка: $errorMsg\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Критическая ошибка: " . $e->getMessage() . "\n";
}