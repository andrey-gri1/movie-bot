<?php
// webhook.php

require_once __DIR__ . '/bot.php';

session_start();

$content = file_get_contents('php://input');

if ($content) {
    try {
        $bot = new MovieBot();
        $bot->handleWebhook($content);
        http_response_code(200);
    } catch (Exception $e) {
        logMessage("❌ Webhook error: " . $e->getMessage());
        http_response_code(500);
    }
} else {
    http_response_code(400);
}