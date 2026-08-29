<?php

function botar_admin_is_developer(){
    global $sudoID;
    return botar_actor_id() === (string)$sudoID;
}

function botar_admin_is_private(){
    $chat = botar_chat();
    return ($chat['type'] ?? '') === 'private';
}

function botar_admin_keyboard(){
    return [
        'inline_keyboard' => [
            [
                ['text' => '📊 الإحصائيات', 'callback_data' => 'botar_admin_stats']
            ],
            [
                ['text' => '⭐ مجموعات VIP', 'callback_data' => 'botar_admin_vip'],
                ['text' => '📣 المجموعات العادية', 'callback_data' => 'botar_admin_free']
            ],
            [
                ['text' => '💳 إدارة الشحن', 'callback_data' => 'botar_admin_charge'],
                ['text' => '📨 رسالة جماعية', 'callback_data' => 'botar_admin_broadcast']
            ],
            [
                ['text' => '👥 مساعدو المطور', 'callback_data' => 'botar_admin_helpers'],
                ['text' => '🚫 المحظورون عام', 'callback_data' => 'botar_admin_bans']
            ],
            [
                ['text' => '⚙️ إعدادات البوت', 'callback_data' => 'botar_admin_settings']
            ]
        ]
    ];
}

function botar_admin_back_keyboard(){
    return [
        'inline_keyboard' => [
            [
                ['text' => '⬅️ رجوع', 'callback_data' => 'botar_admin_home']
            ]
        ]
    ];
}

function botar_admin_answer_callback($text = ''){
    global $updateData;

    $callbackId = $updateData['callback_query']['id'] ?? null;

    if (!$callbackId) {
        return;
    }

    $data = [
        'callback_query_id' => $callbackId
    ];

    if ($text !== '') {
        $data['text'] = $text;
    }

    sendCommand(
        'answerCallbackQuery',
        $data
    );
}

function botar_admin_render($text, $keyboard = null){
    global $updateData;

    $isCallback = isset(
        $updateData['callback_query']
    );

    if ($isCallback) {

        editMessage(
            $text,
            false,
            false,
            $keyboard ?: botar_admin_back_keyboard()
        );

        botar_admin_answer_callback();

        return;
    }

    sendMessage(
        $text,
        false,
        $keyboard ?: botar_admin_keyboard()
    );
}

function botar_admin_home(){

    botar_admin_render(
        "🛡 لوحة مطور BOTAR\n\n".
        "اختر القسم الذي تريد إدارته:",
        botar_admin_keyboard()
    );
}

function botar_admin_groups(){

    $groups = botar_json(
        'groups.json'
    );

    return is_array($groups)
        ? $groups
        : [];
}

function botar_admin_stats_text(){

    $groups = botar_admin_groups();

    $total = 0;
    $vip = 0;
    $free = 0;

    foreach ($groups as $group) {

        if (
            !is_array($group)
            ||
            empty($group['enabled'])
        ) {
            continue;
        }

        $total++;

        if (
            ($group['plan'] ?? 'free')
            === 'vip'
        ) {

            $vip++;

        } else {

            $free++;
        }
    }

    $helpers = botar_json(
        'helpers.json'
    );

    $bans = botar_json(
        'global_bans.json'
    );

    $helpersCount =
        is_array($helpers)
        ? count($helpers)
        : 0;

    $bansCount =
        is_array($bans)
        ? count($bans)
        : 0;

    return
        "📊 إحصائيات BOTAR\n\n".
        "🗂 المجموعات: {$total}\n".
        "⭐ VIP: {$vip}\n".
        "📣 العادية: {$free}\n".
        "👥 مساعدو المطور: {$helpersCount}\n".
        "🚫 المحظورون عام: {$bansCount}";
}

function botar_admin_group_list_text($plan){

    $groups =
        botar_admin_groups();

    $wanted = [];

    foreach (
        $groups as $id => $group
    ) {

        if (
            !is_array($group)
            ||
            empty($group['enabled'])
        ) {
            continue;
        }

        $groupPlan =
            $group['plan']
            ?? 'free';

        if ($groupPlan !== $plan) {
            continue;
        }

        $wanted[] = [

            'id' =>
                $group['chat_id']
                ?? $id,

            'title' =>
                trim(
                    (string)(
                        $group['title']
                        ?? 'بدون اسم'
                    )
                )
                ?: 'بدون اسم'
        ];
    }

    $label =
        $plan === 'vip'
        ? '⭐ مجموعات VIP'
        : '📣 المجموعات العادية';

    if (!$wanted) {

        return
            $label.
            "\n\n".
            "لا توجد مجموعات في هذا القسم حالياً.";
    }

    $text =
        $label.
        "\n\n";

    $shown =
        array_slice(
            $wanted,
            0,
            25
        );

    foreach (
        $shown as $index => $group
    ) {

        $n = $index + 1;

        $text .=
            "{$n}) {$group['title']}\n".
            "ID: {$group['id']}\n\n";
    }

    $remaining =
        count($wanted)
        -
        count($shown);

    if ($remaining > 0) {

        $text .=
            "… ويوجد {$remaining} مجموعة إضافية.";
    }

    return trim($text);
}

function botar_admin_helpers_text(){

    $helpers =
        botar_json(
            'helpers.json'
        );

    if (
        !is_array($helpers)
        ||
        !$helpers
    ) {

        return
            "👥 مساعدو المطور\n\n".
            "لا يوجد مساعدون حالياً.";
    }

    $text =
        "👥 مساعدو المطور\n\n";

    $i = 1;

    foreach (
        $helpers as $helper
    ) {

        $text .=
            $i.
            ') '.
            (string)$helper.
            "\n";

        $i++;
    }

    return trim($text);
}

function botar_admin_bans_text(){

    $bans =
        botar_json(
            'global_bans.json'
        );

    if (
        !is_array($bans)
        ||
        !$bans
    ) {

        return
            "🚫 المحظورون عام\n\n".
            "لا يوجد محظورون حالياً.";
    }

    $text =
        "🚫 المحظورون عام\n\n";

    $i = 1;

    foreach (
        $bans as $key => $value
    ) {

        $id =
            is_array($value)
            ? ($value['id'] ?? $key)
            : (
                is_numeric($key)
                ? $value
                : $key
            );

        $text .=
            $i.
            ') '.
            (string)$id.
            "\n";

        $i++;
    }

    return trim($text);
}

function handle_admin_command(
    $messageText
){

    $command =
        botar_normalize_command(
            $messageText
        );

    if ($command !== 'admin') {
        return false;
    }

    if (
        !botar_admin_is_private()
    ) {

        sendMessage(
            'لوحة المطور تعمل في الخاص فقط.'
        );

        return true;
    }

    if (
        !botar_admin_is_developer()
    ) {

        sendMessage(
            'ليس لديك الصلاحية لفتح لوحة المطور.'
        );

        return true;
    }

    botar_admin_home();

    return true;
}

function handle_admin_callback(
    $data
){

    if (
        !is_string($data)
        ||
        strpos(
            $data,
            'botar_admin_'
        ) !== 0
    ) {

        return false;
    }

    if (
        !botar_admin_is_private()
        ||
        !botar_admin_is_developer()
    ) {

        botar_admin_answer_callback(
            'ليس لديك الصلاحية'
        );

        return true;
    }

    switch ($data) {

        case 'botar_admin_home':

            botar_admin_home();

            break;

        case 'botar_admin_stats':

            botar_admin_render(
                botar_admin_stats_text()
            );

            break;

        case 'botar_admin_vip':

            botar_admin_render(
                botar_admin_group_list_text(
                    'vip'
                )
            );

            break;

        case 'botar_admin_free':

            botar_admin_render(
                botar_admin_group_list_text(
                    'free'
                )
            );

            break;

        case 'botar_admin_charge':

            botar_admin_render(
                "💳 إدارة الشحن\n\n".
                "من داخل المجموعة استخدم:\n".
                "• شحن\n".
                "• فحص\n".
                "• ترقية\n".
                "• عادية\n".
                "• شحن مدفوع\n".
                "• فحص مدفوعة"
            );

            break;

        case 'botar_admin_broadcast':

            botar_admin_render(
                "📨 الرسالة الجماعية\n\n".
                "سيتم ربط استقبال الرسالة وإرسالها للمجموعات في المرحلة الخاصة بالنشر العام."
            );

            break;

        case 'botar_admin_helpers':

            botar_admin_render(
                botar_admin_helpers_text()
            );

            break;

        case 'botar_admin_bans':

            botar_admin_render(
                botar_admin_bans_text()
            );

            break;

        case 'botar_admin_settings':

            botar_admin_render(
                "⚙️ إعدادات BOTAR\n\n".
                "💾 التخزين: JSON\n".
                "⌨️ البادئات: /  !  #\n".
                "✅ نظام التفعيل والشحن: مفعّل\n".
                "🔐 لوحة المطور: خاصة بالمطور الرئيسي"
            );

            break;

        default:

            botar_admin_answer_callback();

            break;
    }

    return true;
}
