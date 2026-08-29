<?php

function command_ping(){
    sendMessage("البوت يعمل ✅");
}

function command_start(){
    sendMessage("أهلاً بك في BOTAR");
}

function handle_command($messageText){

    if (!is_string($messageText) || trim($messageText) === "") {
        return;
    }

    $command = trim($messageText);

    // يدعم / و ! و #
    $command = preg_replace('/^[\/!#]/', '', $command);

    // يأخذ اسم الأمر فقط
    $command = explode(' ', $command)[0];

    // يدعم /start@DUBLM_BOT
    $command = explode('@', $command)[0];

    $command = strtolower($command);

    if ($command === 'start') {

        command_start();

    } elseif ($command === 'ping') {

        command_ping();
    }
}
