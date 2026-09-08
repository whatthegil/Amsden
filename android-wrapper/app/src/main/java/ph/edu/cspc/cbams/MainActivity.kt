package ph.edu.cspc.cbams

import android.annotation.SuppressLint
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.view.WindowManager
import android.webkit.CookieManager
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.appcompat.app.AppCompatActivity

/**
 * A WebView around the C-BAMS archive whose window is marked FLAG_SECURE.
 *
 * This is the only way to make a screenshot of this content come out blank.
 * A web page cannot ask for it: FLAG_SECURE is a window flag the Android
 * compositor honours, so it has to be set by an application, and the effect is
 * that screenshots, screen recordings and the recent-apps thumbnail all show
 * black for this window. The browser build of the site cannot do this and never
 * will, which is why this wrapper exists.
 *
 * Everything else - authentication, the watermark, the audit log - stays on the
 * server and behaves exactly as it does in a browser. This adds one protection
 * and changes nothing else.
 */
class MainActivity : AppCompatActivity() {

    private lateinit var webView: WebView

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        // Before any content exists, so no frame is ever composited unprotected.
        window.setFlags(
            WindowManager.LayoutParams.FLAG_SECURE,
            WindowManager.LayoutParams.FLAG_SECURE,
        )

        super.onCreate(savedInstanceState)

        webView = WebView(this).apply {
            settings.apply {
                javaScriptEnabled = true          // the archive is a Blade app with client-side behaviour
                domStorageEnabled = true          // sidebar state and similar preferences
                cacheMode = WebSettings.LOAD_DEFAULT
                mediaPlaybackRequiresUserGesture = true
                allowFileAccess = false           // nothing local is needed
                allowContentAccess = false
            }

            // Long-press selection and the context menu are already suppressed by
            // the site's own script; blocking them here as well keeps the
            // behaviour consistent if that script fails to load.
            isLongClickable = false
            setOnLongClickListener { true }

            webViewClient = ArchiveWebViewClient()
        }

        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true)
        setContentView(webView)

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (webView.canGoBack()) webView.goBack() else finish()
            }
        })

        if (savedInstanceState == null) {
            webView.loadUrl(BuildConfig.ARCHIVE_URL)
        }
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        webView.saveState(outState)
    }

    override fun onRestoreInstanceState(savedInstanceState: Bundle) {
        super.onRestoreInstanceState(savedInstanceState)
        webView.restoreState(savedInstanceState)
    }

    private inner class ArchiveWebViewClient : WebViewClient() {

        override fun shouldOverrideUrlLoading(
            view: WebView,
            request: WebResourceRequest,
        ): Boolean {
            val url = request.url

            // Google sign-in has to run in this WebView, or the session it
            // establishes would land in the external browser instead.
            val host = url.host ?: return false
            val internal = host == BuildConfig.ARCHIVE_HOST ||
                host.endsWith("google.com") ||
                host.endsWith("googleusercontent.com") ||
                host.endsWith("gstatic.com")

            if (internal) return false

            // Anything else - a mailto: link, an outside site - leaves the app,
            // so protected content is never rendered in a window without the
            // secure flag.
            return try {
                startActivity(Intent(Intent.ACTION_VIEW, url))
                true
            } catch (_: Exception) {
                Toast.makeText(this@MainActivity, "No app can open that link.", Toast.LENGTH_SHORT).show()
                true
            }
        }

        override fun onPageFinished(view: WebView, url: String?) {
            // Android's WebView ships no PDF renderer, unlike desktop Chrome, so
            // the inline <iframe> on a bluebook page stays blank. Say so rather
            // than leaving a silent empty box.
            if (url != null && url.contains("/student/bluebooks/")) {
                view.evaluateJavascript(PDF_NOTICE, null)
            }
        }
    }

    private companion object {
        /**
         * Replaces an empty PDF frame with an explanation. Kept here rather than
         * on the server so the browser build is untouched: this limitation only
         * exists inside a WebView.
         */
        const val PDF_NOTICE = """
            (function () {
              var f = document.querySelector('iframe[src*="/file"]');
              if (!f) return;
              var note = document.createElement('div');
              note.style.cssText = 'padding:2rem;text-align:center;color:#4a5568;' +
                'background:#f7f8fa;border:1px solid #edf2f7;border-radius:8px;font:14px system-ui;';
              note.textContent = 'This document opens in the browser version of the archive. ' +
                'Screenshot protection applies to this app only.';
              f.parentNode.replaceChild(note, f);
            })();
        """
    }
}
