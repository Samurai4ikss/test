<?php
declare(strict_types=1);

// ===== Налаштування (зробіть БЕЗПЕЧНО!) =====
// Рекомендовано задати через змінні оточення (наприклад у панелі хостингу)
$BOT_TOKEN = getenv('TG_BOT_TOKEN') ?: 'PASTE_TELEGRAM_BOT_TOKEN';
$CHAT_ID   = getenv('TG_CHAT_ID')   ?: 'PASTE_CHAT_ID';

// Куди редиректити у разі не-AJAX запиту (без JS)
$REDIRECT_SUCCESS = 'thank-you.html';

// ===== Сервісні функції =====
function json_response(array $payload, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function is_ajax(): bool {
  return !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
}

// ===== Дозволяємо тільки POST =====
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(['ok' => false, 'error' => 'Only POST is allowed'], 405);
}

// ===== Honeypot проти ботів =====
$honeypot = trim($_POST['website'] ?? '');
if ($honeypot !== '') {
  if (is_ajax()) json_response(['ok' => true]); // удаємо успіх, але нічого не відправляємо
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
// М’яка перевірка формату телефону
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
if ($BOT_TOKEN !== 'PASTE_TELEGRAM_BOT_TOKEN' && $CHAT_ID !== 'PASTE_CHAT_ID') {
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

// ===== Альтернатива: якщо токен/чат не задані, можна дублювати на email =====
// @mail('you@example.com', 'Нове замовлення', $msg, "Content-Type: text/plain; charset=UTF-8");

// ===== Відповідь клієнту =====
if (is_ajax()) {
  json_response(['ok' => true]);
} else {
  header('Location: ' . $REDIRECT_SUCCESS); // якщо форма без JS — редирект на сторінку подяки
  exit;
}
