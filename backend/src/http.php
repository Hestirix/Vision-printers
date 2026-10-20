<?php
declare(strict_types=1);
namespace Vision;
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/mail.php';

function respond(int $status, array $body): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE); exit;
}

function run(string $route): never {
    ini_set('display_errors', '0');
    $reference = bin2hex(random_bytes(8));
    try {
        $c = config(); $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if ($method !== ($route === 'session' ? 'GET' : 'POST')) { header('Allow: ' . ($route === 'session' ? 'GET' : 'POST')); respond(405, ['ok'=>false,'message'=>'Method not allowed.']); }
        if (!in_array($c['mode'], ['mock', 'smtp'], true)) respond(503, ['ok'=>false,'message'=>'Form submissions are not enabled. Please contact us by email or telephone.']);
        $origin = parse_url($c['origin']);
        if (!$origin || empty($origin['host']) || !in_array($origin['scheme'] ?? '', ['http','https'], true) || isset($origin['user']) || isset($origin['query']) || !empty($origin['path'])) throw new \RuntimeException('origin configuration');
        $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true);
        if ($c['mode'] === 'mock') {
            if (PHP_SAPI !== 'cli-server' || !$local || !in_array($origin['host'], ['127.0.0.1','localhost','::1'], true)) respond(503, ['ok'=>false,'message'=>'Mock mode is restricted to the local PHP development server.']);
            $c['key'] = 'local-mock-rate-key-not-used-in-production';
        } elseif ($origin['scheme'] !== 'https' || strlen($c['key']) < 32 || !filter_var($c['from'], FILTER_VALIDATE_EMAIL) || !str_ends_with(strtolower($c['from']), '@visionprintersmw.com') || preg_match('/[\r\n]/', $c['from']) || !$c['host'] || !$c['user'] || !$c['password'] || !in_array($c['tls'], ['starttls','smtps'], true) || !in_array($c['port'], [465,587], true)) {
            throw new \RuntimeException('SMTP configuration');
        }
        $expectedHost = $origin['host'] . (isset($origin['port']) ? ':' . $origin['port'] : '');
        if (strtolower($_SERVER['HTTP_HOST'] ?? '') !== strtolower($expectedHost)) respond(403, ['ok'=>false,'message'=>'Origin not allowed.']);
        if ($method === 'POST' && ($_SERVER['HTTP_ORIGIN'] ?? '') !== $c['origin']) respond(403, ['ok'=>false,'message'=>'Origin not allowed. Reload the form.']);
        if (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && !in_array($_SERVER['HTTP_SEC_FETCH_SITE'], ['same-origin','none'], true)) respond(403, ['ok'=>false,'message'=>'Cross-site request rejected.']);
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > MAX_FILE + 1048576) respond(413, ['ok'=>false,'message'=>'The request exceeds the 10 MB artwork limit.']);
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_file($autoload)) throw new \RuntimeException('dependencies');
        require_once $autoload;
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) throw new \RuntimeException('mailer unavailable');
        $ip = $_SERVER['REMOTE_ADDR'] ?? ''; // Deliberately do not trust forwarded client headers.
        if (!rateLimit($c, 'ip:' . $ip . ':' . $route, $route === 'session' ? 60 : 5, 900)) respond(429, ['ok'=>false,'message'=>'Too many requests. Please try again later.']);
        ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
        session_name('vision_forms');
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$origin['scheme'] === 'https','httponly'=>true,'samesite'=>'Strict']);
        if (!session_start()) throw new \RuntimeException('session');
        if ($route === 'session') {
            if (!isset($_SESSION['csrf']) || ($_SESSION['created'] ?? 0) < time() - 3600) {
                $_SESSION = ['csrf'=>bin2hex(random_bytes(32)), 'created'=>time(), 'requests'=>[]];
            }
            respond(200, ['ok'=>true,'mode'=>$c['mode'],'csrf'=>$_SESSION['csrf'],'maxFileBytes'=>MAX_FILE]);
        }
        $token = $_POST['csrf'] ?? null;
        if (!is_string($token) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token) || ($_SESSION['created'] ?? 0) < time() - 3600) respond(403, ['ok'=>false,'message'=>'Your form session expired. Reload this page and try again.']);
        if (!is_string($_POST['website'] ?? null) || $_POST['website'] !== '' || time() - $_SESSION['created'] < 2) respond(422, ['ok'=>false,'message'=>'Request rejected. Please wait a moment and try again.']);
        $requestId = $_POST['request_id'] ?? '';
        if (!is_string($requestId) || !preg_match('/^[a-f0-9-]{36}$/D', $requestId)) respond(422, ['ok'=>false,'message'=>'Invalid request identifier. Reload the form.']);
        $key = $route . ':' . $requestId;
        if (isset($_SESSION['requests'][$key])) {
            $prior = $_SESSION['requests'][$key];
            respond($prior['code'], $prior['body']);
        }
        $data = validate($route, $_POST);
        if (array_diff(array_keys($_FILES), $route === 'quote' ? ['artwork'] : [])) throw new InputError(['artwork'=>'Attachments are only allowed on quote inquiries.']);
        $file = $route === 'quote' ? attachment($_FILES['artwork'] ?? null) : null;
        if (!rateLimit($c, 'email:' . strtolower($data['email']), 3, 3600) || !rateLimit($c, 'global', 100, 3600)) respond(429, ['ok'=>false,'message'=>'Submission limit reached. Please try again later.']);
        $uncertain = ['ok'=>false,'message'=>'We could not confirm email acceptance. Do not resubmit automatically; please contact us directly with reference ' . $reference . '.', 'reference'=>$reference];
        if (count($_SESSION['requests']) >= 20) array_shift($_SESSION['requests']);
        // Mark before SMTP: an uncertain failure must never lead to an automatic duplicate.
        $_SESSION['requests'][$key] = ['code'=>502,'body'=>$uncertain];
        try {
            $result = deliver($c, $route, $data, $file, $reference);
            $_SESSION['requests'][$key] = ['code'=>200,'body'=>$result];
            error_log('vision_forms ' . $result['status'] . ' reference=' . $reference);
            respond(200, $result);
        } catch (\Throwable) {
            error_log('vision_forms mail_failed reference=' . $reference);
            respond(502, $uncertain);
        }
    } catch (InputError $e) {
        respond(422, ['ok'=>false,'message'=>$e->getMessage(),'errors'=>$e->fields]);
    } catch (\Throwable) {
        // Never log SMTP errors, credentials, addresses, submitted text, paths or upload names.
        error_log('vision_forms unavailable reference=' . $reference);
        respond(503, ['ok'=>false,'message'=>'The inquiry service is unavailable. Please contact us directly.','reference'=>$reference]);
    }
}
