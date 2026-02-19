<?php
// webhook_fixed.php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Client;

try {
    // Получаем входящие данные
    $content = file_get_contents('php://input');
    
    if (!$content) {
        http_response_code(400);
        exit;
    }
    
    // Создаем клиент для обработки веб-хука
    $bot = new Client(BOT_TOKEN);
    
    // Обработчик команды /start
    $bot->command('start', function ($message) use ($bot) {
        $chatId = $message->getChat()->getId();
        $bot->sendMessage(
            $chatId,
            "🎬 *Добро пожаловать!*\n\nЯ бот для поиска фильмов.",
            'Markdown'
        );
    });
    
    // Обработчик команды /help
    $bot->command('help', function ($message) use ($bot) {
        $chatId = $message->getChat()->getId();
        $bot->sendMessage(
            $chatId,
            "📋 *Доступные команды:*\n/start - Начать\n/help - Помощь\n/movie - Случайный фильм",
            'Markdown'
        );
    });
    
    // Обработчик всех сообщений
    $bot->on(function ($update) use ($bot) {
        $message = $update->getMessage();
        if ($message) {
            $chatId = $message->getChat()->getId();
            $text = $message->getText();
            
            if ($text && !str_starts_with($text, '/')) {
                $bot->sendMessage(
                    $chatId,
                    "Вы написали: $text\nИспользуйте /help для списка команд."
                );
            }
        }
    }, function () {
        return true;
    });
    
    // Запускаем обработку
    $bot->run();
    
} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
}