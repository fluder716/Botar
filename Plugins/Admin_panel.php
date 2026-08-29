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
                [
                    'text' => '📊 الإحصائيات',
                    'callback_data' => 'botar_admin_stats'
                ]
            ],
            [
                [
                    'text' => '⭐ مجموعات VIP',
                    'callback_data' => 'botar_admin_vip'
                ],
                [
                    'text' => '📣 المجموعات العادية',
                    'callback_data' => 'botar_admin_free'
                ]
            ],
            [
                [
                    'text' => '💳 إدارة الشحن',
                    'callback_data' => 'botar_admin_charge'
                ],
                [
                    'text' => '📨 رسالة جماعية',
                    'callback_data' => 'botar_admin_broadcast'
                ]
            ],
            [
                [
                    'text' => '👥 مساعدو المطور',
                    'callback_data' => 'botar_admin_helpers'
                ],
                [
                    'text' => '🚫 المحظورون عام',
                    'callback_data' => 'botar_admin_bans'
                ]
            ],
            [
                [
                    'text' => '⚙️ إعدادات البوت',
                    'callback_data' => 'botar_admin_settings'
                ]
            ]
        ]
    ];
}

function botar_admin_back_keyboard(){
    return [
        'inline_keyboard' => [
            [
                [
                    'text' => '⬅️ رجوع',
                    'callback_data' => 'botar_admin_home'
                ]
            ]
        ]
    ];
}

function botar_admin_broadcast_target_keyboard(){
    return [
        'inline_keyboard' => [
            [
                [
                    'text' => '⭐ VIP',
                    'callback_data' => 'botar_bc_target_vip'
                ],
                [
                    'text' => '📣 العادية',
                    'callback_data' => 'botar_bc_target_free'
                ]
            ],
            [
                [
                    'text' => '👥 الخاص',
                    'callback_data' => 'botar_bc_target_private'
                ],
                [
                    'text' => '🌐 الجميع',
                    'callback_data' => 'botar_bc_target_all'
                ]
            ],
            [
                [
                    'text' => '⬅️ رجوع',
                    'callback_data' => 'botar_admin_home'
                ]
            ]
        ]
    ];
}

function botar_admin_broadcast_mode_keyboard(){
    return [
        'inline_keyboard' => [
            [
                [
                    'text' => '📤 إرسال',
                    'callback_data' => 'botar_bc_mode_copy'
                ],
                [
                    'text' => '🔁 توجيه',
                    'callback_data' => 'botar_bc_mode_forward'
                ]
            ],
            [
                [
                    'text' => '❌ إلغاء',
                    'callback_data' => 'botar_bc_cancel'
                ]
            ]
        ]
    ];
}

function botar_admin_broadcast_cancel_keyboard(){
    return [
        'inline_keyboard' => [
            [
                [
                    'text' => '❌ إلغاء النشر',
                    'callback_data' => 'botar_bc_cancel'
                ]
            ]
        ]
    ];
}

function botar_admin_answer_callback($text = ''){
    global $updateData;

    $callbackId =
        $updateData['callback_query']['id']
        ?? null;

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

function botar_admin_render(
    $text,
    $keyboard = null
){
    global $updateData;

    $isCallback =
        isset(
            $updateData['callback_query']
        );

    if ($isCallback) {

        editMessage(
            $text,
            false,
            false,
            $keyboard
                ?: botar_admin_back_keyboard()
        );

        botar_admin_answer_callback();

        return;
    }

    sendMessage(
        $text,
        false,
        $keyboard
            ?: botar_admin_keyboard()
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

    $groups =
        botar_json(
            'groups.json'
        );

    return is_array($groups)
        ? $groups
        : [];
}

/*
|--------------------------------------------------------------------------
| حفظ مستخدمي الخاص
|--------------------------------------------------------------------------
*/

function botar_admin_track_private_user(){
    global $updateData, $messageData;

    if (!botar_admin_is_private()) {
        return;
    }

    $from =
        $updateData['callback_query']['from']
        ??
        ($messageData['from'] ?? null)
        ??
        ($updateData['message']['from'] ?? null);

    if (
        !is_array($from)
        ||
        !isset($from['id'])
    ) {
        return;
    }

    $id =
        (string)$from['id'];

    $users =
        botar_json(
            'users.json'
        );

    if (!is_array($users)) {
        $users = [];
    }

    $users[$id] = [

        'id' =>
            (int)$from['id'],

        'first_name' =>
            (string)(
                $from['first_name']
                ?? ''
            ),

        'last_name' =>
            (string)(
                $from['last_name']
                ?? ''
            ),

        'username' =>
            (string)(
                $from['username']
                ?? ''
            ),

        'updated_at' =>
            time()
    ];

    botar_json(
        'users.json',
        $users
    );
}

/*
|--------------------------------------------------------------------------
| الإحصائيات
|--------------------------------------------------------------------------
*/

function botar_admin_stats_text(){

    $groups =
        botar_admin_groups();

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

    $helpers =
        botar_json(
            'helpers.json'
        );

    $bans =
        botar_json(
            'global_bans.json'
        );

    $users =
        botar_json(
            'users.json'
        );

    $helpersCount =
        is_array($helpers)
        ? count($helpers)
        : 0;

    $bansCount =
        is_array($bans)
        ? count($bans)
        : 0;

    $usersCount =
        is_array($users)
        ? count($users)
        : 0;

    return
        "📊 إحصائيات BOTAR\n\n".
        "🗂 المجموعات: {$total}\n".
        "⭐ VIP: {$vip}\n".
        "📣 العادية: {$free}\n".
        "👤 مستخدمو الخاص: {$usersCount}\n".
        "👥 مساعدو المطور: {$helpersCount}\n".
        "🚫 المحظورون عام: {$bansCount}";
}

/*
|--------------------------------------------------------------------------
| قوائم المجموعات
|--------------------------------------------------------------------------
*/

function botar_admin_group_list_text(
    $plan
){

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

        $n =
            $index + 1;

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

    foreach ($helpers as $helper) {

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

/*
|--------------------------------------------------------------------------
| حالة النشر
|--------------------------------------------------------------------------
*/

function botar_admin_broadcast_state(
    $write = null
){

    $all =
        botar_json(
            'broadcast_state.json'
        );

    if (!is_array($all)) {
        $all = [];
    }

    $actor =
        botar_actor_id();

    if ($write === null) {

        return
            $all[$actor]
            ?? [];
    }

    if ($write === false) {

        unset(
            $all[$actor]
        );

    } else {

        $all[$actor] =
            $write;
    }

    botar_json(
        'broadcast_state.json',
        $all
    );

    return true;
}

/*
|--------------------------------------------------------------------------
| تحديد المستلمين
|--------------------------------------------------------------------------
*/

function botar_admin_broadcast_recipients(
    $target
){

    $recipients = [];

    if (
        in_array(
            $target,
            [
                'vip',
                'free',
                'all'
            ],
            true
        )
    ) {

        foreach (
            botar_admin_groups()
            as $id => $group
        ) {

            if (
                !is_array($group)
                ||
                empty($group['enabled'])
            ) {
                continue;
            }

            $plan =
                $group['plan']
                ?? 'free';

            if (
                $target !== 'all'
                &&
                $plan !== $target
            ) {
                continue;
            }

            $recipients[] =
                (string)(
                    $group['chat_id']
                    ?? $id
                );
        }
    }

    if (
        in_array(
            $target,
            [
                'private',
                'all'
            ],
            true
        )
    ) {

        $users =
            botar_json(
                'users.json'
            );

        if (is_array($users)) {

            foreach (
                $users
                as $key => $user
            ) {

                if (
                    is_array($user)
                ) {

                    $id =
                        $user['id']
                        ?? $key;

                } else {

                    $id =
                        is_numeric($key)
                        ? $key
                        : $user;
                }

                if (
                    $id !== null
                    &&
                    $id !== ''
                ) {

                    $recipients[] =
                        (string)$id;
                }
            }
        }
    }

    $recipients =
        array_values(
            array_unique(
                $recipients
            )
        );

    return $recipients;
}

function botar_admin_api_ok(
    $result
){

    if (is_array($result)) {

        return
            !empty(
                $result['ok']
            );
    }

    if (
        !is_string($result)
        ||
        $result === ''
    ) {

        return false;
    }

    $decoded =
        json_decode(
            $result,
            true
        );

    return
        is_array($decoded)
        &&
        !empty(
            $decoded['ok']
        );
}

/*
|--------------------------------------------------------------------------
| تنفيذ النشر بعد إرسال المطور للرسالة
|--------------------------------------------------------------------------
*/

function botar_admin_handle_pending_broadcast(
    $messageText = null
){
    global
        $messageData,
        $chatId,
        $messageId;

    if (
        !botar_admin_is_private()
        ||
        !botar_admin_is_developer()
    ) {

        return false;
    }

    $state =
        botar_admin_broadcast_state();

    if (
        !is_array($state)
        ||
        empty($state['target'])
        ||
        empty($state['mode'])
    ) {

        return false;
    }

    $command =
        botar_normalize_command(
            $messageText
        );

    if ($command === 'admin') {

        return false;
    }

    $sourceChat =
        $messageData['chat']['id']
        ??
        $chatId
        ??
        null;

    $sourceMessage =
        $messageData['message_id']
        ??
        $messageId
        ??
        null;

    if (
        !$sourceChat
        ||
        !$sourceMessage
    ) {

        return false;
    }

    $recipients =
        botar_admin_broadcast_recipients(
            $state['target']
        );

    $success = 0;
    $failed = 0;

    foreach (
        $recipients as $recipient
    ) {

        /*
        | لا نرسل الرسالة للمطور نفسه
        */

        if (
            (string)$recipient
            ===
            (string)$sourceChat
        ) {

            continue;
        }

        /*
        | توجيه
        */

        if (
            $state['mode']
            === 'forward'
        ) {

            $result =
                sendCommand(
                    'forwardMessage',
                    [
                        'chat_id' =>
                            $recipient,

                        'from_chat_id' =>
                            $sourceChat,

                        'message_id' =>
                            $sourceMessage
                    ]
                );

        /*
        | إرسال نسخة
        */

        } else {

            $result =
                sendCommand(
                    'copyMessage',
                    [
                        'chat_id' =>
                            $recipient,

                        'from_chat_id' =>
                            $sourceChat,

                        'message_id' =>
                            $sourceMessage
                    ]
                );
        }

        if (
            botar_admin_api_ok(
                $result
            )
        ) {

            $success++;

        } else {

            $failed++;
        }

        /*
        | تخفيف سرعة الطلبات
        */

        usleep(
            50000
        );
    }

    /*
    | إنهاء جلسة النشر
    */

    botar_admin_broadcast_state(
        false
    );

    sendMessage(
        "✅ انتهى النشر\n\n".
        "تم الإرسال: {$success}\n".
        "فشل: {$failed}",
        false
    );

    return true;
}

function botar_admin_broadcast_target_label(
    $target
){

    $labels = [

        'vip'
