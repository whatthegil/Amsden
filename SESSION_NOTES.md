# SESSION_NOTES.md — AMSDEN Laravel

> Snapshot of the whole system as of **2026-07-20**. Written after a full read-through of the codebase.

## What this is

**AMSDEN** — a bluebook (undergraduate thesis / capstone document) repository for **CSPC** (Camarines Sur Polytechnic Colleges). Students and faculty browse, upload, bookmark, and search approved bluebooks; admins curate the archive, manage users, and audit activity. Includes OCR full-text extraction of uploaded PDFs, a similarity checker for proposed titles, and a literature-review search tool.

- **Stack:** Laravel 9 (PHP ^8.0), MySQL (`laravel` db via XAMPP at `C:\xampp\php\php.exe`), Blade views + vanilla JS/CSS (no build pipeline in active use; `webpack.mix.js` is stock).
- **Not a git repository.**
- **Key packages:** `laravel/socialite` (Google OAuth), `laravel/sanctum` (installed, only used by the stock `/api/user` route), `guzzlehttp/guzzle`.
- **Tests:** only the stock Laravel example tests — no real coverage.

## Auth model (important — non-standard)

The app does **not** use Laravel's auth guards. `AuthController` writes a `user` array directly into the session (`session(['user' => [...]])`), and every controller reads `session('user')`. Route protection is the custom `role` middleware (`app/Http/Middleware/EnsureRole.php`, aliased in `app/Http/Kernel.php:66`), which redirects and writes a "Unauthorized Access Attempt" audit log on denial.

- **Allowed emails:** only `@cspc.edu.ph` (→ role **Faculty**) and `@my.cspc.edu.ph` (→ role **Student**). Third role: **Admin** (created via admin panel / seed).
- **Two login paths:** email+password form, and Google OAuth (Socialite; `stateless()` callback). Google signups get a random 32-char password and `can_upload = false`; existing email accounts get google_id/avatar linked on first Google login.
- **Passwords are bcrypt-hashed** (fixed 2026-07-20): `Hash::make` on register / Google signup / admin create+update / seeder, `Hash::check` on login. Migration `2026_07_20_000001_hash_plaintext_user_passwords` hashed all pre-existing plaintext rows (idempotent, no down()). `Store::userToArray` no longer returns the password field.
- ⚠️ `.env` contains a **real Google client secret** and committed-style APP_KEY; `.env` redirect URI is `http://127.0.0.1:8000/auth/google/callback`.

## Routes / features (routes/web.php)

**Admin** (`/admin/*`, `role:Admin`): dashboard with stats; bluebook CRUD (+ approve / reject / delete / reprocess-OCR; admin-created bluebooks are auto-**Approved**); user CRUD + enable/disable upload permission; audit log viewer with search/filter.

**Profile** (`/profile`, any logged-in role, added 2026-07-20): `ProfileController` — view account info (live from DB, not the session snapshot), edit name (session re-synced after save), change password (requires current password via `Hash::check`; new min 8 chars, confirmed). Reached via the sidebar user chip, which is now a link. Email/role are not editable. Actions logged as "Updated Profile" / "Changed Password".

**Student & Faculty** (`/student/*`, `role:Student,Faculty`):
- Acceptable Use Policy → dashboard (recent/popular/my bluebooks). As of 2026-07-20 this only appears on an account's genuinely first login: `users.policy_accepted_at` (nullable timestamp, migration `2026_07_20_000002_...`, backfilled to `now()` for all pre-existing accounts so nobody already using the system got re-prompted) gates `AuthController::redirectToDashboard` — null sends the user to `student.policy`, set sends them straight to `student.dashboard`. The page's "I Agree" button is a POST to `student.policy.accept` (`StudentController::acceptPolicy` → `Store::acceptPolicy($email)`), which stamps the column once and is permanent — accepting never has to happen twice for the same account, including via Google OAuth signups.
- Browse/search approved bluebooks; view detail (increments views, logs the view); inline PDF streaming (`bluebookFile`, `Content-Disposition: inline`, `no-store`, `X-Frame-Options: SAMEORIGIN`).
- Upload (PDF ≤ 25 MB, gated by per-user `can_upload` flag; lands as **Pending** and dispatches OCR).
- Reprocess own OCR (has an explicit ownership check — others get 403 + audit log).
- Similarity check, literature review, my-uploads, history (own logs), bookmarks add/remove.
- `flag-capture` POST endpoint: JS calls it when screenshot/screen-share behavior is detected → "Screenshot/Recording Attempt" audit log entry.

## Data model (5 tables + framework tables)

- **users** — name, email, plaintext password, role (Admin/Faculty/Student), `can_upload` bool, `google_id`, `avatar`.
- **bluebooks** — metadata (title, authors JSON, year, department, program, keywords JSON, abstract, adviser), status (Pending/Approved/Rejected), uploader email+name, pages, views, `date_added` (string), file fields (`file_path`, `file_original_name`, `file_size`), OCR fields (`ocr_status`, `ocr_text`, `ocr_error`, `ocr_engine`, `ocr_rasterizer`, `ocr_processed_at`).
- **logs** — audit trail: user_name, email, action, document, timestamp (Asia/Manila string), status (Success/Denied/Flagged). Written on nearly every action via `Store::addLog`.
- **bookmarks** — keyed by **user_email** + bluebook_id (not user id).
- **jobs** — Laravel database queue (`QUEUE_CONNECTION=database`).

All DB access funnels through **`app/Services/Store.php`**, a static facade that converts models to camelCase arrays (`bookToArray`, `userToArray`, `logToArray`) — controllers/views work with arrays, never Eloquent models directly. (`storage/app/store.json` is a leftover from a pre-database JSON store.)

## OCR pipeline

`ProcessBluebookOcr` job (queue, 2 tries, 300 s timeout) → `OcrService::extractText()`:
1. **Rasterize** PDF to page images — first available of: MuPDF `mutool` → Ghostscript → Poppler `pdftoppm` (`RasterizerResolver`, binaries auto-detected by `BinaryFinder` or pinned via `OCR_*_PATH` env).
2. **Recognize** each page — Tesseract if installed, else **Windows built-in OCR** via `scripts/windows-ocr.ps1` (Windows.Media.Ocr WinRT, zero-install fallback).
3. Caps: 300 DPI, 60 pages, 500 k chars, 120 s per process (all in `config/ocr.php`). Temp work dirs under `storage/app/ocr-tmp/`, cleaned up in `finally`.
4. On success: stores text + engine/rasterizer names; on failure: `ocr_status=failed` + truncated error.

**Worker:** `scripts/run-queue-worker.ps1` — self-healing loop around `php artisan queue:work --sleep=3 --tries=3 --max-time=3600`, logging to `storage/logs/queue-worker.log` (via cmd `>>` on purpose — PowerShell piping locked/corrupted the log). Registered as logon Scheduled Task **"AmsdenLaravelQueueWorker"**. If OCR seems stuck, check this task is running.

## Search / similarity (all in-PHP, no external services)

- **`SimilarityService`** — `tokenize` (lowercase, strip non-alnum, stopwords, len > 2), `jaccard`, and `overlapCoefficient` (fraction of the *smaller* set matched — used whenever a short query meets a long OCR text so scores don't collapse).
  - `computeSimilarity` (duplicate-title checker): title 50% + keywords 20% + abstract 10% + OCR overlap 20%; results shown at score ≥ 0.08.
  - `searchRelevance` (browse-search ranking): title 40% + keywords 20% + abstract 15% + authors 10% + OCR 15% + 0.25 literal-phrase-in-title bonus.
- **`Store::getBluebooks(search)`** does broad LIKE recall (whole phrase OR each token, across title/abstract/authors/keywords/ocr_text), then re-ranks with `searchRelevance`.
- **`LiteratureReviewService`** — metadata jaccard 70% + OCR containment 30%, min 0.05, unchanged. As of 2026-07-21, the top `AI_SUMMARY_LIMIT` (6) results get a **real AI-written summary** grounded in each paper's title/abstract/department **and an excerpt (5000 chars) of its OCR'd document text** — not just the abstract. One batched request per search (all candidates in a single call) via `App\Services\Ai\AnthropicClient::createJsonMessage()`, using `output_config.format` (structured outputs / json_schema) so the response parses reliably into `{papers: [{id, summary}]}`. Ranking itself is untouched — the AI only rewrites `summary` text and sets `aiGenerated: true` on results it touches; the view (`student-literature-review.blade.php`) shows a "✨ AI Summary" badge on those. Results beyond the limit, and every result when the AI call fails for *any* reason (no key, refusal, network error, malformed JSON), keep the original extractive summary (`summarize()` — top-2 abstract sentences by token overlap) — `search()` never errors or drops results because of the AI layer.
  - **Config:** `ANTHROPIC_API_KEY` / `ANTHROPIC_MODEL` (default `claude-opus-4-8`) in `.env` → `config/services.php` → `anthropic.*`. Blank key = feature silently disabled, confirmed working via browser test (no server errors, page renders extractive summaries as before).
  - **Why raw HTTP, not the official SDK:** `anthropic-ai/sdk` requires PHP `^8.1`; this project's `composer.json` and the installed CLI (`C:\xampp\php\php.exe`, 8.0.30) are PHP `^8.0`, confirmed by a failed `composer require` during this session. `AnthropicClient` talks to `POST https://api.anthropic.com/v1/messages` directly via Guzzle (already a dependency) instead.
  - **Not yet tested against a live key** — no `ANTHROPIC_API_KEY` or `ant auth login` profile exists in this dev environment. The request/response shape was built and lint-checked against the Claude API skill's PHP reference and structured-outputs spec, but the first real key should be smoke-tested (search any topic, confirm the ✨ badge appears and Laravel logs show no `Anthropic API request failed` warnings).

## Visual theme (restyled 2026-07-20, SciSpace-inspired layout + original blue accent)

Moved from the original dark navy/photo-background design to a light, neutral SciSpace-style layout: white `--white`/`--bg: #f7f8fa` surfaces, Inter font throughout (was Playfair Display headings + DM Sans body — all `font-family: 'Playfair Display'` rules replaced with `font-weight:700; letter-spacing:-0.02em` on the existing sans stack). Sidebar is now white with a light-gray border (was dark navy `rgba(15,35,80,.97)`); active nav item is a soft accent-tinted pill (`--primary-light` bg, `--primary` text) instead of translucent white-on-navy. Auth pages dropped the full-bleed CSPC photo + dark overlay in favor of a plain light background with soft radial gradients.

The accent color went through two passes: first swapped to violet (`--primary: #6549d5`) to match SciSpace exactly, then **reverted to the original blue (`--primary: #1a56db`) per explicit request** — everything else from the restyle (white sidebar, flat background, Inter font, layout) was kept. All accent-derived variables (`--primary-dark`, `--primary-mid`, `--primary-light`, `--primary-pale`, `--shadow-md` tint) and the few hardcoded `rgba(101,73,213,…)` violet shadow/gradient values in auth-page and profile-avatar rules are all blue again. Neutral grays/status colors (`--gray-*`, `--red`, `--green`, `--yellow`) stayed at their SciSpace values — those were never violet, so "undo the color changes" only touched the accent. Because nearly everything routes through CSS variables, this was a small, contained edit — no per-component rewrite. Verified: `--primary` computes to `#1a56db` in the browser, zero mobile overflow regressions.

## Responsive layout (added 2026-07-20)

Below 900px the sidebar becomes an off-canvas drawer: `main.js` injects a hamburger button into every `.topbar` and an overlay inside `.app` (must stay inside `.app` — it creates a stacking context, and the overlay needs to sit under the sidebar's z-index). Toggling adds `body.sidebar-open`. On desktop (901px+) the same hamburger instead collapses the sidebar to a 76px icon-only rail (`html.sidebar-collapsed` overrides `--sidebar-w`; nav text hidden via `font-size: 0`, pending count becomes a corner mini-badge, tooltips added from link text). When collapsed, the logo in `.sidebar-brand` is also hidden and replaced in the same spot by a `.sidebar-expand-btn` (»» icon) that re-expands the rail — added so there's a way to reopen it without reaching back to the topbar hamburger. The state persists in `localStorage['amsden-sidebar']` and is pre-applied by an inline script in `partials/head.blade.php` before first paint to avoid a flash. Key CSS facts: `.main` has `min-width: 0` (without it, wide tables stretch the whole page past the viewport on mobile); breakpoints at 1100/900/768/600/480px; inputs go to 16px on mobile to stop iOS focus-zoom; the topbar email is hidden ≤600px. Verified with Playwright at 390px and 1440px: zero horizontal overflow on all student and admin pages, drawer opens/closes via button, overlay, Escape, and nav clicks.

## Content protection (public/js/main.js)

Active on logged-in pages: right-click blocked, Ctrl+P/S/U and DevTools shortcuts blocked, text selection disabled on content areas, image/link drag disabled. On the bluebook detail page specifically: repeating rotated CSPC-logo canvas watermark overlay, `getDisplayMedia` wrapped to blur content + red banner during screen share, PrintScreen keyup and window-blur trigger blur + banner + throttled POST to `flag-capture` (audit log). Comments are honest that OS-level capture can't truly be blocked — this is deterrence + audit only.

## How to run

```powershell
C:\xampp\php\php.exe artisan serve          # dev server (past sessions used ports 8000/8123)
# queue worker: Scheduled Task "AmsdenLaravelQueueWorker", or manually:
powershell -NoProfile -File scripts\run-queue-worker.ps1
```

MySQL must be running (XAMPP). OCR needs at least one rasterizer installed (mutool/gs/pdftoppm); recognition falls back to Windows OCR if Tesseract is absent.

## Rate limiting (added 2026-07-21)

Configured in `RouteServiceProvider::configureRateLimiting()`, applied via the `throttle:<name>` middleware on individual routes in `routes/web.php`:
- **`login`** — 6/min by IP, on `POST /login`. Brute-force protection; this app has no Laravel auth-guard lockout otherwise (see Auth section — session-array auth, not guards).
- **`register`** — 4/min by IP, on `POST /register`.
- **`literature-review-ai`** — 15/hour, keyed by the session user's email (falls back to IP), on `POST /student/literature-review` only (the GET form-render route is untouched). Cost protection: each POST can trigger a paid Claude API call — see Literature Review AI section above.

`app/Exceptions/Handler.php` catches `ThrottleRequestsException` globally and redirects back with `session('error')` instead of Laravel's bare 429 page — matches the app's existing flash-alert pattern (see `login.blade.php`'s `$error ?? session('error')`, now also on `register.blade.php` and `student-literature-review.blade.php`). Verified live: 8 rapid login POSTs → first 6 render the normal "invalid credentials" page, 7th+ redirect back with the styled "Too many attempts" alert.

## Known gaps / candidate next steps

1. Secrets in `.env` (Google client secret, and now a live `ANTHROPIC_API_KEY`) with no git history to scrub — rotate before sharing the folder or pushing anywhere public. `.env` is gitignored so it hasn't been committed.
2. Little server-side validation beyond the file rules (most text inputs unvalidated).
3. No real test suite.
4. `date_added` is a string column; sorting on dashboard uses `strtotime` in PHP.

~~Plaintext passwords~~ — fixed 2026-07-20, see Auth section. ~~No git repo~~ — `git init` run 2026-07-20 (not yet committed as of this note). ~~`LiteratureReviewService::summarize` designated LLM seam~~ — implemented 2026-07-21, see AI section above.

## File map (the parts that matter)

```
app/Http/Controllers/   AuthController, AdminController, StudentController (all logic here)
app/Http/Middleware/    EnsureRole (the 'role' alias — the whole authz layer)
app/Services/           Store (DB facade), OcrService, SimilarityService, LiteratureReviewService
app/Services/Ai/        AnthropicClient (raw-HTTP Claude API wrapper — see Literature Review AI note above)
app/Services/Ocr/       BinaryFinder, Engines/ (Tesseract, WindowsOcr, resolver), Rasterizers/ (MuPdf, Ghostscript, Poppler, resolver)
app/Jobs/               ProcessBluebookOcr
app/Models/             User, Bluebook, Log, Bookmark (thin — fillable + casts only)
routes/web.php          all routes (api.php is stock)
resources/views/pages/  one blade per screen (admin-*, student-*, login, register, policy)
resources/views/partials/ head, footer, admin-sidebar, student-sidebar
public/js/main.js       UI helpers + content-protection layer
public/css/style.css    all styling
config/ocr.php          OCR tunables (paths, dpi, max_pages, timeout, max_text_length)
scripts/                run-queue-worker.ps1 (worker loop), windows-ocr.ps1 (WinRT OCR)
database/migrations/    2024_* core tables, 2026_07_08_* OCR fields + jobs table
```
