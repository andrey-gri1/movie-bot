<?php
// run_bot_quiet.php - ИСПРАВЛЕННАЯ ВЕРСИЯ

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/kinopoisk_api.php';
require_once __DIR__ . '/bot_full.php';  // ИЗМЕНЕНО

use TelegramBot\Api\BotApi;
use TelegramBot\Api\HttpException;

echo "🎬 MOVIE MASTER BOT - QUIET MODE\n";
echo "================================\n\n";

try {
    $telegram = new BotApi(BOT_TOKEN);
    $bot = new MovieBotFull();  // Класс называется MovieBotFull
    
    $telegram->deleteWebhook();
    echo "✅ Вебхук сброшен\n";
    
    $me = $telegram->getMe();
    echo "✅ Бот: @" . $me->getUsername() . "\n";
    echo "✅ Имя: " . $me->getFirstName() . "\n\n";
    
    $lastUpdateId = 0;
    $noUpdatesCount = 0;
    
    echo "🚀 ЗАПУСК POLLING...\n";
    echo "📱 Отправь /start боту @" . $me->getUsername() . "\n";
    echo "────────────────────────────────────\n\n";
    
    while (true) {
        try {
            $updates = $telegram->getUpdates($lastUpdateId + 1, 100, 30);
            
            if (!empty($updates)) {
                foreach ($updates as $update) {
                    $lastUpdateId = $update->getUpdateId();
                    $updateArray = json_decode(json_encode($update), true);
                    
                    echo "📨 Получено обновление #$lastUpdateId\n";
                    
                    if (isset($updateArray['message'])) {
                        $bot->handleMessage($updateArray['message']);
                    }
                    
                    if (isset($updateArray['callback_query'])) {
                        $bot->handleCallback($updateArray['callback_query']);
                    }
                }
                $noUpdatesCount = 0;
            } else {
                $noUpdatesCount++;
                if ($noUpdatesCount % 10 == 0) {
                    echo "⏎ (ожидание сообщений...)\n";
                }
            }
            
        } catch (HttpException $e) {
            // Игнорируем таймауты - они нормальны
            continue;
        } catch (Exception $e) {
            echo "❌ Ошибка: " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Критическая ошибка: " . $e->getMessage() . "\n";
}