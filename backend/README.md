# Vision Printers form backend

This is development work, not a deployed service. Production SMTP is disabled by default. No credentials are provided. Work stays on `development`; do not commit, push or deploy without review.

## Runtime and installation

Use a supported PHP 8.2+ release (8.4+ recommended for a new installation), Composer 2 and extensions `fileinfo`, `mbstring`, `openssl`, `session`, plus standard `filter`, `hash`, `ctype` and JSON support. Image metadata checks use built-in `getimagesize`; GD is not required. PHPMailer is declared in `backend/composer.json` using the official Composer package.

From the project root:

```powershell
composer install --working-dir=backend --no-dev --prefer-dist --optimize-autoloader
composer audit --working-dir=backend
npm.cmd run build
```

The first install creates `backend/composer.lock`. Review and retain that lockfile in the eventual reviewed commit; deploy with `composer install`, not `update`. Composer/PHP are not bundled. Never fabricate a lockfile without resolving dependencies. `vendor/` is ignored and stays private. No frontend package install is needed.

## Safe local testing: no SMTP and no real customer email

The Node preview at port 4173 serves static assets only and returns 503 for PHP/API requests. Restart an older Node preview after this change. Use one origin for frontend and PHP; do not combine the Node page with cross-origin PHP requests.

After installing PHP, Composer dependencies and building, run in PowerShell:

```powershell
$env:VP_MODE = 'mock'
$env:VP_ORIGIN = 'http://127.0.0.1:8080'
php -d upload_max_filesize=10M -d post_max_size=12M -d memory_limit=128M -S 127.0.0.1:8080 -t dist
```

Open `http://127.0.0.1:8080/contact.html` or `/quote.html`. Do not use the Node port for backend testing. Mock mode is allowed only by PHP's built-in CLI server with loopback client and origin; it cannot be enabled on Hostinger. PHPMailer generates both inquiry and acknowledgment MIME in memory via `preSend()`; no SMTP connection occurs and no message body or attachment is saved. Only rate-limit counters and minimal session metadata persist. The UI says **Local test mode**, and successful tests explicitly say no email was sent. A successful status is returned only after MIME preparation succeeds. Ctrl+C stops the server. Clear `VP_MODE`/`VP_ORIGIN` in that shell afterward if desired.

No `.env` loader is used. Set real process environment variables, or copy `config.example.php` to the ignored `backend/config.local.php`. Environment values take precedence. Never put this config in `dist`, `public_html`, JavaScript, a downloadable archive or Git.

## Tests

```powershell
npm.cmd run build
npm.cmd run check
Get-ChildItem backend -Filter *.php -Recurse | Where-Object { $_.FullName -notmatch '[\\/]vendor[\\/]' } | ForEach-Object { php -l $_.FullName }
php backend/tests/unit.php
php backend/tests/mail.php
# With the loopback mock server running:
node backend/tests/http.mjs
```

The unit suite tests field limits, invalid email/header controls, dates, quantities, HTML escaping, MIME/extension mismatches, double extensions, executable markers, oversize uploads and atomic rate limits. The HTTP suite checks successful mock contact and quote requests, mock acknowledgment generation, origin/CSRF rejection, honeypot, duplicate suppression, bad attachments and rate limits. It refuses to submit unless the session endpoint reports mock mode. Use synthetic data only. The HTTP suite deliberately exhausts localhost limits: wait 15 minutes before rerunning, or remove only the local `backend/var/rate.json` after stopping the local server. Never reset production limits for a test.

The mail suite uses the same preparation functions as the endpoints and PHPMailer's real `preSend()` implementation. It checks the business recipient, absence of CC/BCC, Reply-To, sender, both body formats, encoded attachment bytes, and separate acknowledgment recipient/content. It never calls `send()` or connects to SMTP. See `TESTING.md` for the latest execution results.

Also verify in a browser: disabled button with Node-only preview, disabled backend, missing Composer dependencies, required fields, quote service preselection, invalid file feedback, mobile layout, and session expiry. If testing SMTP failures later, use an isolated mail sink with test credentials and synthetic addresses; never enable live SMTP just to exercise failure paths. An inquiry transport error returns 502 without success. An acknowledgment error after business acceptance reports acceptance with a separate warning; it must not invite duplicate inquiries.

## Public/private structure for future shared hosting

```text
account-directory/
  backend/                    PRIVATE; outside public_html
    src/                      core.php, http.php, mail.php
    vendor/                   installed Composer dependencies
    config.local.php          optional secrets, never committed
    var/rate.json             private counters, runtime-created
    composer.json
    composer.lock             generated by installation
  public_html/                contents of dist/, not the repository
    index.html, services.html, equipment.html, about.html, contact.html, quote.html
    style.css, refinement.css, app.js, assets/
    api/session.php
    api/contact.php
    api/quote.php
```

The endpoint wrappers resolve `../../backend/src/http.php` from `public_html/api`. Keep the same sibling relationship locally (`dist/` and `backend/`). Do not place `backend` under the document root. Do not deploy repository sources, original uploads, tests, secrets or `.git`. The build copies only the three public wrappers; it never copies backend configuration or vendor files. A host without configurable private sibling storage needs a reviewed path change before activation, not public placement of secrets.

## Endpoints and response contracts

- `GET /api/session.php`: verifies mode/config/dependencies, issues a one-hour CSRF token in a strict HttpOnly session and returns `mode`, `csrf`, `maxFileBytes`. No CORS is enabled.
- `POST /api/contact.php`: `name`, `email`, optional `phone`, `subject`, `message`, `csrf`, empty honeypot `website`, `request_id`.
- `POST /api/quote.php`: `name`, `email`, `phone`, `service`, `quantity`, `description`, optional `date` and multipart `artwork`, plus the same protection fields.

JSON status codes: 200 accepted/mock-complete, 403 origin/session failure, 405 method, 413 oversized request, 422 fields/upload/spam timing, 429 limits, 502 uncertain mail acceptance, 503 disabled/configuration/dependency failure. A 200 SMTP result means the configured SMTP server accepted the message, not proof of delivery to an inbox. The frontend requires the expected mode/status/reference before displaying success. On network loss it retains the request ID; same-session retries reuse the prior response. Session reset/expiry removes duplicate history, so do not automate retries or open new forms after uncertain failures.

## Mail and configuration

The business recipient is the server-side constant `sales@visionprintersmw.com`; request parameters cannot change To/CC/BCC. `src/config.mjs` retains the same display recipient. The only additional recipient is an optional separate acknowledgment to the validated customer address, as explicitly requested. Reply-To on the inquiry is the customer; on acknowledgments it is the sales mailbox.

`VP_MODE=smtp` requires all of the following:

| Setting | Requirement |
| --- | --- |
| `VP_ORIGIN` | Exact HTTPS origin, no trailing path, matching the site's host and port |
| `VP_FROM_EMAIL` | Verified business-controlled `@visionprintersmw.com` sender |
| `VP_SMTP_HOST` | Confirmed SMTP hostname |
| `VP_SMTP_PORT` | 587 for STARTTLS or 465 for implicit TLS |
| `VP_SMTP_TLS` | `starttls` or `smtps`; TLS peer verification remains enabled |
| `VP_SMTP_USER`, `VP_SMTP_PASSWORD` | Private server credentials |
| `VP_RATE_KEY` | At least 32 cryptographically random characters |
| `VP_ACK_ENABLED` | Default `0`; set `1` only after production anti-abuse review |

Generate the rate key on the server with `php -r "echo bin2hex(random_bytes(32));"` and keep it private. It hashes client IP/email rate buckets; never rotate casually because existing counters would no longer match.

## Security and operating limits

- Allowlisted fields with byte/control checks, UTF-8/length checks, email validation, phone format, known service, quantity 1–1,000,000 and real calendar dates. Preferred dates are requests, not bookings.
- CSRF token compared in constant time, one-hour expiry, exact Origin and Host checks, SameSite Strict/HttpOnly cookies and Secure cookies on HTTPS. All submit requests require Origin. Do not disable checks to accommodate a proxy; configure and verify the host/origin architecture.
- Honeypot plus a two-second minimum form session age; five submissions per IP per endpoint per 15 minutes, three per destination email per hour, 100 accepted attempts globally per hour, and 60 session initializations per IP per 15 minutes. All writes use an exclusive file lock and fail closed. Forwarded IP headers are ignored; configure a trusted proxy deliberately if introduced. These baseline protections do not stop distributed abuse; assess challenge/WAF protection before enabling acknowledgments publicly.
- Limit uploads to 10 MB, a simple single-extension filename and PDF/PNG/JPEG detected by `fileinfo`. Image dimensions and obvious executable/active PDF content are checked. MIME checks are not malware scanning: complex/obfuscated PDF payloads cannot be ruled out. Treat artwork as untrusted, keep mail-client protection enabled and add malware scanning if stronger content guarantees are required. No uploads are served or permanently stored. PHP removes temporary uploads at request end, including rejection paths.
- Logs contain only generic event types and random references, never customer fields, email bodies, IPs, uploaded filenames, tokens, SMTP error text or credentials. Rate storage contains only HMAC bucket IDs, counts and expiry; expired records are pruned on subsequent requests. Use owner-only permissions for private storage/config (0700 directories, 0600 config), restrictive Windows ACLs locally, protected PHP session storage and an agreed short log retention period. Host access logs are separate and must also be reviewed.
- Duplicate suppression is session-scoped, not a durable database queue. Do not promise exactly-once mail delivery across server crashes or expired sessions. No automatic mail retry is performed.

## Production activation checklist — documentation only

1. Review code and run PHP syntax/unit/HTTP tests, resolve Composer dependencies, retain lockfile and run `composer audit`.
2. Confirm business sender, SMTP provider settings, DNS SPF/DKIM/DMARC and mailbox access. Supply secrets privately; nothing is configured here.
3. Keep backend/config/vendor outside `public_html`; verify direct HTTP requests cannot access them. Give only required filesystem permissions.
4. Configure HTTPS, exact origin, protected sessions, PHP upload limit 10M, POST limit 12M and memory 128M or more; set `display_errors=Off`. Confirm TLS certificate validation works.
5. Agree privacy wording, inquiry retention, attachment handling, access-log retention and anti-abuse limits. Leave acknowledgments off until reviewed.
6. Test against a safe mail sink, including SMTP failure and acknowledgment failure. Then perform an explicitly authorized controlled mailbox test; verify actual inbox receipt independently of SMTP acceptance.
7. Only after separate deployment approval set `VP_MODE=smtp`, enable acknowledgments if approved, and review the prototype `noindex` policy. A rollback sets `VP_MODE=disabled` without changing the frontend.

No production SMTP credentials, hosting connection, publication, commit or push is part of this implementation.
