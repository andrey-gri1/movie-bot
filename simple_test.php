<?php
// test_simple.php
require_once 'vendor/autoload.php';
require_once 'config.php';

use TelegramBot\Api\BotApi;

$bot = new BotApi(BOT_TOKEN);
$updates = $bot->getUpdates(0, 1);

echo "Последние обновления:\n";
print_r($updates);