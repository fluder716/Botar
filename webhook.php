<?php

$token = getenv("BOT_TOKEN") ?: "";

if ($token === "") {
    exit("BOT_TOKEN missing");
}

$webhookUrl = "https://botar-84ql.onrender.com/index.php";

$response = file_get_contents(
    "https://api.telegram.org/bot" .
    $token .
    "/setWebhook?url=" .
    urlencode($webhookUrl)
);

header("Content-Type: application/json");
echo $response;
