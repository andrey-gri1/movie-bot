<?php
// simple_bot.php - РАБОЧАЯ ВЕРСИЯ

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

echo "🤖 ПРОСТОЙ БОТ\n";
echo "==============\n\n";

try {
    $telegram = new BotApi(BOT_TOKEN);
    $telegram->deleteWebhook();
    
    $me = $telegram->getMe();
    echo "✅ Бот: @" . $me->getUsername() . "\n\n";
    
    $lastUpdateId = 0;
    
    while (true) {
        try {
            $updates = $telegram->getUpdates($lastUpdateId + 1, 10, 10);
            
            foreach ($updates as $update) {
                $lastUpdateId = $update->getUpdateId();
                
                if ($message = $update->getMessage()) {
                    $chatId = $message->getChat()->getId();
                    $text = $message->getText();
                    $name = $message->getFrom()->getFirstName();
                    
                    echo date('H:i:s') . " 📨 $name: $text\n";
                    
                    if ($text == '/start') {
                        // СОЗДАЕМ КНОПКИ ПРАВИЛЬНО (БЕЗ InlineKeyboardButton)
                        $keyboard = new InlineKeyboardMarkup([
                            [
                                ['text' => '🎬 Популярные', 'callback_data' => 'cat_popular'],
                                ['text' => '🔥 Новинки', 'callback_data' => 'cat_new']
                            ],
                            [
                                ['text' => '😂 Комедии', 'callback_data' => 'cat_comedy'],
                                ['text' => '🚀 Фантастика', 'callback_data' => 'cat_scifi']
                            ],
                            [
                                ['text' => '⭐ Избранное', 'callback_data' => 'show_favorites']
                            ]
                        ]);
                        
                        // Отправляем сообщение
                        $telegram->sendMessage(
                            $chatId,
                            "👋 Привет, $name! Выбери категорию:",
                            null,
                            false,
                            null,
                            $keyboard
                        );
                        
                        echo "   ✅ Отправлено меню\n";
                        
                    } elseif ($text == '/help') {
                        $telegram->sendMessage($chatId, "Команды: /start, /help, /movie");
                        echo "   ✅ Отправлена помощь\n";
                        
                    } elseif ($text == '/movie') {
                        $telegram->sendMessage($chatId, "🍿 Случайный фильм будет здесь...");
                        echo "   ✅ Отправлен случайный фильм\n";
                        
                    } else {
                        $telegram->sendMessage($chatId, "❓ Неизвестная команда. Напиши /start");
                        echo "   ✅ Отправлен ответ\n";
                    }
                }
                
                if ($callbackQuery = $update->getCallbackQuery()) {
                    $data = $callbackQuery->getData();
                    $chatId = $callbackQuery->getMessage()->getChat()->getId();
                    $callbackId = $callbackQuery->getId();
                    $messageId = $callbackQuery->getMessage()->getMessageId();
                    
                    echo date('H:i:s') . " 🔘 Callback: $data\n";
                    
                    // Отвечаем на callback
                    $telegram->answerCallbackQuery($callbackId, "✅ Выбрано: $data");
                    
                    // Отправляем сообщение о выборе
                    $response = "✅ Ты выбрал: ";
                    switch ($data) {
                        case 'cat_popular': $response .= "Популярные фильмы"; break;
                        case 'cat_new': $response .= "Новинки"; break;
                        case 'cat_comedy': $response .= "Комедии"; break;
                        case 'cat_scifi': $response .= "Фантастика"; break;
                        case 'show_favorites': $response .= "Избранное"; break;
                        default: $response .= $data;
                    }
                    
                    $telegram->sendMessage($chatId, $response);
                }
            }
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'timeout') === false) {
                echo "❌ Ошибка: " . $e->getMessage() . "\n";
                echo "   Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}