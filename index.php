<?php
ob_start();
error_reporting(0);
date_default_timezone_set("Asia/Tashkent");

// 1. BU YERGA MA'LUMOTLARINGIZNI QO'YING
define('API_KEY', '8769995781:AAFHA4j1uwL_t1XpihmWxEfz_l1Ltqnb5R8'); // Bot tokeni
$admin = "8397170271"; // Sizning Telegram ID raqamingiz

// Papkalarni yaratish (baza o'rnida foydalanamiz)
if (!file_exists("users")) mkdir("users");

function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot".API_KEY."/".$method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if (curl_error($ch)) {
        var_dump(curl_error($ch));
    } else {
        return json_decode($res);
    }
}

// Avto Webhook ulanishi
$set_webhook = file_get_contents("https://api.telegram.org/bot".API_KEY."/setwebhook?url=".$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME']);

$update = json_decode(file_get_contents('php://input'));
$message = $update->message;
$cid = $message->chat->id;
$uid = $message->from->id;
$text = $message->text;
$name = $message->from->first_name;

// Callback querylar uchun (Tugmalar bosilganda)
$callback_query = $update->callback_query;
$cb_id = $callback_query->message->chat->id;
$cb_data = $callback_query->data;
$cb_mid = $callback_query->message->message_id;

// Foydalanuvchi tilini aniqlash/o'qish
$user_lang = file_get_contents("users/$cid.txt");

// Tilga mos matnlarni belgilash
$lang_text = [
    'uz' => [
        'welcome' => "👋 Salom, **$name**! Botga xush kelibsiz.\n\n📥 Menga Instagram, TikTok yoki YouTube ssilkasini yuboring, men uni yuklab beraman!",
        'loading' => "⏳ Yuklanmoqda, iltimos kuting...",
        'error' => "❌ Kechirasiz, video yuklashda xatolik yuz berdi yoki ssilka noto'g'ri.",
        'select_lang' => "🇺🇿 Iltimos, tilni tanlang:\n🇷🇺 Пожалуйста, выберите язык:\n🇹🇯 Лутфан, забонро интихоб кунед:"
    ],
    'ru' => [
        'welcome' => "👋 Привет, **$name**! Добро пожаловать в бот.\n\n📥 Отправьте мне ссылку на Instagram, TikTok или YouTube, и я скачаю её!",
        'loading' => "⏳ Загрузка, пожалуйста, подождите...",
        'error' => "❌ Извините, произошла ошибка при загрузке видео или ссылка неверна.",
        'select_lang' => "🇺🇿 Iltimos, tilni tanlang:\n🇷🇺 Пожалуйста, выберите язык:\n🇹🇯 Лутфан, забонро интихоб кунед:"
    ],
    'tj' => [
        'welcome' => "👋 Салом, **$name**! Ба бот хуш омадед.\n\n📥 Ба ман пайванди Instagram, TikTok ё YouTube-ро равон кунед, ман онро боргирӣ мекунам!",
        'loading' => "⏳ Боргирӣ рафта истодааст, лутфан мунтазир шавед...",
        'error' => "❌ Бубахшед, ҳангоми боргирии видео хатогӣ юз дод ё пайванд нодуруст аст.",
        'select_lang' => "🇺🇿 Iltimos, tilni tanlang:\n🇷🇺 Пожалуйста, выберите язык:\n🇹🇯 Лутфан, забонро интихоб кунед:"
    ]
];

// Til tanlash tugmalari (Inline)
$lang_keyboard = json_encode([
    'inline_keyboard' => [
        [
            ['text' => "🇺🇿 O'zbekcha", 'callback_data' => "lang_uz"],
            ['text' => "🇷🇺 Русский", 'callback_data' => "lang_ru"],
            ['text' => "🇹🇯 Тоҷикӣ", 'callback_data' => "lang_tj"]
        ]
    ]
]);

// Start buyrug'i berilganda
if ($text == "/start") {
    // Avval tilni tanlashni so'raymiz
    bot('sendMessage', [
        'chat_id' => $cid,
        'text' => $lang_text['uz']['select_lang'],
        'reply_markup' => $lang_keyboard
    ]);
    exit();
}

// Til tugmasi bosilganda xizmat ko'rsatish
if (strpos($cb_data, "lang_") === 0) {
    $selected_lang = str_replace("lang_", "", $cb_data);
    file_put_contents("users/$cb_id.txt", $selected_lang); // Tilni saqlaymiz
    
    bot('deleteMessage', [
        'chat_id' => $cb_id,
        'message_id' => $cb_mid
    ]);
    
    bot('sendMessage', [
        'chat_id' => $cb_id,
        'text' => $lang_text[$selected_lang]['welcome'],
        'parse_mode' => 'markdown'
    ]);
    exit();
}

// Agar ssilka yuborilsa (Instagram, TikTok, YouTube)
if (preg_match('/(instagram\.com|tiktok\.com|youtube\.com|youtu\.be)/i', $text)) {
    // Agar foydalanuvchi vaqtincha til tanlamagan bo'lsa, 'uz' qilamiz
    $current_lang = $user_lang ? $user_lang : 'uz';
    
    // "Yuklanmoqda..." xabarini yuborish
    $msg = bot('sendMessage', [
        'chat_id' => $cid,
        'text' => $lang_text[$current_lang]['loading']
    ]);
    $msg_id = $msg->result->message_id;

    // Ishlaydigan va yangi zamonaviy API (Universal video downloader)
    $api_url = "https://api.vkrdownloader.com/server?vkr=" . urlencode($text);
    $response = file_get_contents($api_url);
    $json = json_decode($response, true);

    // Videoni aniqlash
    $video_url = $json['data']['video'] ?? $json['url'] ?? $json['urls'][0]['url'];

    if ($video_url) {
        // Eski xabarni o'chirish
        bot('deleteMessage', [
            'chat_id' => $cid,
            'message_id' => $msg_id
        ]);

        // Videoni yuborish
        bot('sendVideo', [
            'chat_id' => $cid,
            'video' => $video_url,
            'caption' => "🎯 @Videorss_bot orqali yuklab olindi"
        ]);
    } else {
        // Agar birinchi API o'xshamaса, muqobil (zaxira) API orqali ko'ramiz
        $backup_api = "https://api.dandi.link/api/download?url=" . urlencode($text);
        $res_backup = json_decode(file_get_contents($backup_api), true);
        $video_backup = $res_backup['result']['url'] ?? $res_backup['url'];

        if ($video_backup) {
            bot('deleteMessage', [
                'chat_id' => $cid,
                'message_id' => $msg_id
            ]);

            bot('sendVideo', [
                'chat_id' => $cid,
                'video' => $video_backup,
                'caption' => "🎯 @Videorss_bot orqali yuklab olindi"
            ]);
        } else {
            // Agar umuman video topilmasa
            bot('editMessageText', [
                'chat_id' => $cid,
                'message_id' => $msg_id,
                'text' => $lang_text[$current_lang]['error']
            ]);
        }
    }
}
?>

