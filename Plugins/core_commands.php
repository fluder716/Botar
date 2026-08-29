<?php

function botar_path($file){
    $dir = getenv('BOTAR_DATA_DIR') ?: dirname(__DIR__) . '/Data';

    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir . '/' . $file;
}

function botar_json($file, $write = null){
    $path = botar_path($file);

    if ($write !== null) {
        return file_put_contents(
            $path,
            json_encode(
                $write,
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ),
            LOCK_EX
        ) !== false;
    }

    if (!is_file($path)) {
        return [];
    }

    $data = json_decode(
        (string) file_get_contents($path),
        true
    );

    return is_array($data) ? $data : [];
}

function botar_get_group($chatId){
    $all = botar_json('groups.json');

    return $all[(string)$chatId] ?? null;
}

function botar_save_group($chatId, $group){
    $all = botar_json('groups.json');

    $all[(string)$chatId] = $group;

    return botar_json('groups.json', $all);
}

function botar_delete_group($chatId){
    $all = botar_json('groups.json');

    unset($all[(string)$chatId]);

    return botar_json('groups.json', $all);
}

function botar_actor_id(){
    global $updateData, $from_id;

    return (string)(
        $updateData['callback_query']['from']['id']
        ?? $from_id
        ?? ''
    );
}

function botar_is_operator(){
    global $sudoID;

    $actor = botar_actor_id();

    if ($actor === (string)$sudoID) {
        return true;
    }

    foreach (botar_json('helpers.json') as $id) {

        if ((string)$id === $actor) {
            return true;
        }
    }

    return false;
}

function botar_chat(){
    global $updateData, $messageData, $chatId;

    $chat =
        $updateData['callback_query']['message']['chat']
        ?? ($messageData['chat'] ?? []);

    if (!isset($chat['id']) && isset($chatId)) {
        $chat['id'] = $chatId;
    }

    return $chat;
}

function botar_is_group(){
    return in_array(
        botar_chat()['type'] ?? '',
        ['group', 'supergroup'],
        true
    );
}

function botar_normalize_command($text){

    if (!is_string($text) || trim($text) === '') {
        return '';
    }

    $text = preg_replace(
        '/^[\/!#]+/u',
        '',
        trim($text)
    );

    $text = preg_replace(
        '/^([^\s@]+)@[^\s]+/u',
        '$1',
        $text
    );

    $text = preg_replace(
        '/\s+/u',
        ' ',
        trim($text)
    );

    return function_exists('mb_strtolower')
        ? mb_strtolower($text, 'UTF-8')
        : strtolower($text);
}

function botar_allowed(){

    if (!botar_is_group()) {

        sendMessage(
            'هذا الأمر يعمل داخل المجموعات فقط.'
        );

        return false;
    }

    if (!botar_is_operator()) {

        sendMessage(
            'ليس لديك الصلاحية لتنفيذ هذا الأمر.'
        );

        return false;
    }

    return true;
}

function botar_notify($text){
    global $sudoID;

    if ($sudoID !== '') {

        sendMessage(
            $text,
            false,
            false,
            $sudoID
        );
    }
}

function botar_charge_keyboard($plan){

    return [

        'inline_keyboard' => [

            [
                [
                    'text' => 'أسبوع',
                    'callback_data' =>
                        'botar_charge_'.$plan.'_7'
                ],

                [
                    'text' => 'شهر',
                    'callback_data' =>
                        'botar_charge_'.$plan.'_30'
                ],
            ],

            [
                [
                    'text' => '6 شهور',
                    'callback_data' =>
                        'botar_charge_'.$plan.'_180'
                ],

                [
                    'text' => 'سنة',
                    'callback_data' =>
                        'botar_charge_'.$plan.'_365'
                ],
            ],
        ],
    ];
}

function botar_check_text($group, $plan){

    $field =
        $plan === 'vip'
        ? 'vip_until'
        : 'free_until';

    $label =
        $plan === 'vip'
        ? 'الاشتراك المدفوع'
        : 'التفعيل';

    $until = (int)(
        $group[$field] ?? 0
    );

    if ($until === 0) {

        return
            "✅ {$label} موجود، ولم يتم تحديد مدة شحن بعد.";
    }

    $days = (int) ceil(
        ($until - time()) / 86400
    );

    return $days > 0
        ? "✅ متبقي على {$label}: {$days} يوم."
        : "⛔ {$label} منتهي.";
}

function handle_activation_command($text){

    $cmd = botar_normalize_command($text);

    $commands = [

        'تفعيل',
        'add',

        'شحن',
        'charge',

        'فحص',
        'check',

        'ترقية',
        'vip',

        'عادية',
        'nvip',

        'شحن مدفوع',
        'chargevip',

        'فحص مدفوعة',
        'checkvip'
    ];

    if (!in_array(
        $cmd,
        $commands,
        true
    )) {
        return false;
    }

    if (!botar_allowed()) {
        return true;
    }

    $chat = botar_chat();

    $chatId = $chat['id'];

    $title = $chat['title'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | تفعيل
    |--------------------------------------------------------------------------
    */

    if (in_array(
        $cmd,
        ['تفعيل', 'add'],
        true
    )) {

        $group =
            botar_get_group($chatId)
            ?: [

                'chat_id' => $chatId,

                'title' => $title,

                'enabled' => true,

                'plan' => 'free',

                'free_until' => null,

                'vip_until' => null,

                'activated_by' =>
                    botar_actor_id(),

                'activated_at' =>
                    time(),
            ];

        $group['enabled'] = true;

        $group['title'] = $title;

        botar_save_group(
            $chatId,
            $group
        );

        sendMessage(
            '✅ تم تفعيل BOTAR في المجموعة.'
        );

        botar_notify(
            "✅ تم تفعيل مجموعة\n".
            "الاسم: {$title}\n".
            "الايدي: {$chatId}\n".
            "بواسطة: ".botar_actor_id()
        );

        return true;
    }

    $group =
        botar_get_group($chatId);

    if (
        !$group
        || empty($group['enabled'])
    ) {

        sendMessage(
            'يجب تفعيل البوت في المجموعة أولاً.'
        );

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | شحن عادي
    |--------------------------------------------------------------------------
    */

    if (in_array(
        $cmd,
        ['شحن', 'charge'],
        true
    )) {

        sendMessage(
            'اختر مدة شحن المجموعة:',
            true,
            botar_charge_keyboard('free')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | فحص
    |--------------------------------------------------------------------------
    */

    elseif (in_array(
        $cmd,
        ['فحص', 'check'],
        true
    )) {

        sendMessage(
            botar_check_text(
                $group,
                'free'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ترقية VIP
    |--------------------------------------------------------------------------
    */

    elseif (in_array(
        $cmd,
        ['ترقية', 'vip'],
        true
    )) {

        $group['plan'] = 'vip';

        botar_save_group(
            $chatId,
            $group
        );

        sendMessage(
            '⭐ تم ترقية المجموعة إلى VIP.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | عادية
    |--------------------------------------------------------------------------
    */

    elseif (in_array(
        $cmd,
        ['عادية', 'nvip'],
        true
    )) {

        $group['plan'] = 'free';

        botar_save_group(
            $chatId,
            $group
        );

        sendMessage(
            '✅ تم إرجاع المجموعة إلى عادية.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | شحن مدفوع
    |--------------------------------------------------------------------------
    */

    elseif (in_array(
        $cmd,
        ['شحن مدفوع', 'chargevip'],
        true
    )) {

        if (
            ($group['plan'] ?? 'free')
            !== 'vip'
        ) {

            sendMessage(
                'المجموعة ليست VIP. استخدم أمر ترقية أولاً.'
            );

        } else {

            sendMessage(
                'اختر مدة شحن المجموعة المدفوعة:',
                true,
                botar_charge_keyboard('vip')
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | فحص مدفوعة
    |--------------------------------------------------------------------------
    */

    elseif (in_array(
        $cmd,
        ['فحص مدفوعة', 'checkvip'],
        true
    )) {

        if (
            ($group['plan'] ?? 'free')
            !== 'vip'
        ) {

            sendMessage(
                'المجموعة ليست VIP.'
            );

        } else {

            sendMessage(
                botar_check_text(
                    $group,
                    'vip'
                )
            );
        }
    }

    return true;
}

function handle_activation_callback($data){

    if (
        !is_string($data)
        || !preg_match(
            '/^botar_charge_(free|vip)_(7|30|180|365)$/',
            $data,
            $m
        )
    ) {
        return false;
    }

    global $updateData;

    $callbackId =
        $updateData['callback_query']['id']
        ?? null;

    if (
        !botar_is_group()
        || !botar_is_operator()
    ) {

        if ($callbackId) {

            sendCommand(
                'answerCallbackQuery',
                [
                    'callback_query_id' =>
                        $callbackId,

                    'text' =>
                        'ليس لديك الصلاحية',

                    'show_alert' =>
                        true
                ]
            );
        }

        return true;
    }

    $chat = botar_chat();

    $chatId = $chat['id'];

    $group =
        botar_get_group($chatId);

    if (
        !$group
        || empty($group['enabled'])
    ) {

        return true;
    }

    $plan = $m[1];

    $days = (int)$m[2];

    if (
        $plan === 'vip'
        &&
        ($group['plan'] ?? 'free')
        !== 'vip'
    ) {

        return true;
    }

    $field =
        $plan === 'vip'
        ? 'vip_until'
        : 'free_until';

    $group[$field] =
        max(
            time(),
            (int)(
                $group[$field]
                ?? 0
            )
        )
        +
        ($days * 86400);

    botar_save_group(
        $chatId,
        $group
    );

    if ($callbackId) {

        sendCommand(
            'answerCallbackQuery',
            [
                'callback_query_id' =>
                    $callbackId,

                'text' =>
                    'تم الشحن ✅'
            ]
        );
    }

    $type =
        $plan === 'vip'
        ? 'المدفوع'
        : 'العادي';

    sendMessage(
        "✅ تم شحن الاشتراك {$type} لمدة {$days} يوم.",
        false
    );

    botar_notify(
        "💳 شحن مجموعة\n".
        "الاسم: ".
        ($chat['title'] ?? '').
        "\nالايدي: {$chatId}\n".
        "النوع: {$type}\n".
        "المدة: {$days} يوم\n".
        "بواسطة: ".
        botar_actor_id()
    );

    return true;
}

/*
|--------------------------------------------------------------------------
| الأوامر الأساسية القديمة
|--------------------------------------------------------------------------
*/

function command_ping(){

    sendMessage(
        'البوت يعمل ✅'
    );
}

function command_start(){

    sendMessage(
        'أهلاً بك في BOTAR'
    );
}

function handle_command($messageText){
    global $data;

    /*
    |--------------------------------------------------------------------------
    | أزرار الشحن
    |--------------------------------------------------------------------------
    */

    if (
        is_string($data ?? null)
        &&
        $data !== ''
        &&
        handle_activation_callback($data)
    ) {

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | أوامر التفعيل والشحن
    |--------------------------------------------------------------------------
    */

    if (
        handle_activation_command(
            $messageText
        )
    ) {

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | start / ping
    |--------------------------------------------------------------------------
    */

    $command =
        botar_normalize_command(
            $messageText
        );

    if ($command === 'start') {

        command_start();

    } elseif ($command === 'ping') {

        command_ping();
    }
}
