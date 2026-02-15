<?php
// config.php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Загружаем переменные окружения
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

define('BOT_TOKEN', $_ENV['BOT_TOKEN']);
define('KINOPOISK_API_KEY', $_ENV['KINOPOISK_API_KEY']);
define('BOT_USERNAME', $_ENV['BOT_USERNAME']);

define('DB_HOST', $_ENV['DB_HOST']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);
define('DB_NAME', $_ENV['DB_NAME']);

define('CACHE_DIR', __DIR__ . '/cache/');
define('LOG_FILE', __DIR__ . '/bot.log');

if (!is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}

// Функция для логирования
function logMessage($message) {
    $date = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$date] $message\n", FILE_APPEND);
}