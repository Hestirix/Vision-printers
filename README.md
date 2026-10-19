# Vision Printers & Graphic Suppliers

Private local frontend prototype prepared under Lenga Systems. No deployment, SMTP, backend, analytics or external form service is configured.

## Built by Dennis Garfield from Lenga

Dennis Garfield one of lenga systems co-creators and software engineers. 

## Run locally

Requires Node.js 18 or newer. No package installation is necessary.

```sh
npm run dev
```

Open http://127.0.0.1:4173. The server builds once on startup; after source edits run `npm run build` and refresh, or restart the server. Stop with Ctrl+C.

```sh
npm run build
npm run check
```

The build generates six static HTML pages and assets in `dist/`: Home, Services, Equipment, About Us, Contact and Request a Quote. Plain HTML, CSS and vanilla JavaScript keep this suitable for future shared hosting. Custom CSS avoids framework dependencies. Generated HTML contains all content, including navigation, without requiring JavaScript. Google Fonts enhance typography when online; system fonts are the fallback.

## Editing

- `src/config.mjs`: centrally configured primary and secondary email, four telephone numbers, WhatsApp, address, hours and map URL. `formRecipient` remains the sales address only. Use international phone format and digits only for WhatsApp.
- `scripts/build.mjs`: page content, service data and reusable components.
- `src/style.css`: responsive design, focus states and reduced-motion handling.
- `src/refinement.css`: shared premium visual layer: frosted navigation, rounded photography, responsive service bento layout, blue pill buttons and refined form panels. Loaded after the base stylesheet; no UI framework added.
- `src/app.js`: mobile navigation, service preselection and form preview validation.
- `assets/images/`: untouched originals.
- `assets/optimized/`: 540px and 1080px JPEG derivatives, responsive source sets and lazy loading below the fold.

## Asset choices

Photo `(1)` is the press hero; `(5)` illustrates printing; `(2)` shows embroidery equipment; `(4)` shows embroidery in progress; `(11)` supports equipment storytelling. The nearly duplicate `(8)` and supplied stock files are unused. Stock licensing has not been confirmed. Service illustrations are CSS artwork, explicitly labelled, and are not completed client projects. Supplied workshop photos are not presented as client endorsements.

## Forms and next phase

Both forms explicitly disclose inactive submissions. The preview button performs browser validation and displays an informational status, never a success confirmation. No request, storage or upload occurs. The file picker accepts PDF/JPG/PNG references up to 10 MB for local interface review only. Required fields have labels and native validation. In this frontend-only phase JavaScript intercepts submission; do not enable live forms until a secure backend exists.

Future integration: PHP + PHPMailer on Hostinger, delivering both forms to `sales@visionprintersmw.com`. Add server-side validation, CSRF and spam controls, strict attachment validation, SMTP secrets outside public files and appropriate privacy wording. Client checks are not a security boundary. Hosting and email configuration are intentionally deferred.

## Content requiring confirmation

Portfolio photos and permission to display projects remain outstanding. The portfolio component is retained in `scripts/build.mjs` but omitted from generated HTML while `features.portfolio` is false in `src/config.mjs`. Enable it only after replacing the placeholder gallery with approved projects.

The confirmed street address and weekday/weekend hours are centrally configured alongside all existing contact details. The About page includes Timothy Nzima (Chief Executive Officer), Wesley Nzima (Managing Director), and the supplied wider-team paragraph, without portraits. Mission, vision and core values were verified against https://visionprintersmw.com/about/ on 19 October 2026. The original professional introduction is preserved. No company history, statistics or additional employee profiles were added. Exact embroidery manufacturer/model remains unconfirmed.

## Review

Check all six pages, navigation at desktop/mobile widths, quote service preselection, required fields, invalid file feedback, keyboard focus and reduced motion. The build check validates local links/assets and core form constraints. All pages have noindex/nofollow metadata for the prototype; this is not access control. This project must not be published without further approval.
