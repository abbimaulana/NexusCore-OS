<?php
/**
 * NexusCore OS — modules/api/chatbot_api.php
 * Handles AJAX POST requests from the chatbot widget.
 * Supports: auto (keyword-based), openai, gemini providers.
 */

session_start();
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Read and sanitize input
$input    = json_decode(file_get_contents('php://input'), true);
$userMsg  = trim((string)($input['message'] ?? ''));

if ($userMsg === '') {
    echo json_encode(['reply' => 'Please type a message.']);
    exit;
}

$provider = getSetting('chatbot_provider', 'auto');
$apiKey   = getSetting('chatbot_api_key', '');

// ---------------------------------------------------------------
// AUTO — keyword-based responses from DB
// ---------------------------------------------------------------
if ($provider === 'auto' || empty($apiKey)) {
    echo json_encode(['reply' => getAutoResponse($userMsg)]);
    exit;
}

// ---------------------------------------------------------------
// OPENAI
// ---------------------------------------------------------------
if ($provider === 'openai') {
    $reply = callOpenAI($userMsg, $apiKey);
    echo json_encode(['reply' => $reply]);
    exit;
}

// ---------------------------------------------------------------
// GEMINI
// ---------------------------------------------------------------
if ($provider === 'gemini') {
    $reply = callGemini($userMsg, $apiKey);
    echo json_encode(['reply' => $reply]);
    exit;
}

// Fallback
echo json_encode(['reply' => getAutoResponse($userMsg)]);
exit;

// ---------------------------------------------------------------
// HELPER FUNCTIONS
// ---------------------------------------------------------------

/**
 * getAutoResponse(string $message) — keyword-based fallback
 */
function getAutoResponse(string $message): string
{
    global $db;
    $message = strtolower($message);

    if ($db) {
        $res = $db->query(
            'SELECT keywords, response_text FROM chatbot_responses WHERE is_active = 1'
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $keywords = array_map('trim', explode(',', strtolower($row['keywords'])));
                foreach ($keywords as $kw) {
                    if ($kw !== '' && str_contains($message, $kw)) {
                        return $row['response_text'];
                    }
                }
            }
            $res->free();
        }
    }

    // Global fallback from settings
    $fallback = getSetting('chatbot_auto_script', 'Hello! How can I assist you today?');
    return $fallback;
}

/**
 * callOpenAI(string $message, string $apiKey) — GPT chat completion
 */
function callOpenAI(string $message, string $apiKey): string
{
    $url     = 'https://api.openai.com/v1/chat/completions';
    $siteName = getSetting('site_name', 'NexusCore OS');
    $payload = json_encode([
        'model'    => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => "You are a helpful assistant for {$siteName}. Be concise and friendly."],
            ['role' => 'user',   'content' => $message],
        ],
        'max_tokens' => 200,
    ]);

    return makeApiPost($url, $payload, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ], function (string $body): string {
        $data = json_decode($body, true);
        return $data['choices'][0]['message']['content'] ?? 'Sorry, I could not get a response.';
    });
}

/**
 * callGemini(string $message, string $apiKey) — Gemini generateContent
 */
function callGemini(string $message, string $apiKey): string
{
    $url     = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$apiKey}";
    $payload = json_encode([
        'contents' => [['parts' => [['text' => $message]]]],
    ]);

    return makeApiPost($url, $payload, ['Content-Type: application/json'],
        function (string $body): string {
            $data = json_decode($body, true);
            return $data['candidates'][0]['content']['parts'][0]['text']
                ?? 'Sorry, I could not get a response.';
        }
    );
}

/**
 * makeApiPost — shared HTTP POST helper (cURL with file_get_contents fallback)
 *
 * @param  callable $parser  Parses raw response body to string reply
 */
function makeApiPost(string $url, string $payload, array $headers, callable $parser): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $err  = curl_errno($ch);
        curl_close($ch);
        if ($err !== 0 || $body === false) {
            return 'Service temporarily unavailable. Please try again.';
        }
        return $parser($body);
    }

    // file_get_contents fallback
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", $headers),
            'content' => $payload,
            'timeout' => 15,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return 'Service temporarily unavailable. Please try again.';
    }
    return $parser($body);
}
