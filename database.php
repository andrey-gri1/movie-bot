<?php
// database.php

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec("SET NAMES utf8mb4");
            
            $this->createTables();
            logMessage("✅ Database connected successfully");
        } catch (PDOException $e) {
            logMessage("❌ Database connection error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    private function createTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            user_id BIGINT PRIMARY KEY,
            username VARCHAR(255),
            first_name VARCHAR(255),
            last_name VARCHAR(255),
            language_code VARCHAR(10) DEFAULT 'ru',
            first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_active DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE,
            total_views INT DEFAULT 0,
            favorites_count INT DEFAULT 0,
            INDEX idx_last_active (last_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS user_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            stat_date DATE NOT NULL,
            views_count INT DEFAULT 0,
            UNIQUE KEY unique_user_date (user_id, stat_date),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS genre_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            genre VARCHAR(100) NOT NULL,
            views_count INT DEFAULT 0,
            last_viewed DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_genre (user_id, genre),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            kinopoisk_id INT NOT NULL,
            movie_title VARCHAR(500),
            movie_year INT,
            movie_rating DECIMAL(3,1),
            movie_data JSON NOT NULL,
            added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_movie (user_id, kinopoisk_id),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_user_fav (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS movie_cache (
            kinopoisk_id INT PRIMARY KEY,
            movie_title VARCHAR(500),
            movie_original_title VARCHAR(500),
            movie_year INT,
            movie_rating DECIMAL(3,1),
            movie_data JSON NOT NULL,
            poster_url VARCHAR(1000),
            content_type VARCHAR(50) DEFAULT 'FILM',
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            access_count INT DEFAULT 0,
            INDEX idx_last_updated (last_updated)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS viewing_history (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            kinopoisk_id INT NOT NULL,
            viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            category VARCHAR(50),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            INDEX idx_user_history (user_id, viewed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->pdo->exec($sql);
            logMessage("✅ Tables created/verified");
        } catch (PDOException $e) {
            logMessage("❌ Error creating tables: " . $e->getMessage());
        }
    }
    
    public function getOrCreateUser($userId, $username, $firstName, $lastName = '') {
        try {
            // Проверяем существование пользователя
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                // Создаем нового пользователя
                $stmt = $this->pdo->prepare("
                    INSERT INTO users (user_id, username, first_name, last_name, first_seen, last_active) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$userId, $username, $firstName, $lastName]);
                
                // Создаем запись в статистике
                $stmt = $this->pdo->prepare("
                    INSERT INTO user_stats (user_id, stat_date, views_count) 
                    VALUES (?, CURDATE(), 0)
                ");
                $stmt->execute([$userId]);
                
                logMessage("✅ New user created: $userId ($firstName)");
                
                // Получаем созданного пользователя
                $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            } else {
                // Обновляем время последней активности
                $stmt = $this->pdo->prepare("UPDATE users SET last_active = NOW() WHERE user_id = ?");
                $stmt->execute([$userId]);
            }
            
            return $user;
        } catch (PDOException $e) {
            logMessage("❌ Error in getOrCreateUser: " . $e->getMessage());
            return ['user_id' => $userId, 'username' => $username, 'first_name' => $firstName];
        }
    }
    
    public function addToFavorites($userId, $movieData) {
        try {
            $kinopoiskId = $movieData['kinopoiskId'] ?? 0;
            $title = $movieData['nameRu'] ?? $movieData['nameEn'] ?? 'Н/Д';
            $year = $movieData['year'] ?? 0;
            $rating = $movieData['ratingKinopoisk'] ?? 0;
            $movieJson = json_encode($movieData, JSON_UNESCAPED_UNICODE);
            
            // Проверяем, есть ли уже
            $stmt = $this->pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND kinopoisk_id = ?");
            $stmt->execute([$userId, $kinopoiskId]);
            
            if ($stmt->fetch()) {
                return false;
            }
            
            // Добавляем в избранное
            $stmt = $this->pdo->prepare("
                INSERT INTO favorites (user_id, kinopoisk_id, movie_title, movie_year, movie_rating, movie_data) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $kinopoiskId, $title, $year, $rating, $movieJson]);
            
            // Обновляем счетчик
            $stmt = $this->pdo->prepare("UPDATE users SET favorites_count = favorites_count + 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            return true;
        } catch (PDOException $e) {
            logMessage("❌ Error adding to favorites: " . $e->getMessage());
            return false;
        }
    }
    
    public function getFavorites($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT movie_data FROM favorites 
                WHERE user_id = ? 
                ORDER BY added_at DESC 
                LIMIT 50
            ");
            $stmt->execute([$userId]);
            $results = $stmt->fetchAll();
            
            $favorites = [];
            foreach ($results as $row) {
                $favorites[] = json_decode($row['movie_data'], true);
            }
            
            return $favorites;
        } catch (PDOException $e) {
            logMessage("❌ Error getting favorites: " . $e->getMessage());
            return [];
        }
    }
    
    public function removeFromFavorites($userId, $kinopoiskId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND kinopoisk_id = ?");
            $stmt->execute([$userId, $kinopoiskId]);
            
            if ($stmt->rowCount() > 0) {
                $stmt = $this->pdo->prepare("UPDATE users SET favorites_count = favorites_count - 1 WHERE user_id = ?");
                $stmt->execute([$userId]);
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            logMessage("❌ Error removing from favorites: " . $e->getMessage());
            return false;
        }
    }
    
    public function logMovieView($userId, $movieId, $category = null, $genre = null) {
        try {
            // Добавляем в историю
            $stmt = $this->pdo->prepare("
                INSERT INTO viewing_history (user_id, kinopoisk_id, category) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $movieId, $category]);
            
            // Обновляем статистику пользователя
            $stmt = $this->pdo->prepare("
                UPDATE users SET total_views = total_views + 1, last_active = NOW() 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            // Обновляем дневную статистику
            $stmt = $this->pdo->prepare("
                INSERT INTO user_stats (user_id, stat_date, views_count) 
                VALUES (?, CURDATE(), 1)
                ON DUPLICATE KEY UPDATE views_count = views_count + 1
            ");
            $stmt->execute([$userId]);
            
            // Обновляем статистику по жанру
            if ($genre) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO genre_stats (user_id, genre, views_count, last_viewed) 
                    VALUES (?, ?, 1, NOW())
                    ON DUPLICATE KEY UPDATE 
                    views_count = views_count + 1, last_viewed = NOW()
                ");
                $stmt->execute([$userId, $genre]);
            }
            
            return true;
        } catch (PDOException $e) {
            logMessage("❌ Error logging view: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUserStats($userId) {
        try {
            // Информация о пользователе
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return [
                    'total' => 0,
                    'favorites' => 0,
                    'by_genre' => []
                ];
            }
            
            // Статистика по жанрам
            $stmt = $this->pdo->prepare("
                SELECT genre, views_count FROM genre_stats 
                WHERE user_id = ? 
                ORDER BY views_count DESC
            ");
            $stmt->execute([$userId]);
            $genres = $stmt->fetchAll();
            
            // Количество в избранном
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM favorites WHERE user_id = ?");
            $stmt->execute([$userId]);
            $favCount = $stmt->fetch()['count'];
            
            // Последние просмотры
            $stmt = $this->pdo->prepare("
                SELECT mc.movie_title, vh.viewed_at 
                FROM viewing_history vh
                JOIN movie_cache mc ON vh.kinopoisk_id = mc.kinopoisk_id
                WHERE vh.user_id = ? 
                ORDER BY vh.viewed_at DESC 
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $recent = $stmt->fetchAll();
            
            $byGenre = [];
            foreach ($genres as $g) {
                $byGenre[$g['genre']] = $g['views_count'];
            }
            
            return [
                'total' => $user['total_views'],
                'favorites' => $favCount,
                'by_genre' => $byGenre,
                'recent_views' => $recent
            ];
        } catch (PDOException $e) {
            logMessage("❌ Error getting stats: " . $e->getMessage());
            return [
                'total' => 0,
                'favorites' => 0,
                'by_genre' => []
            ];
        }
    }
    
    public function cacheMovie($movieData) {
        try {
            $kinopoiskId = $movieData['kinopoiskId'] ?? 0;
            $title = $movieData['nameRu'] ?? $movieData['nameEn'] ?? 'Н/Д';
            $originalTitle = $movieData['nameOriginal'] ?? '';
            $year = $movieData['year'] ?? 0;
            $rating = $movieData['ratingKinopoisk'] ?? 0;
            $posterUrl = $movieData['posterUrl'] ?? '';
            $contentType = $movieData['type'] ?? 'FILM';
            $movieJson = json_encode($movieData, JSON_UNESCAPED_UNICODE);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO movie_cache 
                (kinopoisk_id, movie_title, movie_original_title, movie_year, movie_rating, movie_data, poster_url, content_type, access_count) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE 
                movie_title = VALUES(movie_title),
                movie_original_title = VALUES(movie_original_title),
                movie_year = VALUES(movie_year),
                movie_rating = VALUES(movie_rating),
                movie_data = VALUES(movie_data),
                poster_url = VALUES(poster_url),
                content_type = VALUES(content_type),
                access_count = access_count + 1
            ");
            
            $stmt->execute([$kinopoiskId, $title, $originalTitle, $year, $rating, $movieJson, $posterUrl, $contentType]);
            return true;
        } catch (PDOException $e) {
            logMessage("❌ Error caching movie: " . $e->getMessage());
            return false;
        }
    }
    
    public function getCachedMovie($kinopoiskId) {
        try {
            $stmt = $this->pdo->prepare("SELECT movie_data FROM movie_cache WHERE kinopoisk_id = ?");
            $stmt->execute([$kinopoiskId]);
            $result = $stmt->fetch();
            
            if ($result) {
                return json_decode($result['movie_data'], true);
            }
            
            return null;
        } catch (PDOException $e) {
            logMessage("❌ Error getting cached movie: " . $e->getMessage());
            return null;
        }
    }
}