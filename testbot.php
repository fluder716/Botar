<?php

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/util.php";

$result = sendCommand("sendMessage", [
    "chat_id" => $sudoID,
    "text" => "✅ BOTAR TEST"
]);

header("Content-Type: text/plain; charset=utf-8");
echo $result;
