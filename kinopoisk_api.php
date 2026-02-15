<?php
// kinopoisk_api.php

require_once __DIR__ . '/config.php';

class KinopoiskAPI {
    private $apiKey;
    private $baseUrl = "https://kinopoiskapiunofficial.tech/api";
    private $cache = [];
    
    public function __construct() {
        $this->apiKey = KINOPOISK_API_KEY;
    }
    
    private function makeRequest($url, $params = []) {
        $cacheKey = md5($url . json_encode($params));
        $cacheFile = CACHE_DIR . $cacheKey . '.json';
        
        // Проверяем кэш
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600)) {
            return json_decode(file_get_contents($cacheFile), true);
        }
        
        $ch = curl_init();
        $fullUrl = $url . (empty($params) ? '' : '?' . http_build_query($params));
        
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-KEY: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept-Encoding: gzip, deflate'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            // Сохраняем в кэш
            file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $data;
        }
        
        logMessage("❌ API request failed: $fullUrl (HTTP $httpCode)");
        return null;
    }
    
    public function getMovieById($filmId) {
        $url = $this->baseUrl . "/v2.2/films/$filmId";
        return $this->makeRequest($url);
    }
    
    public function getPopularMovies($page = 1) {
        $url = $this->baseUrl . "/v2.2/films/collections";
        return $this->makeRequest($url, ['type' => 'TOP_POPULAR_ALL', 'page' => $page]);
    }
    
    public function getPremieres($year = null, $month = null) {
        if (!$year) $year = date('Y');
        if (!$month) $month = strtoupper(date('F'));
        
        $url = $this->baseUrl . "/v2.2/films/premieres";
        return $this->makeRequest($url, ['year' => $year, 'month' => $month]);
    }
    
    public function getMoviesByGenre($genreId, $page = 1) {
        $url = $this->baseUrl . "/v2.2/films";
        return $this->makeRequest($url, [
            'genres' => $genreId,
            'order' => 'RATING',
            'type' => 'FILM',
            'page' => $page,
            'limit' => 20
        ]);
    }
    
    public function getRandomMovie($category = null) {
        $genreMap = [
            'comedy' => 13,
            'scifi' => 6,
            'drama' => 1,
            'action' => 11
        ];
        
        try {
            if ($category == 'popular') {
                $data = $this->getPopularMovies(rand(1, 3));
                if ($data && isset($data['items'])) {
                    return $data['items'][array_rand($data['items'])];
                }
            } elseif ($category == 'new') {
                $data = $this->getPremieres();
                if ($data && isset($data['items'])) {
                    return $data['items'][array_rand($data['items'])];
                }
            } elseif ($category == 'series') {
                $url = $this->baseUrl . "/v2.2/films";
                $data = $this->makeRequest($url, [
                    'order' => 'RATING',
                    'type' => 'TV_SERIES',
                    'page' => rand(1, 5),
                    'limit' => 20
                ]);
                if ($data && isset($data['items'])) {
                    return $data['items'][array_rand($data['items'])];
                }
            } elseif ($category == 'cartoon') {
                $url = $this->baseUrl . "/v2.2/films";
                $data = $this->makeRequest($url, [
                    'genres' => 18, // мультфильмы
                    'order' => 'RATING',
                    'type' => 'FILM',
                    'page' => rand(1, 5),
                    'limit' => 20
                ]);
                if ($data && isset($data['items'])) {
                    return $data['items'][array_rand($data['items'])];
                }
            } elseif ($category == 'foreign') {
                $url = $this->baseUrl . "/v2.2/films";
                $data = $this->makeRequest($url, [
                    'order' => 'RATING',
                    'type' => 'FILM',
                    'page' => rand(1, 5),
                    'limit' => 50
                ]);
                if ($data && isset($data['items'])) {
                    // Фильтруем российские фильмы
                    $foreign = array_filter($data['items'], function($item) {
                        if (!isset($item['countries'])) return true;
                        foreach ($item['countries'] as $country) {
                            if (strpos($country['country'], 'Россия') !== false || 
                                strpos($country['country'], 'СССР') !== false) {
                                return false;
                            }
                        }
                        return true;
                    });
                    if (!empty($foreign)) {
                        return $foreign[array_rand($foreign)];
                    }
                }
            } elseif ($category && isset($genreMap[$category])) {
                $data = $this->getMoviesByGenre($genreMap[$category], rand(1, 5));
                if ($data && isset($data['items'])) {
                    return $data['items'][array_rand($data['items'])];
                }
            }
            
            // По умолчанию - популярные
            $data = $this->getPopularMovies(rand(1, 3));
            if ($data && isset($data['items'])) {
                return $data['items'][array_rand($data['items'])];
            }
            
        } catch (Exception $e) {
            logMessage("❌ Error getting random movie: " . $e->getMessage());
        }
        
        return null;
    }
    
    public function formatMovieInfo($movieData) {
        if (!$movieData) {
            return "❌ Информация о фильме не найдена";
        }
        
        $title = $movieData['nameRu'] ?? $movieData['nameEn'] ?? 'Н/Д';
        $year = $movieData['year'] ?? 'N/A';
        $rating = $movieData['ratingKinopoisk'] ?? 0;
        $ratingImdb = $movieData['ratingImdb'] ?? 0;
        
        $genres = [];
        if (isset($movieData['genres'])) {
            foreach ($movieData['genres'] as $g) {
                $genres[] = $g['genre'];
            }
        }
        
        $countries = [];
        if (isset($movieData['countries'])) {
            foreach ($movieData['countries'] as $c) {
                $countries[] = $c['country'];
            }
        }
        
        $description = $movieData['description'] ?? $movieData['shortDescription'] ?? 'Нет описания';
        if (strlen($description) > 300) {
            $description = substr($description, 0, 300) . "...";
        }
        
        $stars = str_repeat("⭐", (int)($rating / 2));
        $imdbInfo = $ratingImdb ? " (IMDb: $ratingImdb)" : "";
        
        $type = $movieData['type'] ?? 'FILM';
        $typeEmoji = $type == 'TV_SERIES' ? "📺" : "🎬";
        
        $text = "$typeEmoji *$title* ($year)\n\n";
        $text .= "$stars Рейтинг: $rating/10$imdbInfo\n";
        $text .= "🎭 Жанры: " . (empty($genres) ? 'Н/Д' : implode(', ', array_slice($genres, 0, 3))) . "\n";
        $text .= "🌍 Страны: " . (empty($countries) ? 'Н/Д' : implode(', ', array_slice($countries, 0, 2))) . "\n\n";
        $text .= "📖 $description\n\n";
        $text .= "🔗 [Кинопоиск](https://www.kinopoisk.ru/film/{$movieData['kinopoiskId']}/)";
        
        return $text;
    }
}