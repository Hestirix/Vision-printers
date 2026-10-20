<?php
declare(strict_types=1);

namespace Vision;

const RECIPIENT = 'sales@visionprintersmw.com';
const SERVICES = ['Large-Format Printing & Signage', 'Custom Apparel & Embroidery', 'Commercial & Digital Printing', 'Graphic Design & Branding', 'Promotional Branding', 'Document Printing & Internet Café', 'Other / help me choose'];
const MAX_FILE = 10485760;

final class InputError extends \RuntimeException {
    public function __construct(public array $fields) { parent::__construct('Please correct the highlighted fields.'); }
}

function config(): array {
    $root = dirname(__DIR__);
    $local = is_file($root . '/config.local.php') ? require $root . '/config.local.php' : [];
    $get = static fn(string $key, string $default = ''): string => (string)(getenv($key) !== false ? getenv($key) : ($local[$key] ?? $default));
    return [
        'mode' => $get('VP_MODE', 'disabled'), 'origin' => rtrim($get('VP_ORIGIN'), '/'),
        'from' => $get('VP_FROM_EMAIL'), 'host' => $get('VP_SMTP_HOST'),
        'port' => (int)$get('VP_SMTP_PORT', '587'), 'tls' => $get('VP_SMTP_TLS', 'starttls'),
        'user' => $get('VP_SMTP_USER'), 'password' => $get('VP_SMTP_PASSWORD'),
        'key' => $get('VP_RATE_KEY'), 'ack' => $get('VP_ACK_ENABLED', '0') === '1',
        'storage' => $root . '/var',
    ];
}

function validate(string $kind, array $input): array {
    $errors = []; $data = [];
    $rules = ['name' => [120, true], 'email' => [254, true], 'phone' => [40, $kind === 'quote']];
    $rules += $kind === 'quote'
        ? ['service' => [120, true], 'quantity' => [7, true], 'description' => [5000, true], 'date' => [10, false]]
        : ['subject' => [180, true], 'message' => [5000, true]];
    foreach ($rules as $field => [$max, $required]) {
        $value = $input[$field] ?? '';
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) { $errors[$field] = 'Invalid text.'; continue; }
        $value = trim($value);
        $multiline = in_array($field, ['message', 'description'], true);
        if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $max || preg_match($multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/', $value)) {
            $errors[$field] = "Enter valid text, up to {$max} characters.";
        }
        $data[$field] = $value;
    }
    if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
    if (($data['phone'] ?? '') !== '' && !preg_match('/^\+?[0-9 ()\-.]{6,40}$/D', $data['phone'])) $errors['phone'] = 'Enter a valid telephone number.';
    if ($kind === 'quote') {
        if (!in_array($data['service'] ?? '', SERVICES, true)) $errors['service'] = 'Choose a listed service.';
        if (!preg_match('/^[1-9][0-9]{0,6}$/D', $data['quantity'] ?? '') || (int)$data['quantity'] > 1000000) $errors['quantity'] = 'Enter a whole quantity from 1 to 1,000,000.';
        if (($data['date'] ?? '') !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['date']);
            if (!$date || $date->format('Y-m-d') !== $data['date']) $errors['date'] = 'Enter a valid calendar date.';
        }
    }
    if ($errors) throw new InputError($errors);
    return $data;
}

function attachment(?array $file, bool $requireUpload = true): ?array {
    if ($file === null || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) return null;
    $fail = static function(): never { throw new InputError(['artwork' => 'Choose a PDF, PNG or JPG up to 10 MB, with a simple filename and a single extension.']); };
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['name'] ?? null) || !is_string($file['tmp_name'] ?? null)) $fail();
    $name = $file['name']; $path = $file['tmp_name'];
    if (!preg_match('/^[a-zA-Z0-9 _()-]{1,100}\.(pdf|png|jpe?g)$/Di', $name, $match)) $fail();
    if (($requireUpload && !is_uploaded_file($path)) || !is_file($path)) $fail();
    $size = filesize($path);
    if ($size === false || $size < 1 || $size > MAX_FILE) $fail();
    $ext = strtolower($match[1]);
    $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
    $expected = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];
    if ($mime !== $expected[$ext]) $fail();
    $contents = file_get_contents($path);
    if ($contents === false || preg_match('/<\?(?:php|=)|<script\b|<%/i', $contents)) $fail();
    if ($ext === 'pdf') {
        // Reject obvious active content and embedded payloads; this is not an antivirus scanner.
        if (!str_starts_with($contents, '%PDF-') || !str_contains(substr($contents, -2048), '%%EOF') || preg_match('/\/(?:JavaScript|JS|Launch|EmbeddedFile|OpenAction|AA|RichMedia|Encrypt)\b/i', $contents)) $fail();
    } else {
        $info = @getimagesize($path);
        if (!$info || ($info['mime'] ?? '') !== $mime || $info[0] > 20000 || $info[1] > 20000 || $info[0] * $info[1] > 100000000) $fail();
    }
    // Mail directly from PHP's temporary upload; never move artwork to a public directory.
    return ['path' => $path, 'name' => 'artwork.' . $ext, 'mime' => $mime];
}

function message(string $kind, array $data, string $reference): array {
    $title = $kind === 'quote' ? 'Quotation inquiry' : 'General inquiry';
    $labels = ['name'=>'Customer name','email'=>'Email','phone'=>'Phone','service'=>'Requested service','quantity'=>'Estimated quantity','description'=>'Project description','date'=>'Preferred completion date','subject'=>'Subject','message'=>'Message'];
    $plain = "Vision Printers — {$title}\nReference: {$reference}\n\n";
    $rows = '';
    $escape = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    foreach ($data as $key => $value) {
        $label = $labels[$key]; $value = $value !== '' ? $value : 'Not provided';
        $plain .= "{$label}: {$value}\n\n";
        $rows .= '<tr><th style="text-align:left;vertical-align:top;padding:12px;border-bottom:1px solid #dce7ef">'.$label.'</th><td style="padding:12px;border-bottom:1px solid #dce7ef">'.nl2br($escape($value)).'</td></tr>';
    }
    return ['subject'=>"{$title} — {$reference}", 'text'=>$plain, 'html'=>'<html><body style="font-family:Arial,sans-serif;color:#1c2128"><h1 style="color:#187eb9">'.$title.'</h1><p>Vision Printers &amp; Graphic Suppliers · Reference '.$escape($reference).'</p><table style="border-collapse:collapse;width:100%">'.$rows.'</table><p>Customer-supplied content. Treat links and artwork as untrusted. Preferred dates are requests, not confirmed commitments.</p></body></html>'];
}

function rateLimit(array $config, string $bucket, int $limit, int $window): bool {
    $dir = $config['storage'];
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new \RuntimeException('storage');
    $fp = fopen($dir . '/rate.json', 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) throw new \RuntimeException('rate lock');
    try {
        $raw = stream_get_contents($fp);
        $state = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $now = time();
        foreach ($state as $key => $entry) if ($entry['expires'] <= $now) unset($state[$key]);
        $key = hash_hmac('sha256', $bucket, $config['key']);
        $entry = $state[$key] ?? ['count'=>0,'expires'=>$now+$window];
        $allowed = $entry['count'] < $limit && count($state) < 10000;
        if ($allowed) { $entry['count']++; $state[$key] = $entry; }
        $json = json_encode($state, JSON_THROW_ON_ERROR);
        rewind($fp); if (!ftruncate($fp, 0) || fwrite($fp, $json) !== strlen($json) || !fflush($fp)) throw new \RuntimeException('rate write');
        return $allowed;
    } finally { flock($fp, LOCK_UN); fclose($fp); }
}
