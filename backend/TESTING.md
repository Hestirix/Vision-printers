# Backend verification — 19 October 2026

Environment: Windows, PHP 8.4.25, Composer 2.10.3, PHPMailer v7.1.1; Git branch `development`.

## Passed

- PHP syntax checks for every PHP file under `backend/`, including installed vendor files.
- `composer check-platform-reqs`: all PHP/extension requirements met.
- `composer audit`: no security vulnerability advisories reported at test time.
- `composer validate --no-check-publish`: valid manifest/lock; informational missing-license warning remains for the private application.
- `php backend/tests/unit.php`: field limits, header controls, email, date, service and quantity validation; escaped HTML; valid PNG/JPEG/PDF allowlist checks; rejected executable/double-extension/path filenames, MIME mismatch, PHP markers, active PDF markers, oversized and partial uploads; rate-limit enforcement.
- `php backend/tests/mail.php`: real PHPMailer MIME preparation for contact/quote and acknowledgments, exact business recipient `sales@visionprintersmw.com`, no business CC/BCC, validated customer Reply-To, business-controlled mock sender, escaped HTML/plain alternatives and attachment filename/MIME/payload. No send operation.
- `node backend/tests/http.mjs` against PHP bound to `127.0.0.1:8080`, with `VP_MODE=mock`: contact and quote 200 mock results, acknowledgment preparation, stable reference on duplicate retry, 403 bad CSRF/origin, 422 invalid email/quantity/honeypot/artwork, 429 rate limit and 405 wrong method. Rerun passed after mail preparation was extracted into testable functions.
- Frontend build and checks, JavaScript syntax and Git whitespace checks.
- `backend/vendor/`, `backend/config.local.php`, `.env` and `backend/var/` ignored; no vendor files tracked. Composer lock reviewed for the expected package and public credential-free source URLs, and staged by explicit user request. No commit or push.

## Changes and interruptions

- Extracted `prepareInquiry()` and `prepareAcknowledgment()` from `deliver()` to exercise the actual message builders directly. Delivery behavior is unchanged.
- Added MIME tests and expanded valid JPEG/PDF and active-PDF rejection coverage; documented the new command.
- Initial localhost endpoint access and Composer audit DNS were blocked by the execution sandbox. Repeating those commands with approved network access passed; these were environment failures, not backend test failures.
- The HTTP suite intentionally exhausts local counters. The test server was stopped and its local counter file moved to an ignored backup before retesting. No production state exists or was reset.

## Remaining limits

Live SMTP authentication, TLS negotiation with a provider, actual inbox delivery and SMTP/acknowledgment failure behavior have not been integration-tested. No real SMTP credentials were configured and no real email was sent. Production activation and hosting remain out of scope. MIME/extension checks are not antivirus scanning; the existing operational and retention guidance still applies. Duplicate protection is session-scoped and does not guarantee exactly-once delivery across crashes or session expiry.

The backend is ready for a reviewed development commit, not production activation. Follow `README.md` for later configuration and authorized delivery testing.
