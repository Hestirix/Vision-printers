<?php
declare(strict_types=1);
namespace Vision;

function mailer(array $config): \PHPMailer\PHPMailer\PHPMailer {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->CharSet = 'UTF-8'; $mail->Encoding = 'base64';
    $mail->setFrom($config['mode'] === 'mock' ? 'preview@visionprintersmw.com' : $config['from'], 'Vision Printers & Graphic Suppliers');
    $mail->isHTML(true);
    if ($config['mode'] === 'smtp') {
        $mail->isSMTP(); $mail->Host = $config['host']; $mail->SMTPAuth = true;
        $mail->Username = $config['user']; $mail->Password = $config['password'];
        $mail->Port = $config['port']; $mail->SMTPSecure = $config['tls'] === 'smtps' ? 'ssl' : 'tls';
        $mail->SMTPDebug = 0; $mail->Timeout = 15; $mail->Timelimit = 20;
    }
    return $mail;
}

function prepareInquiry(array $config, string $kind, array $data, ?array $file, string $reference): \PHPMailer\PHPMailer\PHPMailer {
    $mail = mailer($config);
    $mail->addAddress(RECIPIENT); // The only business recipient. No CC/BCC or recipient from request data.
    $mail->addReplyTo($data['email'], $data['name']);
    $content = message($kind, $data, $reference);
    $mail->Subject = $content['subject']; $mail->Body = $content['html']; $mail->AltBody = $content['text'];
    if ($file) $mail->addAttachment($file['path'], $file['name'], 'base64', $file['mime']);
    return $mail;
}

function prepareAcknowledgment(array $config, string $email, string $reference): \PHPMailer\PHPMailer\PHPMailer {
    $receipt = mailer($config);
    $receipt->addAddress($email);
    $receipt->addReplyTo(RECIPIENT, 'Vision Printers');
    $receipt->Subject = 'Your Vision Printers inquiry — ' . $reference;
    // No customer-supplied message, links or attachment in the acknowledgment.
    $receipt->AltBody = "Thank you for contacting Vision Printers & Graphic Suppliers. Your inquiry reference is {$reference}. Our team will review your request. This acknowledgment does not confirm a quotation or completion date.\nIf you did not make this inquiry, no action is required.";
    $receipt->Body = '<p>' . nl2br(htmlspecialchars($receipt->AltBody, ENT_QUOTES, 'UTF-8')) . '</p>';
    return $receipt;
}

function deliver(array $config, string $kind, array $data, ?array $file, string $reference): array {
    $mail = prepareInquiry($config, $kind, $data, $file, $reference);
    if ($config['mode'] === 'mock') {
        // Exercise MIME generation in memory. Never invoke SMTP or persist message/artwork data.
        if (!$mail->preSend()) throw new \RuntimeException('mock MIME');
    } elseif (!$mail->send()) { throw new \RuntimeException('SMTP not accepted'); }
    $ack = 'disabled';
    if ($config['ack'] || $config['mode'] === 'mock') {
        try {
            $receipt = prepareAcknowledgment($config, $data['email'], $reference);
            if ($config['mode'] === 'mock') { if (!$receipt->preSend()) throw new \RuntimeException('mock acknowledgment'); $ack = 'mocked'; }
            else { if (!$receipt->send()) throw new \RuntimeException('ack'); $ack = 'accepted'; }
        } catch (\Throwable) {
            $ack = 'failed'; error_log('vision_forms acknowledgment_failed reference=' . $reference);
        }
    }
    return ['ok'=>true,'mode'=>$config['mode'],'status'=>$config['mode'] === 'mock' ? 'mocked' : 'accepted','reference'=>$reference,'acknowledgment'=>$ack];
}
