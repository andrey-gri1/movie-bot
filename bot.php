<?php
// bot.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/kinopoisk_api.php';

use Telegram\Bot\Api;

class MovieBot {
    private $telegram;
    private $db;
    private $api;
    private $userData = [];
    
    public function __construct() {
        $this->telegram = new Api(BOT_TOKEN);
        $this->db = Database::getInstance();
        $this->api = new KinopoiskAPI();
    }
    
    public function handleWebhook($content) {
        $update = json_decode($content, true);
        
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }
    }
    
    private function handleMessage($message) {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $text = $message['text'] ?? '';
        
        // Получаем или создаем пользователя
        $user = $this->db->getOrCreateUser(
            $userId,
            $message['from']['username'] ?? '',
            $message['from']['first_name'] ?? '',
            $message['from']['last_name'] ?? ''
        );
        
        if ($text == '/start') {
            $this->sendStartMessage($chatId, $user);
        } elseif ($text == '/movie') {
            $this->sendRandomMovie($chatId, $userId);
        } elseif ($text == '/favorites') {
            $this->showFavorites($chatId, $userId);
        } elseif ($text == '/help') {
            $this->sendHelp($chatId);
        } else {
            $this->sendDefaultMessage($chatId);
        }
    }
    
    private function handleCallbackQuery($callback) {
        $data = $callback['data'];
        $chatId = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $userId = $callback['from']['id'];
        
        // Получаем или создаем пользователя
        $user = $this->db->getOrCreateUser(
            $userId,
            $callback['from']['username'] ?? '',
            $callback['from']['first_name'] ?? '',
            $callback['from']['last_name'] ?? ''
        );
        
        if (strpos($data, 'cat_') === 0) {
            $category = substr($data, 4);
            $this->sendMovieByCategory($chatId, $userId, $messageId, $category);
        } elseif ($data == 'back_to_categories') {
            $this->editMessageWithCategories($chatId, $messageId);
        } elseif (strpos($data, 'add_fav_') === 0) {
            $filmId = (int)substr($data, 8);
            $this->addToFavorites($chatId, $userId, $messageId, $filmId);
        } elseif (strpos($data, 'remove_fav_') === 0) {
            $filmId = (int)substr($data, 11);
            $this->removeFromFavorites($chatId, $userId, $messageId, $filmId);
        } elseif ($data == 'show_favorites') {
            $this->showFavoritesCallback($chatId, $userId, $messageId);
        } elseif (strpos($data, 'fav_') === 0) {
            $this->navigateFavorites($chatId, $userId, $messageId, $data);
        }
    }
    
    private function sendStartMessage($chatId, $user) {
        $text = "🎬 *Добро пожаловать, {$user['first_name']}!*\n\n";
        $text .= "Я — *Movie Master*, твой персональный гид в мире кино! 🍿\n\n";
        $text .= "✨ *Что я умею:*\n";
        $text .= "• 🎥 Подбирать фильмы по категориям\n";
        $text .= "• 🔍 Показывать подробную информацию\n";
        $text .= "• ⭐ Сохранять в избранное\n\n";
        $text .= "📌 *Категории фильмов:*\n";
        $text .= "• Популярные\n• Новинки\n• Комедии\n• Фантастика\n";
        $text .= "• Драмы\n• Боевики\n• Зарубежные\n• Мультфильмы\n• Сериалы\n\n";
        $text .= "👇 *Выбери категорию и начнем!*";
        
        $keyboard = $this->getCategoriesKeyboard();
        
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($keyboard)
        ]);
    }
    
    private function getCategoriesKeyboard() {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🎬 Популярные', 'callback_data' => 'cat_popular'],
                    ['text' => '🔥 Новинки', 'callback_data' => 'cat_new']
                ],
                [
                    ['text' => '😂 Комедии', 'callback_data' => 'cat_comedy'],
                    ['text' => '🚀 Фантастика', 'callback_data' => 'cat_scifi']
                ],
                [
                    ['text' => '😢 Драмы', 'callback_data' => 'cat_drama'],
                    ['text' => '💥 Боевики', 'callback_data' => 'cat_action']
                ],
                [
                    ['text' => '🌍 Зарубежные', 'callback_data' => 'cat_foreign'],
                    ['text' => '🎬 Мультфильмы', 'callback_data' => 'cat_cartoon']
                ],
                [
                    ['text' => '📺 Сериалы', 'callback_data' => 'cat_series'],
                    ['text' => '🎲 Случайный', 'callback_data' => 'cat_random']
                ],
                [
                    ['text' => '⭐ Избранное', 'callback_data' => 'show_favorites']
                ]
            ]
        ];
    }
    
    private function getMovieKeyboard($filmId, $inFavorites = false) {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🎭 Съемочная группа', 'callback_data' => "info_staff_$filmId"],
                    ['text' => '📝 Факты', 'callback_data' => "info_facts_$filmId"]
                ],
                [
                    ['text' => '💰 Бюджет', 'callback_data' => "info_box_$filmId"],
                    ['text' => '🏆 Награды', 'callback_data' => "info_awards_$filmId"]
                ],
                [
                    ['text' => '🎬 Похожие', 'callback_data' => "info_similar_$filmId"],
                    ['text' => '📺 Где смотреть', 'callback_data' => "info_sources_$filmId"]
                ],
                [
                    ['text' => $inFavorites ? '✅ В избранном' : '⭐ В избранное', 
                     'callback_data' => $inFavorites ? "remove_fav_$filmId" : "add_fav_$filmId"]
                ],
                [
                    ['text' => '📚 К списку', 'callback_data' => 'back_to_categories']
                ]
            ]
        ];
    }
    
    private function sendMovieByCategory($chatId, $userId, $messageId, $category) {
        // Получаем случайный фильм по категории
        $movieItem = $this->api->getRandomMovie($category);
        
        if (!$movieItem) {
            $this->telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => "😅 Не удалось найти фильм",
                'reply_markup' => json_encode(['inline_keyboard' => [
                    [['text' => '📚 К списку', 'callback_data' => 'back_to_categories']]
                ]])
            ]);
            return;
        }
        
        $filmId = $movieItem['filmId'] ?? $movieItem['kinopoiskId'] ?? 0;
        
        if (!$filmId) {
            $this->telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => "😅 Не удалось найти фильм",
                'reply_markup' => json_encode(['inline_keyboard' => [
                    [['text' => '📚 К списку', 'callback_data' => 'back_to_categories']]
                ]])
            ]);
            return;
        }
        
        // Получаем полную информацию о фильме
        $movieData = $this->api->getMovieById($filmId);
        
        if (!$movieData) {
            $movieData = $movieItem;
        } else {
            // Кэшируем в БД
            $this->db->cacheMovie($movieData);
        }
        
        // Логируем просмотр
        $this->db->logMovieView($userId, $filmId, $category);
        
        $text = $this->api->formatMovieInfo($movieData);
        
        // Проверяем, в избранном ли фильм
        $favorites = $this->db->getFavorites($userId);
        $inFavorites = false;
        foreach ($favorites as $fav) {
            if (($fav['kinopoiskId'] ?? 0) == $filmId) {
                $inFavorites = true;
                break;
            }
        }
        
        $keyboard = $this->getMovieKeyboard($filmId, $inFavorites);
        
        // Отправляем с постером если есть
        if (!empty($movieData['posterUrl'])) {
            $this->telegram->deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $messageId
            ]);
            
            $this->telegram->sendPhoto([
                'chat_id' => $chatId,
                'photo' => $movieData['posterUrl'],
                'caption' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($keyboard)
            ]);
        } else {
            $this->telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($keyboard)
            ]);
        }
    }
    
    private function sendRandomMovie($chatId, $userId) {
        $msg = $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "⏳ Загружаем фильм..."
        ]);
        
        $this->sendMovieByCategory($chatId, $userId, $msg['message_id'], 'random');
    }
    
    private function editMessageWithCategories($chatId, $messageId) {
        $this->telegram->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => "🎬 *Выбери категорию:*",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode($this->getCategoriesKeyboard())
        ]);
    }
    
    private function addToFavorites($chatId, $userId, $messageId, $filmId) {
        $movieData = $this->db->getCachedMovie($filmId);
        
        if (!$movieData) {
            $movieData = $this->api->getMovieById($filmId);
        }
        
        if ($movieData) {
            $success = $this->db->addToFavorites($userId, $movieData);
            
            if ($success) {
                $this->telegram->answerCallbackQuery([
                    'callback_query_id' => $this->getCallbackId(),
                    'text' => "✅ Добавлено в избранное!",
                    'show_alert' => true
                ]);
                
                // Обновляем клавиатуру
                $keyboard = $this->getMovieKeyboard($filmId, true);
                
                $this->telegram->editMessageReplyMarkup([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'reply_markup' => json_encode($keyboard)
                ]);
            } else {
                $this->telegram->answerCallbackQuery([
                    'callback_query_id' => $this->getCallbackId(),
                    'text' => "⚠️ Уже в избранном!",
                    'show_alert' => true
                ]);
            }
        } else {
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $this->getCallbackId(),
                'text' => "❌ Ошибка",
                'show_alert' => true
            ]);
        }
    }
    
    private function removeFromFavorites($chatId, $userId, $messageId, $filmId) {
        $success = $this->db->removeFromFavorites($userId, $filmId);
        
        if ($success) {
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $this->getCallbackId(),
                'text' => "✅ Удалено из избранного",
                'show_alert' => true
            ]);
            
            // Обновляем клавиатуру
            $keyboard = $this->getMovieKeyboard($filmId, false);
            
            $this->telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reply_markup' => json_encode($keyboard)
            ]);
        } else {
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $this->getCallbackId(),
                'text' => "⚠️ Ошибка",
                'show_alert' => true
            ]);
        }
    }
    
    private function showFavorites($chatId, $userId) {
        $favorites = $this->db->getFavorites($userId);
        
        if (empty($favorites)) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "⭐ *У тебя пока нет избранных фильмов*",
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode(['inline_keyboard' => [
                    [['text' => '📚 К списку', 'callback_data' => 'back_to_categories']]
                ]])
            ]);
            return;
        }
        
        // Сохраняем в сессии
        $_SESSION['favorites'][$userId] = $favorites;
        $_SESSION['fav_index'][$userId] = 0;
        
        $movie = $favorites[0];
        $text = "⭐ *Избранное* (1/" . count($favorites) . ")\n\n";
        $text .= $this->api->formatMovieInfo($movie);
        
        $keyboard = $this->getFavoritesKeyboard($userId, 1, count($favorites), $movie['kinopoiskId'] ?? 0);
        
        if (!empty($movie['posterUrl'])) {
            $this->telegram->sendPhoto([
                'chat_id' => $chatId,
                'photo' => $movie['posterUrl'],
                'caption' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($keyboard)
            ]);
        } else {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($keyboard)
            ]);
        }
    }
    
    private function showFavoritesCallback($chatId, $userId, $messageId) {
        $favorites = $this->db->getFavorites($userId);
        
        if (empty($favorites)) {
            $this->telegram->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => "⭐ *У тебя пока нет избранных фильмов*",
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode(['inline_keyboard' => [
                    [['text' => '📚 К списку', 'callback_data' => 'back_to_categories']]
                ]])
            ]);
            return;
        }
        
        $_SESSION['favorites'][$userId] = $favorites;
        $_SESSION['fav_index'][$userId] = 0;
        
        $movie = $favorites[0];
        $text = "⭐ *Избранное* (1/" . count($favorites) . ")\n\n";
        $text .= $this->api->formatMovieInfo($movie);
        
        $keyboard = $this->getFavoritesKeyboard($userId, 1, count($favorites), $movie['kinopoiskId'] ?? 0);
        
        $this->telegram->deleteMessage([
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
        
        if (!empty($movie['posterUrl'])) {
            $this->telegram->sendPhoto([
                'chat_id' => $chatId,
                'photo' => $movie['posterUrl'],
                'caption' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($keyboard)
            ]);
        } else {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($keyboard)
            ]);
        }
    }
    
    private function getFavoritesKeyboard($userId, $current, $total, $filmId) {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '⬅️', 'callback_data' => 'fav_prev'],
                    ['text' => "$current/$total", 'callback_data' => 'none'],
                    ['text' => '➡️', 'callback_data' => 'fav_next']
                ],
                [
                    ['text' => '🎭 Подробнее', 'callback_data' => "info_staff_$filmId"],
                    ['text' => '📚 К списку', 'callback_data' => 'back_to_categories']
                ]
            ]
        ];
    }
    
    private function navigateFavorites($chatId, $userId, $messageId, $direction) {
        if (!isset($_SESSION['favorites'][$userId])) {
            return;
        }
        
        $favorites = $_SESSION['favorites'][$userId];
        $current = $_SESSION['fav_index'][$userId] ?? 0;
        $total = count($favorites);
        
        if ($direction == 'fav_prev') {
            $current = ($current - 1 + $total) % $total;
        } else {
            $current = ($current + 1) % $total;
        }
        
        $_SESSION['fav_index'][$userId] = $current;
        
        $movie = $favorites[$current];
        $text = "⭐ *Избранное* (" . ($current + 1) . "/$total)\n\n";
        $text .= $this->api->formatMovieInfo($movie);
        
        $keyboard = $this->getFavoritesKeyboard($userId, $current + 1, $total, $movie['kinopoiskId'] ?? 0);
        
        $this->telegram->deleteMessage([
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
        
        if (!empty($movie['posterUrl'])) {
            $this->telegram->sendPhoto([
                'chat_id' => $chatId,
                'photo' => $movie['posterUrl'],
                'caption' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($keyboard)
            ]);
        } else {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode($keyboard)
            ]);
        }
    }
    
    private function sendHelp($chatId) {
        $text = "🆘 *Помощь по командам*\n\n";
        $text .= "/start - Начать работу\n";
        $text .= "/movie - Случайный фильм\n";
        $text .= "/favorites - Мои избранные фильмы\n";
        $text .= "/help - Эта справка\n\n";
        $text .= "*Как пользоваться:*\n";
        $text .= "1. Нажми /start\n";
        $text .= "2. Выбери категорию\n";
        $text .= "3. Сохраняй фильмы в избранное\n\n";
        $text .= "*Приятного просмотра!* 🍿";
        
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown'
        ]);
    }
    
    private function sendDefaultMessage($chatId) {
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "🤔 Я понимаю только команды.\nНажми /start чтобы увидеть список команд."
        ]);
    }
    
    private function getCallbackId() {
        // Этот метод должен получать ID колбэка из контекста
        // В реальной реализации нужно хранить это в свойстве класса
        return '';
    }
}