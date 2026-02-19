<?php
// run_bot.php - ИСПРАВЛЕННАЯ ВЕРСИЯ

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/kinopoisk_api.php';
require_once __DIR__ . '/bot_full.php';  // ИЗМЕНЕНО: было bot.php, стало bot_full.php

use TelegramBot\Api\BotApi;

echo "🎬 MOVIE MASTER BOT - FULL VERSION\n";
echo "==================================\n\n";

try {
    // Инициализация
    $telegram = new BotApi(BOT_TOKEN);
    $bot = new MovieBotFull();  // Класс называется MovieBotFull
    
    // Сброс вебхука
    $telegram->deleteWebhook();
    echo "✅ Вебхук сброшен\n";
    
    // Проверка бота
    $me = $telegram->getMe();
    echo "✅ Бот: @" . $me->getUsername() . "\n";
    echo "✅ Имя: " . $me->getFirstName() . "\n\n";
    
    $lastUpdateId = 0;
    
    echo "🚀 ЗАПУСК POLLING...\n";
    echo "📱 Отправь /start боту @" . $me->getUsername() . "\n";
    echo "────────────────────────────────────\n\n";
    
    while (true) {
        try {
            // Получаем обновления
            $updates = $telegram->getUpdates($lastUpdateId + 1, 100, 30);
            
            if (!empty($updates)) {
                foreach ($updates as $update) {
                    $lastUpdateId = $update->getUpdateId();
                    
                    // Преобразуем в массив
                    $updateArray = json_decode(json_encode($update), true);
                    
                    if (isset($updateArray['message'])) {
                        $bot->handleMessage($updateArray['message']);
                    }
                    
                    if (isset($updateArray['callback_query'])) {
                        $bot->handleCallback($updateArray['callback_query']);
                    }
                }
            }
            
        } catch (Exception $e) {
            // Игнорируем таймауты
            if (strpos($e->getMessage(), 'timeout') === false) {
                echo "❌ Ошибка: " . $e->getMessage() . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Критическая ошибка: " . $e->getMessage() . "\n";
}