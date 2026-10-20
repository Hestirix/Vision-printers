<?php
// Optional server-only configuration. Copy to config.local.php OUTSIDE public_html.
// Environment variables override this file. No real credentials belong in Git.
return [
    'VP_MODE' => 'disabled', // disabled | mock | smtp
    'VP_ORIGIN' => 'http://127.0.0.1:8080',
    'VP_FROM_EMAIL' => '', // Verified business-controlled mailbox in visionprintersmw.com
    'VP_SMTP_HOST' => '',
    'VP_SMTP_PORT' => '587',
    'VP_SMTP_TLS' => 'starttls', // starttls (587) | smtps (465)
    'VP_SMTP_USER' => '',
    'VP_SMTP_PASSWORD' => '',
    'VP_RATE_KEY' => '', // At least 32 random characters; generate on the server.
    'VP_ACK_ENABLED' => '0', // Explicitly enable after anti-abuse and mailbox review.
];
