<?php
/**
 * NexusCore OS — modules/api/telegram.php
 * Sends formatted Telegram notifications for new orders and contact messages.
 * Uses cURL with file_get_contents fallback.
 */

/**
 * sendTelegramMessage(string $text, array $replyMarkup = [])
 * Sends a message to the configured Telegram chat.
 *
 * @param  string $text        MarkdownV2 message text
 * @param  array  $replyMarkup Optional inline keyboard markup array
 * @return bool   true on success
 */
function sendTelegramMessage(string $text, array $replyMarkup = []): bool
{
    // Config loaded from DB via getSetting()
    $token  = getSetting('telegram_bot_token');
    $chatId = getSetting('telegram_chat_id');

    if (empty($token) || empty($chatId)) {
        return false; // Not configured
    }

    $url     = "https://api.telegram.org/bot{$token}/sendMessage";
    $payload = [
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => 'Markdown',
    ];

    if (!empty($replyMarkup)) {
        $payload['reply_markup'] = json_encode($replyMarkup);
    }

    $jsonPayload = json_encode($payload);

    // --- cURL (preferred) ---
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $err      = curl_errno($ch);
        curl_close($ch);
        return $err === 0 && $response !== false;
    }

    // --- file_get_contents fallback ---
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $jsonPayload,
            'timeout' => 10,
        ],
    ]);
    $result = @file_get_contents($url, false, $context);
    return $result !== false;
}

/**
 * sendOrderNotification(array $order, array $items)
 * Formats and sends a new-order notification to Telegram.
 *
 * @param array $order  Associative array from orders table
 * @param array $items  Array of order_items rows
 */
function sendOrderNotification(array $order, array $items): bool
{
    // Build item list
    $itemLines = '';
    foreach ($items as $item) {
        $itemLines .= "  • " . $item['product_name']
            . " x" . $item['qty']
            . " — " . formatRupiah((float)$item['price'] * (int)$item['qty']) . "\n";
    }

    $waNumber = preg_replace('/\D/', '', $order['buyer_wa']);
    $datetime = date('d M Y H:i', strtotime($order['created_at']));

    $text = "🛒 *NEW ORDER — NexusCore OS*\n\n"
        . "👤 *Buyer:* " . $order['buyer_name'] . "\n"
        . "📧 Email: " . $order['buyer_email'] . "\n"
        . "📱 WhatsApp: " . $order['buyer_wa'] . "\n\n"
        . "🛍 *Items:*\n" . $itemLines . "\n"
        . "🔧 *Service Detail:* " . $order['service_detail'] . "\n"
        . "💰 *Total:* " . formatRupiah((float)$order['total_amount']) . "\n"
        . "💳 *Payment:* " . $order['payment_method'] . "\n\n"
        . "🆔 Order Code: " . $order['order_code'] . "\n"
        . "⏰ " . $datetime;

    // Inline keyboard
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '💬 Reply WA', 'url' => "https://wa.me/{$waNumber}"],
                ['text' => '📧 ' . $order['buyer_email'], 'callback_data' => 'email_reply'],
            ],
        ],
    ];

    return sendTelegramMessage($text, $keyboard);
}

/**
 * sendContactNotification(array $message)
 * Notifies admin of a new contact form submission.
 */
function sendContactNotification(array $message): bool
{
    $datetime = date('d M Y H:i', strtotime($message['created_at'] ?? 'now'));
    $text = "📩 *NEW CONTACT MESSAGE — NexusCore OS*\n\n"
        . "👤 *From:* " . $message['name'] . "\n"
        . "📧 Email: " . $message['email'] . "\n\n"
        . "💬 *Message:*\n" . $message['message'] . "\n\n"
        . "⏰ " . $datetime;

    return sendTelegramMessage($text);
}
