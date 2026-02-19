<?php
// bot_full.php - ИСПРАВЛЕННАЯ ВЕРСИЯ

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/kinopoisk_api.php';

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class MovieBotFull {
    private $telegram;
    private $db;
    private $api;
    
    public function __construct() {
        $this->telegram = new BotApi(BOT_TOKEN);
        $this->db = Database::getInstance();
        $this->api = new KinopoiskAPI();
    }
    
    // ===================== ОБРАБОТКА СООБЩЕНИЙ =====================
    
    public function handleMessage($message) {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $text = $message['text'] ?? '';
        $name = $message['from']['first_name'] ?? 'User';
        
        // Получаем или создаем пользователя
        if ($this->db->isConnected()) {
            $this->db->getOrCreateUser(
                $userId,
                $message['from']['username'] ?? '',
                $name,
                $message['from']['last_name'] ?? ''
            );
        }
        
        // Обработка команд
        switch ($text) {
            case '/start':
                $this->sendStart($chatId, $name);
                break;
            case '/help':
                $this->sendHelp($chatId);
                break;
            case '/movie':
                $this->sendRandomMovie($chatId, $userId);
                break;
            case '/favorites':
                $this->showFavorites($chatId, $userId);
                break;
            default:
                $this->sendDefault($chatId);
        }
    }
    
    // ===================== ОБРАБОТКА CALLBACK =====================
    
    public function handleCallback($callback) {
        $data = $callback['data'];
        $chatId = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $userId = $callback['from']['id'];
        $callbackId = $callback['id'];
        
        // Сразу отвечаем на callback
        $this->telegram->answerCallbackQuery($callbackId, "", false);
        
        // Категории
        if (strpos($data, 'cat_') === 0) {
            $category = substr($data, 4);
            $this->sendMovieByCategory($chatId, $userId, $messageId, $category);
        }
        
        // Навигация
        elseif ($data == 'back_to_categories') {
            $this->editMessageWithCategories($chatId, $messageId);
        }
        elseif ($data == 'more_movie') {
            $this->sendRandomMovie($chatId, $userId, $messageId);
        }
        
        // Избранное
        elseif (strpos($data, 'add_fav_') === 0) {
            $filmId = (int)substr($data, 8);
            $this->addToFavorites($chatId, $userId, $messageId, $filmId, $callbackId);
        }
        elseif (strpos($data, 'remove_fav_') === 0) {
            $filmId = (int)substr($data, 11);
            $this->removeFromFavorites($chatId, $userId, $messageId, $filmId, $callbackId);
        }
        elseif ($data == 'show_favorites') {
            $this->showFavoritesCallback($chatId, $userId, $messageId);
        }
        elseif (strpos($data, 'fav_') === 0) {
            $this->navigateFavorites($chatId, $userId, $messageId, $data);
        }
        
        // Возврат к фильму
        elseif (strpos($data, 'back_to_movie_') === 0) {
            $filmId = (int)substr($data, 14);
            $this->returnToMovie($chatId, $messageId, $filmId, $userId);
        }
    }
    
    // ===================== ОСНОВНЫЕ ФУНКЦИИ =====================
    
    private function sendStart($chatId, $name) {
        $text = "🎬 *Добро пожаловать, $name!*\n\n";
        $text .= "Я — *Movie Master*, твой персональный гид в мире кино! 🍿\n\n";
        $text .= "👇 *Выбери категорию и начнем!*";
        
        $keyboard = $this->getCategoriesKeyboard();
        
        $this->telegram->sendMessage(
            $chatId,
            $text,
            'Markdown',
            false,
            null,
            $keyboard
        );
    }
    
    private function sendHelp($chatId) {
        $text = "🆘 *Помощь по командам*\n\n";
        $text .= "/start - Начать работу\n";
        $text .= "/movie - Случайный фильм\n";
        $text .= "/favorites - Мои избранные фильмы\n";
        $text .= "/help - Эта справка\n\n";
        $text .= "*Приятного просмотра!* 🍿";
        
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '📚 К категориям', 'callback_data' => 'back_to_categories']]
        ]);
        
        $this->telegram->sendMessage(
            $chatId,
            $text,
            'Markdown',
            false,
            null,
            $keyboard
        );
    }
    
    private function getCategoriesKeyboard() {
        return new InlineKeyboardMarkup([
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
        ]);
    }
    
    private function getMovieKeyboard($filmId, $inFavorites = false) {
        $buttons = [];
        
        // Кнопка избранного
        $buttons[] = [
            ['text' => $inFavorites ? '✅ В избранном' : '⭐ В избранное', 
             'callback_data' => $inFavorites ? "remove_fav_$filmId" : "add_fav_$filmId"]
        ];
        
        // Кнопки навигации
        $buttons[] = [
            ['text' => '🎲 Еще фильм', 'callback_data' => 'more_movie'],
            ['text' => '📚 К списку', 'callback_data' => 'back_to_categories']
        ];
        
        return new InlineKeyboardMarkup($buttons);
    }
    
    private function getFavoritesKeyboard($userId, $current, $total, $filmId) {
        return new InlineKeyboardMarkup([
            [
                ['text' => '⬅️', 'callback_data' => 'fav_prev'],
                ['text' => "$current/$total", 'callback_data' => 'none'],
                ['text' => '➡️', 'callback_data' => 'fav_next']
            ],
            [
                ['text' => '📚 К списку', 'callback_data' => 'back_to_categories']
            ]
        ]);
    }
    
    private function sendMovieByCategory($chatId, $userId, $messageId, $category) {
        // Получаем фильм по категории
        $movieItem = $this->api->getRandomMovie($category);
        
        if (!$movieItem || !isset($movieItem['kinopoiskId'])) {
            $keyboard = new InlineKeyboardMarkup([
                [['text' => '📚 К списку', 'callback_data' => 'back_to_categories']]
            ]);
            
            $this->telegram->editMessageText(
                $chatId,
                $messageId,
                "😅 Не удалось найти фильм в этой категории",
                null,
                false,
                $keyboard
            );
            return;
        }
        
        $filmId = $movieItem['kinopoiskId'];
        $this->sendMovie($chatId, $userId, $messageId, $filmId, $movieItem, $category);
    }
    
    private function sendRandomMovie($chatId, $userId, $messageId = null) {
        if ($messageId) {
            $this->telegram->editMessageText(
                $chatId,
                $messageId,
                "⏳ Загружаем случайный фильм..."
            );
        } else {
            $msg = $this->telegram->sendMessage($chatId, "⏳ Загружаем фильм...");
            $messageId = $msg->getMessageId();
        }
        
        $movieItem = $this->api->getRandomMovie();
        
        if (!$movieItem || !isset($movieItem['kinopoiskId'])) {
            $keyboard = new InlineKeyboardMarkup([
                [['text' => '📚 К списку', 'callback_data' => 'back_to_categories']]
            ]);
            
            $this->telegram->editMessageText(
                $chatId,
                $messageId,
                "😅 Не удалось найти фильм",
                null,
                false,
                $keyboard
            );
            return;
        }
        
        $filmId = $movieItem['kinopoiskId'];
        $this->sendMovie($chatId, $userId, $messageId, $filmId, $movieItem, 'random');
    }
    
    private function sendMovie($chatId, $userId, $messageId, $filmId, $movieData, $category) {
        // Получаем полную информацию о фильме
        $fullMovieData = $this->api->getMovieById($filmId) ?: $movieData;
        
        // Кэшируем в БД
        if ($this->db->isConnected()) {
            $this->db->cacheMovie($fullMovieData);
            // Логируем просмотр
            $this->db->logMovieView($userId, $filmId, $category);
        }
        
        $text = $this->api->formatMovieInfo($fullMovieData);
        
        // Проверяем, в избранном ли фильм
        $inFavorites = false;
        if ($this->db->isConnected()) {
            $favorites = $this->db->getFavorites($userId);
            foreach ($favorites as $fav) {
                if (($fav['kinopoiskId'] ?? 0) == $filmId) {
                    $inFavorites = true;
                    break;
                }
            }
        }
        
        $keyboard = $this->getMovieKeyboard($filmId, $inFavorites);
        
        // Удаляем старое сообщение
        try {
            $this->telegram->deleteMessage($chatId, $messageId);
        } catch (Exception $e) {
            // Игнорируем ошибку удаления
        }
        
        // Отправляем с постером если есть
        if (!empty($fullMovieData['posterUrl'])) {
            $this->telegram->sendPhoto(
                $chatId,
                $fullMovieData['posterUrl'],
                $text,
                null,
                $keyboard,
                'Markdown'
            );
        } else {
            $this->telegram->sendMessage(
                $chatId,
                $text,
                'Markdown',
                false,
                null,
                $keyboard
            );
        }
    }
    
    private function returnToMovie($chatId, $messageId, $filmId, $userId) {
        $movieData = $this->db->isConnected() ? 
            ($this->db->getCachedMovie($filmId) ?: $this->api->getMovieById($filmId)) : 
            $this->api->getMovieById($filmId);
        
        if (!$movieData) {
            $keyboard = new InlineKeyboardMarkup([
                [['text' => '📚 К списку', 'callback_data' => 'back_to_categories']]
            ]);
            
            $this->telegram->editMessageText(
                $chatId,
                $messageId,
                "❌ Фильм не найден",
                null,
                false,
                $keyboard
            );
            return;
        }
        
        $text = $this->api->formatMovieInfo($movieData);
        
        $inFavorites = false;
        if ($this->db->isConnected()) {
            $favorites = $this->db->getFavorites($userId);
            foreach ($favorites as $fav) {
                if (($fav['kinopoiskId'] ?? 0) == $filmId) {
                    $inFavorites = true;
                    break;
                }
            }
        }
        
        $keyboard = $this->getMovieKeyboard($filmId, $inFavorites);
        
        try {
            $this->telegram->deleteMessage($chatId, $messageId);
        } catch (Exception $e) {}
        
        if (!empty($movieData['posterUrl'])) {
            $this->telegram->sendPhoto(
                $chatId,
                $movieData['posterUrl'],
                $text,
                null,
                $keyboard,
                'Markdown'
            );
        } else {
            $this->telegram->sendMessage(
                $chatId,
                $text,
                'Markdown',
                false,
                null,
                $keyboard
            );
        }
    }
    
    private function editMessageWithCategories($chatId, $messageId) {
        $keyboard = $this->getCategoriesKeyboard();
        
        $this->telegram->editMessageText(
            $chatId,
            $messageId,
            "🎬 *Выбери категорию:*",
            'Markdown',
            false,
            $keyboard
        );
    }
    
    private function addToFavorites($chatId, $userId, $messageId, $filmId, $callbackId) {
        if (!$this->db->isConnected()) {
            $this->telegram->answerCallbackQuery($callbackId, "❌ База данных не доступна", true);
            return;
        }
        
        $movieData = $this->db->getCachedMovie($filmId) ?: $this->api->getMovieById($filmId);
        
        if ($movieData) {
            $success = $this->db->addToFavorites($userId, $movieData);
            
            $this->telegram->answerCallbackQuery(
                $callbackId,
                $success ? "✅ Добавлено в избранное!" : "⚠️ Уже в избранном",
                true
            );
            
            if ($success) {
                $keyboard = $this->getMovieKeyboard($filmId, true);
                $this->telegram->editMessageReplyMarkup(
                    $chatId,
                    $messageId,
                    $keyboard
                );
            }
        }
    }
    
    private function removeFromFavorites($chatId, $userId, $messageId, $filmId, $callbackId) {
        if (!$this->db->isConnected()) {
            $this->telegram->answerCallbackQuery($callbackId, "❌ База данных не доступна", true);
            return;
        }
        
        $success = $this->db->removeFromFavorites($userId, $filmId);
        
        $this->telegram->answerCallbackQuery(
            $callbackId,
            $success ? "✅ Удалено из избранного" : "⚠️ Ошибка",
            true
        );
        
        if ($success) {
            $keyboard = $this->getMovieKeyboard($filmId, false);
            $this->telegram->editMessageReplyMarkup(
                $chatId,
                $messageId,
                $keyboard
            );
        }
    }
    
    private function showFavorites($chatId, $userId) {
        if (!$this->db->isConnected()) {
            $this->telegram->sendMessage($chatId, "❌ База данных не доступна");
            return;
        }
        
        $favorites = $this->db->getFavorites($userId);
        
        if (empty($favorites)) {
            $keyboard = new InlineKeyboardMarkup([
                [['text' => '📚 К списку', 'callback_data' => 'back_to_categories']]
            ]);
            
            $this->telegram->sendMessage(
                $chatId,
                "⭐ *У тебя пока нет избранных фильмов*",
                'Markdown',
                false,
                null,
                $keyboard
            );
            return;
        }
        
        // Используем сессию для навигации
        session_start();
        $_SESSION['favorites'][$userId] = $favorites;
        $_SESSION['fav_index'][$userId] = 0;
        
        $movie = $favorites[0];
        $text = "⭐ *Избранное* (1/" . count($favorites) . ")\n\n";
        $text .= $this->api->formatMovieInfo($movie);
        
        $keyboard = $this->getFavoritesKeyboard($userId, 1, count($favorites), $movie['kinopoiskId'] ?? 0);
        
        if (!empty($movie['posterUrl'])) {
            $this->telegram->sendPhoto(
                $chatId,
                $movie['posterUrl'],
                $text,
                null,
                $keyboard,
                'Markdown'
            );
        } else {
            $this->telegram->sendMessage(
                $chatId,
                $text,
                'Markdown',
                false,
                null,
                $keyboard
            );
        }
    }
    
    private function showFavoritesCallback($chatId, $userId, $messageId) {
        if (!$this->db->isConnected()) {
            $keyboard = new InlineKeyboardMarkup([
                [['text' => '📚 К списку', 'callback_data' => 'back_to_categories']]
            ]);
            
            $this->telegram->editMessageText(
                $chatId,
                $messageId,
                "❌ База данных не доступна",
                null,
                false,
                $keyboard
            );
            return;
        }
        
        $favorites = $this->db->getFavorites($userId);
        
        if (empty($favorites)) {
            $keyboard = new InlineKeyboardMarkup([
                [['text' => '📚 К списку', 'callback_data' => 'back_to_categories']]
            ]);
            
            $this->telegram->editMessageText(
                $chatId,
                $messageId,
                "⭐ *У тебя пока нет избранных фильмов*",
                'Markdown',
                false,
                $keyboard
            );
            return;
        }
        
        session_start();
        $_SESSION['favorites'][$userId] = $favorites;
        $_SESSION['fav_index'][$userId] = 0;
        
        $movie = $favorites[0];
        $text = "⭐ *Избранное* (1/" . count($favorites) . ")\n\n";
        $text .= $this->api->formatMovieInfo($movie);
        
        $keyboard = $this->getFavoritesKeyboard($userId, 1, count($favorites), $movie['kinopoiskId'] ?? 0);
        
        try {
            $this->telegram->deleteMessage($chatId, $messageId);
        } catch (Exception $e) {}
        
        if (!empty($movie['posterUrl'])) {
            $this->telegram->sendPhoto(
                $chatId,
                $movie['posterUrl'],
                $text,
                null,
                $keyboard,
                'Markdown'
            );
        } else {
            $this->telegram->sendMessage(
                $chatId,
                $text,
                'Markdown',
                false,
                null,
                $keyboard
            );
        }
    }
    
    private function navigateFavorites($chatId, $userId, $messageId, $direction) {
        session_start();
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
        
        try {
            $this->telegram->deleteMessage($chatId, $messageId);
        } catch (Exception $e) {}
        
        if (!empty($movie['posterUrl'])) {
            $this->telegram->sendPhoto(
                $chatId,
                $movie['posterUrl'],
                $text,
                null,
                $keyboard,
                'Markdown'
            );
        } else {
            $this->telegram->sendMessage(
                $chatId,
                $text,
                'Markdown',
                false,
                null,
                $keyboard
            );
        }
    }
    
    private function sendDefault($chatId) {
        $this->telegram->sendMessage(
            $chatId,
            "🤔 Я понимаю только команды.\nНапиши /help чтобы увидеть список команд."
        );
    }
}