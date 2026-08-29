<?php

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/util.php";

foreach (glob(__DIR__ . "/Plugins/*.php") as $plugin) {
    include_once $plugin;
}

$rawUpdate = file_get_contents("php://input");

if (empty($rawUpdate)) {
    http_response_code(200);
    exit("BOTAR OK");
}

$updateData = json_decode($rawUpdate, true);

if (!is_array($updateData)) {
    http_response_code(200);
    exit("OK");
}

$messageData =
    $updateData["message"]
    ?? $updateData["edited_message"]
    ?? ($updateData["callback_query"]["message"] ?? null);

if (!$messageData) {
    http_response_code(200);
    exit("OK");
}

$chatId = $messageData["chat"]["id"] ?? null;
$messageId = $messageData["message_id"] ?? null;
$messageText = $messageData["text"] ?? "";

$fromData =
    $updateData["callback_query"]["from"]
    ?? ($messageData["from"] ?? []);

$from_id = $fromData["id"] ?? null;
$from_name = trim(
    ($fromData["first_name"] ?? "") . " " .
    ($fromData["last_name"] ?? "")
);
$from_username = $fromData["username"] ?? "";

$data = $updateData["callback_query"]["data"] ?? null;

/*
|--------------------------------------------------------------------------
| أوامر الاختبار
|--------------------------------------------------------------------------
*/

if ($messageText !== "") {

    $command = trim($messageText);

    // يدعم / و ! و #
    $command = preg_replace('/^[\/!#]/', '', $command);

    // إزالة اسم البوت من /start@BOTNAME
    $command = explode(" ", $command)[0];
    $command = explode("@", $command)[0];
    $command = strtolower($command);

    switch ($command) {

        case "start":
            if (function_exists("command_start")) {
                command_start();
            }
            break;

        case "ping":
            if (function_exists("command_ping")) {
                command_ping();
            }
            break;
    }
}

http_response_code(200);
echo "OK";
