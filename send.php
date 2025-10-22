<?php
declare(strict_types=1);

// ===== CORS (дозволимо безпечне звернення; за потреби звузьте до вашого домену) =====
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
header('Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// ===== Налаштування (зробіть БЕЗПЕЧНО!) =====
$BOT_TOKEN = getenv('TG_BOT_TOKEN') ?: '8131201272:AAFSTAxK5Kr-1HvP5iHPwGq-x-YNEojezm8';
$CHAT_ID   = getenv('TG_CHAT_ID')   ?: '816561820';
$REDIRECT_SUCCESS = 'thank-you.html';

function json_response(array $payload, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function is_ajax(): bool {
  return !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
}

// ===== OPTIONS (preflight) повертаємо 204 OK =====
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// ===== GET (health-check) — повернемо інформаційний екран замість 405 =====
if ($method === 'GET') {
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><meta charset="utf-8"><title>send.php</title><style>body{font:14px/1.6 system-ui,Segoe UI,Roboto,Helvetica,Arial}</style>';
  echo '<h1>Endpoint працює</h1><p>Надсилайте <strong>POST</strong> із полями: <code>name</code>, <code>phone</code> (та опц. <code>product</code>, <code>price</code>).</p>';
  exit;
}

// ===== Дозволяємо тільки POST для надсилання =====
if ($method !== 'POST') {
  json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

// ===== Honeypot проти ботів =====
$honeypot = trim($_POST['website'] ?? '');
if ($honeypot !== '') {
  if (is_ajax()) json_response(['ok' => true]);
  header('Location: ' . $REDIRECT_SUCCESS);
  exit;
}

// ===== Отримуємо та валідируемо поля =====
$name    = trim($_POST['name']    ?? '');
$phone   = trim($_POST['phone']   ?? '');
$product = trim($_POST['product'] ?? 'Портативна мобільна батарея Powerbank VEGER VP3008PD 30000mAh');
$price   = trim($_POST['price']   ?? '1399 грн');

if ($name === '' || $phone === '') {
  json_response(['ok' => false, 'error' => 'Заповніть Ім’я та Телефон'], 422);
}
if (!preg_match('/^\+?[\d\s\-\(\)]{7,20}$/u', $phone)) {
  $phone .= ' (⚠ формат нестандартний)';
}

// ===== Готуємо повідомлення =====
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$when    = date('Y-m-d H:i:s');
$msg = "📦 Нове замовлення\n"
     . "👤 Ім’я: {$name}\n"
     . "📱 Телефон: {$phone}\n"
     . "🛒 Товар: {$product}\n"
     . "💰 Ціна: {$price}\n"
     . "🌐 Сторінка: {$referer}\n"
     . "🕒 Час: {$when}";

// ===== Відправка у Telegram через cURL =====
$ok = false;
if ($BOT_TOKEN !== '8131201272:AAFSTAxK5Kr-1HvP5iHPwGq-x-YNEojezm8' && $CHAT_ID !== '816561820') {
  $url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage";
  $payload = json_encode(['chat_id' => $CHAT_ID, 'text' => $msg], JSON_UNESCAPED_UNICODE);
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
    CURLOPT_POSTFIELDS     => $payload,
  ]);
  $res  = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  $ok = ($res !== false && $code >= 200 && $code < 300);
  if (!$ok && is_ajax()) {
    json_response(['ok' => false, 'error' => 'Не вдалося відправити (Telegram). ' . $err], 502);
  }
}

// ===== Відповідь клієнту =====
if (is_ajax()) {
  json_response(['ok' => true]);
} else {
  header('Location: ' . $REDIRECT_SUCCESS);
  exit;
}
