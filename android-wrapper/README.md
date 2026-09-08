# C-BAMS Android wrapper

A WebView around the C-BAMS archive whose window carries Android's
`FLAG_SECURE`. Screenshots, screen recordings and the recent-apps thumbnail all
come out black for this app.

This exists because **a web page cannot do this.** `FLAG_SECURE` is a window
flag the Android compositor honours, so it has to be set by an application. The
browser build of the archive cannot request it, which is why blocking
screenshots there is impossible rather than merely unimplemented.

## What it does and does not change

Everything else stays on the server and behaves exactly as it does in a
browser — sign-in, the watermark, the audit log, role checks. This adds one
protection and changes nothing else.

| | Browser | This app |
|---|---|---|
| Screenshot | Succeeds, watermarked | **Blank** |
| Screen recording | Succeeds, watermarked | **Blank** |
| Recent-apps thumbnail | n/a | **Blank** |
| Viewing a bluebook PDF | Works | **Does not render** — see below |

### iOS

There is no equivalent. iOS offers no supported way for an app to blank
screenshots of arbitrary content, so an iPhone build would not gain this
protection.

### The PDF limitation

Android's WebView ships **no PDF renderer**, unlike desktop Chrome. The bluebook
page embeds the document in an `<iframe>`, which stays blank inside a WebView.
The app replaces that frame with a short explanation rather than leaving an
empty box.

So today this wrapper protects browsing, searching and metadata, but the
document itself still has to be read in a browser — where screenshots work
normally. That is a real gap, and closing it means rendering PDFs as canvas with
PDF.js instead of an iframe. That change belongs in the Laravel app, benefits the
browser build too, and is worth doing before relying on this wrapper for
document protection.

## Building

Needs Android Studio (Ladybug or newer) or a local JDK 17 with the Android SDK.

```bash
cd android-wrapper
./gradlew assembleDebug          # app/build/outputs/apk/debug/app-debug.apk
```

Or open the `android-wrapper` folder in Android Studio and press Run.

Gradle wrapper files are not committed. Android Studio generates them on first
open; from the command line run `gradle wrapper` once with a local Gradle 8.7+.

### Pointing it at a different URL

Both values live in `app/build.gradle.kts`:

```kotlin
buildConfigField("String", "ARCHIVE_URL",  "\"https://your-domain\"")
buildConfigField("String", "ARCHIVE_HOST", "\"your-domain\"")
```

The Laravel Cloud domain is derived from the app and environment names, so
renaming either in Laravel Cloud changes this URL and the app must be rebuilt.

### A launcher icon

None is committed, so the platform default is used. Add one in Android Studio
via *File → New → Image Asset*, which writes `@mipmap/ic_launcher`, then
reference it in `AndroidManifest.xml`.

## Verifying it actually blocks screenshots

This cannot be verified by reading the code — it has to be observed on a device
or emulator. Install the debug APK and check all five:

1. **Screenshot** (Power + Volume Down) — Android shows *"Can't take screenshot
   due to security policy"*, or saves a black image.
2. **Screen recording** — the recording plays back black for this app.
3. **Recent apps** (square/swipe up) — the app's card is blank, not a preview.
4. **Google Assistant / "share screenshot"** — same block.
5. **Casting or screen mirroring** — the window does not appear on the external
   display.

If any of those succeed, the flag is not reaching that window. Check that
`CbamsApplication` is registered as `android:name` on `<application>` in the
manifest, since that is what guarantees the flag on every window rather than
only the one activity.

### Why the flag is applied in two places

`MainActivity` sets it, and `CbamsApplication` sets it again for every activity
created. That is deliberate. `FLAG_SECURE` is per-window, not per-app: anything
that opens its own window — an activity added later, a dialog hosted in one, a
file picker — starts unprotected unless it asks. Relying on each one to remember
is how a screenshot gets through a build that looks correct.

## What this is not

It does not stop a photograph of the screen taken with another phone, and it
protects only this app. Someone who opens the same archive in Chrome gets a
normal, screenshot-able page. The wrapper raises the effort required; it does
not make the content unreproducible, and no software can.
