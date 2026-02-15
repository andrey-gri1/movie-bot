<?php
// index.php

echo "🎬 Movie Master Bot is running!\n";
echo "Webhook URL: " . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}/webhook.php\n";