<?php
// movie_bot_no_db.php - ФИНАЛЬНАЯ РАБОЧАЯ ВЕРСИЯ

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class MovieBot {
    private $telegram;
    private $apiKey;
    private $favorites = [];
    
    public function __construct() {
        $this->telegram = new BotApi(BOT_TOKEN);
        $this->apiKey = KINOPOISK_API_KEY;
        mb_internal_encoding('UTF-8');
    }
    
    public function run() {
        $lastUpdateId = 0;
        
        while (true) {
            try {
                $updates = $this->telegram->getUpdates($lastUpdateId + 1, 10, 10);
                
                foreach ($updates as $update) {
                    $lastUpdateId = $update->getUpdateId();
                    
                    if ($message = $update->getMessage()) {
                        $this->handleMessage($message);
                    }
                    
                    if ($callbackQuery = $update->getCallbackQuery()) {
                        $this->handleCallback($callbackQuery);
                    }
                }
                
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'timeout') === false) {
                    echo "❌ Ошибка: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    private function handleMessage($message) {
        $chatId = $message->getChat()->getId();
        $text = $message->getText();
        $name = $message->getFrom()->getFirstName();
        
        echo date('H:i:s') . " 📨 $name: $text\n";
        
        switch ($text) {
            case '/start':
                $this->sendStart($chatId, $name);
                break;
            case '/help':
                $this->sendHelp($chatId);
                break;
            case '/movie':
                $this->sendRandomMovie($chatId);
                break;
            case '/favorites':
                $this->showFavorites($chatId);
                break;
            default:
                $this->sendMessage($chatId, "❓ Неизвестная команда. Напиши /start");
        }
    }
    
    private function handleCallback($callbackQuery) {
        $data = $callbackQuery->getData();
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $messageId = $callbackQuery->getMessage()->getMessageId();
        $callbackId = $callbackQuery->getId();
        
        echo date('H:i:s') . " 🔘 Callback: $data\n";
        
        // Сразу отвечаем на callback
        $this->telegram->answerCallbackQuery($callbackId, "");
        
        // Категории фильмов
        if (strpos($data, 'cat_') === 0) {
            $category = substr($data, 4);
            $this->sendMovieByCategory($chatId, $messageId, $category);
        }
        
        // Навигация
        elseif ($data == 'back_to_categories') {
            $this->showCategories($chatId, $messageId);
        }
        elseif ($data == 'more_movie') {
            $this->sendRandomMovie($chatId, $messageId);
        }
        
        // Избранное
        elseif (strpos($data, 'add_fav_') === 0) {
            $filmId = substr($data, 8);
            $this->addToFavorites($chatId, $messageId, $filmId, $callbackId);
        }
        elseif (strpos($data, 'remove_fav_') === 0) {
            $filmId = substr($data, 11);
            $this->removeFromFavorites($chatId, $messageId, $filmId, $callbackId);
        }
        elseif ($data == 'show_favorites') {
            $this->showFavorites($chatId, $messageId);
        }
        
        // Навигация по избранному
        elseif (strpos($data, 'fav_') === 0) {
            $this->navigateFavorites($chatId, $messageId, $data);
        }
    }
    
    private function sendMessage($chatId, $text, $keyboard = null) {
        try {
            return $this->telegram->sendMessage(
                $chatId,
                $text,
                'HTML',
                false,
                null,
                $keyboard
            );
        } catch (Exception $e) {
            echo "❌ Ошибка sendMessage: " . $e->getMessage() . "\n";
        }
    }
    
    private function sendPhoto($chatId, $photo, $caption, $keyboard = null) {
        try {
            // Очищаем caption от специальных символов
            $caption = str_replace(['*', '_', '`', '[', ']'], '', $caption);
            $caption = mb_convert_encoding($caption, 'UTF-8', 'UTF-8');
            
            return $this->telegram->sendPhoto(
                $chatId,
                $photo,
                $caption,
                null,
                $keyboard,
                'HTML'
            );
        } catch (Exception $e) {
            echo "❌ Ошибка sendPhoto: " . $e->getMessage() . "\n";
            // Если фото не отправилось, пробуем отправить только текст
            return $this->sendMessage($chatId, $caption, $keyboard);
        }
    }
    
    private function deleteMessage($chatId, $messageId) {
        try {
            $this->telegram->deleteMessage($chatId, $messageId);
        } catch (Exception $e) {
            // Игнорируем ошибки удаления
        }
    }
    
    private function getMainKeyboard() {
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
        return new InlineKeyboardMarkup([
            [
                ['text' => $inFavorites ? '✅ В избранном' : '⭐ В избранное', 
                 'callback_data' => $inFavorites ? "remove_fav_$filmId" : "add_fav_$filmId"]
            ],
            [
                ['text' => '🎲 Еще фильм', 'callback_data' => 'more_movie'],
                ['text' => '📚 К списку', 'callback_data' => 'back_to_categories']
            ]
        ]);
    }
    
    private function getFavoritesKeyboard($chatId, $current, $total) {
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
    
    private function sendStart($chatId, $name) {
        $text = "🎬 Добро пожаловать, $name!\n\n";
        $text .= "Я — твой персональный гид в мире кино! 🍿\n\n";
        $text .= "👇 Выбери категорию и начнем!";
        
        $this->sendMessage($chatId, $text, $this->getMainKeyboard());
    }
    
    private function sendHelp($chatId) {
        $text = "🆘 Помощь по командам\n\n";
        $text .= "/start - Начать работу\n";
        $text .= "/movie - Случайный фильм\n";
        $text .= "/favorites - Мои избранные фильмы\n";
        $text .= "/help - Эта справка\n\n";
        $text .= "Приятного просмотра! 🍿";
        
        $keyboard = new InlineKeyboardMarkup([
            [['text' => '📚 К категориям', 'callback_data' => 'back_to_categories']]
        ]);
        
        $this->sendMessage($chatId, $text, $keyboard);
    }
    
    private function showCategories($chatId, $messageId) {
        // Удаляем старое сообщение
        $this->deleteMessage($chatId, $messageId);
        
        // Отправляем новое с категориями
        $this->sendStart($chatId, '');
    }
    
    private function makeRequest($url, $params = []) {
        $ch = curl_init();
        $fullUrl = $url . (empty($params) ? '' : '?' . http_build_query($params));
        
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-KEY: ' . $this->apiKey,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            return json_decode($response, true);
        }
        
        return null;
    }
    
    private function getMovieById($filmId) {
        $url = "https://kinopoiskapiunofficial.tech/api/v2.2/films/$filmId";
        return $this->makeRequest($url);
    }
    
    private function getRandomMovie($category = null) {
        $categories = [
            'popular' => ['url' => 'https://kinopoiskapiunofficial.tech/api/v2.2/films/collections', 'params' => ['type' => 'TOP_POPULAR_ALL', 'page' => rand(1, 3)]],
            'new' => ['url' => 'https://kinopoiskapiunofficial.tech/api/v2.2/films/premieres', 'params' => ['year' => date('Y'), 'month' => date('m')]],
            'comedy' => ['url' => 'https://kinopoiskapiunofficial.tech/api/v2.2/films', 'params' => ['genres' => 13, 'order' => 'RATING', 'page' => rand(1, 3)]],
            'scifi' => ['url' => 'https://kinopoiskapiunofficial.tech/api/v2.2/films', 'params' => ['genres' => 6, 'order' => 'RATING', 'page' => rand(1, 3)]],
            'drama' => ['url' => 'https://kinopoiskapiunofficial.tech/api/v2.2/films', 'params' => ['genres' => 1, 'order' => 'RATING', 'page' => rand(1, 3)]],
            'action' => ['url' => 'https://kinopoiskapiunofficial.tech/api/v2.2/films', 'params' => ['genres' => 11, 'order' => 'RATING', 'page' => rand(1, 3)]],
            'cartoon' => ['url' => 'https://kinopoiskapiunofficial.tech/api/v2.2/films', 'params' => ['genres' => 18, 'order' => 'RATING', 'page' => rand(1, 3)]],
            'series' => ['url' => 'https://kinopoiskapiunofficial.tech/api/v2.2/films', 'params' => ['type' => 'TV_SERIES', 'order' => 'RATING', 'page' => rand(1, 3)]],
        ];
        
        if (!$category || $category == 'random') {
            $category = array_rand($categories);
        }
        
        if (isset($categories[$category])) {
            $data = $this->makeRequest($categories[$category]['url'], $categories[$category]['params']);
            
            if ($data && isset($data['items']) && !empty($data['items'])) {
                $movies = $data['items'];
                
                if ($category == 'foreign' && !empty($movies)) {
                    $filtered = [];
                    foreach ($movies as $movie) {
                        $isRussian = false;
                        if (isset($movie['countries'])) {
                            foreach ($movie['countries'] as $country) {
                                $countryName = is_array($country) ? ($country['country'] ?? '') : $country;
                                if (strpos($countryName, 'Россия') !== false || 
                                    strpos($countryName, 'СССР') !== false) {
                                    $isRussian = true;
                                    break;
                                }
                            }
                        }
                        if (!$isRussian) {
                            $filtered[] = $movie;
                        }
                    }
                    if (!empty($filtered)) {
                        $movies = $filtered;
                    }
                }
                
                if (!empty($movies)) {
                    return $movies[array_rand($movies)];
                }
            }
        }
        
        // Запасной вариант - популярные
        $data = $this->makeRequest('https://kinopoiskapiunofficial.tech/api/v2.2/films/collections', 
                                   ['type' => 'TOP_POPULAR_ALL', 'page' => 1]);
        if ($data && isset($data['items']) && !empty($data['items'])) {
            return $data['items'][array_rand($data['items'])];
        }
        
        return null;
    }
    
    private function formatMovieInfo($movie) {
        $title = $movie['nameRu'] ?? $movie['nameEn'] ?? $movie['nameOriginal'] ?? 'Н/Д';
        $year = $movie['year'] ?? '????';
        $rating = $movie['ratingKinopoisk'] ?? $movie['rating'] ?? 0;
        
        $genres = [];
        if (isset($movie['genres'])) {
            foreach ($movie['genres'] as $g) {
                if (is_array($g) && isset($g['genre'])) {
                    $genres[] = $g['genre'];
                }
            }
        }
        
        $countries = [];
        if (isset($movie['countries'])) {
            foreach ($movie['countries'] as $c) {
                if (is_array($c) && isset($c['country'])) {
                    $countries[] = $c['country'];
                }
            }
        }
        
        $description = $movie['description'] ?? $movie['shortDescription'] ?? 'Нет описания';
        if (strlen($description) > 300) {
            $description = substr($description, 0, 300) . '...';
        }
        
        // Очищаем описание от проблемных символов
        $description = preg_replace('/[^\p{L}\p{N}\p{P}\p{Z}]/u', '', $description);
        
        $stars = str_repeat('⭐', (int)($rating / 2));
        
        $text = "🎬 $title ($year)\n\n";
        $text .= "$stars Рейтинг: $rating/10\n";
        $text .= "🎭 Жанры: " . (empty($genres) ? 'Н/Д' : implode(', ', array_slice($genres, 0, 3))) . "\n";
        $text .= "🌍 Страны: " . (empty($countries) ? 'Н/Д' : implode(', ', array_slice($countries, 0, 2))) . "\n\n";
        $text .= "📖 $description\n\n";
        $text .= "🔗 Смотреть на Кинопоиске: https://www.kinopoisk.ru/film/{$movie['kinopoiskId']}/";
        
        return $text;
    }
    
    private function sendMovieByCategory($chatId, $messageId, $category) {
        // Удаляем сообщение с кнопками
        $this->deleteMessage($chatId, $messageId);
        
        // Показываем загрузку
        $loadingMsg = $this->sendMessage($chatId, "⏳ Ищем фильм...");
        
        $movie = $this->getRandomMovie($category);
        
        if (!$movie || !isset($movie['kinopoiskId'])) {
            $this->deleteMessage($chatId, $loadingMsg->getMessageId());
            
            $keyboard = new InlineKeyboardMarkup([
                [['text' => '📚 К списку', 'callback_data' => 'back_to_categories']]
            ]);
            $this->sendMessage($chatId, "😅 Не удалось найти фильм", $keyboard);
            return;
        }
        
        $this->deleteMessage($chatId, $loadingMsg->getMessageId());
        $this->sendMovie($chatId, $movie);
    }
    
    private function sendRandomMovie($chatId, $messageId = null) {
        if ($messageId) {
            $this->deleteMessage($chatId, $messageId);
        }
        
        $loadingMsg = $this->sendMessage($chatId, "⏳ Ищем фильм...");
        
        $movie = $this->getRandomMovie();
        
        if (!$movie || !isset($movie['kinopoiskId'])) {
            $this->deleteMessage($chatId, $loadingMsg->getMessageId());
            
            $keyboard = new InlineKeyboardMarkup([
                [['text' => '📚 К списку', 'callback_data' => 'back_to_categories']]
            ]);
            $this->sendMessage($chatId, "😅 Не удалось найти фильм", $keyboard);
            return;
        }
        
        $this->deleteMessage($chatId, $loadingMsg->getMessageId());
        $this->sendMovie($chatId, $movie);
    }
    
    private function sendMovie($chatId, $movieData) {
        $fullData = $this->getMovieById($movieData['kinopoiskId']) ?: $movieData;
        
        $text = $this->formatMovieInfo($fullData);
        $filmId = $fullData['kinopoiskId'];
        
        $inFavorites = isset($this->favorites[$chatId]) && in_array($filmId, $this->favorites[$chatId]);
        
        $keyboard = $this->getMovieKeyboard($filmId, $inFavorites);
        
        if (!empty($fullData['posterUrl'])) {
            $this->sendPhoto($chatId, $fullData['posterUrl'], $text, $keyboard);
        } else {
            $this->sendMessage($chatId, $text, $keyboard);
        }
    }
    
    private function addToFavorites($chatId, $messageId, $filmId, $callbackId) {
        if (!isset($this->favorites[$chatId])) {
            $this->favorites[$chatId] = [];
        }
        
        if (!in_array($filmId, $this->favorites[$chatId])) {
            $this->favorites[$chatId][] = $filmId;
            $this->telegram->answerCallbackQuery($callbackId, "✅ Добавлено в избранное!", true);
            
            $keyboard = $this->getMovieKeyboard($filmId, true);
            $this->telegram->editMessageReplyMarkup($chatId, $messageId, $keyboard);
        } else {
            $this->telegram->answerCallbackQuery($callbackId, "⚠️ Уже в избранном", true);
        }
    }
    
    private function removeFromFavorites($chatId, $messageId, $filmId, $callbackId) {
        if (isset($this->favorites[$chatId])) {
            $key = array_search($filmId, $this->favorites[$chatId]);
            if ($key !== false) {
                unset($this->favorites[$chatId][$key]);
                $this->favorites[$chatId] = array_values($this->favorites[$chatId]);
                $this->telegram->answerCallbackQuery($callbackId, "✅ Удалено из избранного", true);
                
                $keyboard = $this->getMovieKeyboard($filmId, false);
                $this->telegram->editMessageReplyMarkup($chatId, $messageId, $keyboard);
                return;
            }
        }
        
        $this->telegram->answerCallbackQuery($callbackId, "⚠️ Ошибка", true);
    }
    
    private function showFavorites($chatId, $messageId = null) {
        if ($messageId) {
            $this->deleteMessage($chatId, $messageId);
        }
        
        if (empty($this->favorites[$chatId])) {
            $text = "⭐ У тебя пока нет избранных фильмов";
            $keyboard = new InlineKeyboardMarkup([
                [['text' => '📚 К списку', 'callback_data' => 'back_to_categories']]
            ]);
            $this->sendMessage($chatId, $text, $keyboard);
            return;
        }
        
        $favorites = [];
        foreach ($this->favorites[$chatId] as $filmId) {
            $movie = $this->getMovieById($filmId);
            if ($movie) {
                $favorites[] = $movie;
            }
        }
        
        if (empty($favorites)) {
            $text = "⭐ Не удалось загрузить избранные фильмы";
            $keyboard = new InlineKeyboardMarkup([
                [['text' => '📚 К списку', 'callback_data' => 'back_to_categories']]
            ]);
            $this->sendMessage($chatId, $text, $keyboard);
            return;
        }
        
        session_start();
        $_SESSION['favorites'][$chatId] = $favorites;
        $_SESSION['fav_index'][$chatId] = 0;
        
        $movie = $favorites[0];
        $text = "⭐ Избранное (1/" . count($favorites) . ")\n\n";
        $text .= $this->formatMovieInfo($movie);
        
        $keyboard = $this->getFavoritesKeyboard($chatId, 1, count($favorites));
        
        if (!empty($movie['posterUrl'])) {
            $this->sendPhoto($chatId, $movie['posterUrl'], $text, $keyboard);
        } else {
            $this->sendMessage($chatId, $text, $keyboard);
        }
    }
    
    private function navigateFavorites($chatId, $messageId, $direction) {
        session_start();
        
        if (!isset($_SESSION['favorites'][$chatId])) {
            return;
        }
        
        $favorites = $_SESSION['favorites'][$chatId];
        $current = $_SESSION['fav_index'][$chatId] ?? 0;
        $total = count($favorites);
        
        if ($direction == 'fav_prev') {
            $current = ($current - 1 + $total) % $total;
        } elseif ($direction == 'fav_next') {
            $current = ($current + 1) % $total;
        } else {
            return;
        }
        
        $_SESSION['fav_index'][$chatId] = $current;
        
        $movie = $favorites[$current];
        $text = "⭐ Избранное (" . ($current + 1) . "/$total)\n\n";
        $text .= $this->formatMovieInfo($movie);
        
        $keyboard = $this->getFavoritesKeyboard($chatId, $current + 1, $total);
        
        $this->deleteMessage($chatId, $messageId);
        
        if (!empty($movie['posterUrl'])) {
            $this->sendPhoto($chatId, $movie['posterUrl'], $text, $keyboard);
        } else {
            $this->sendMessage($chatId, $text, $keyboard);
        }
    }
}

echo "🎬 MOVIE BOT - БЕЗ БАЗЫ ДАННЫХ\n";
echo "==============================\n\n";

try {
    $bot = new MovieBot();
    $bot->run();
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}