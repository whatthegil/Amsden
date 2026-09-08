@php $title = 'Sign In'; @endphp
@include('partials.head')

<div class="auth-page">
  <div class="auth-art">
    <div class="auth-art-logo">
      <img src="/images/cspc-logo.png" alt="CSPC" width="42" height="42" style="border-radius:8px;">
      <span>C-BAMS — CSPC Bluebook Archive Management System</span>
    </div>
    <h1>Bluebook Archive Management<br>System in<br><span>CSPC Library</span></h1>
    <p>Access, manage, and preserve capstone research papers from the Camarines Sur Polytechnic Colleges Digital Library.</p>
    <div class="auth-art-lines"></div>
  </div>

  <div class="auth-panel">
    <div class="auth-card login-card">
      <div class="login-logo">
        <img src="/images/cspc-logo.png" alt="CSPC-LeOnS" width="160">
      </div>

      <div aria-live="polite">
        @if($error ?? session('error'))
          <div class="alert alert-error" role="alert">{{ $error ?? session('error') }}</div>
        @endif
        @if($success ?? session('success'))
          <div class="alert alert-success" role="alert">{{ $success ?? session('success') }}</div>
        @endif
      </div>

      <form method="POST" action="/login" id="loginForm">
        @csrf
        <div class="input-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input id="login-email" type="email" name="email" value="{{ $email ?? old('email') }}" placeholder="Username / email" autocomplete="username" required autofocus>
        </div>

        <div class="input-icon input-icon--pw">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m15.5 7.5 2.5 2.5a1 1 0 0 0 1.4 0l1.6-1.6a1 1 0 0 0 0-1.4L20 5.5"/><path d="m21 2-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/></svg>
          <input id="login-password" type="password" name="password" placeholder="Password" autocomplete="current-password" required>
          <button type="button" class="password-toggle" data-toggle-password="login-password" aria-label="Show password" aria-pressed="false">
            <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M17.94 17.94A10.94 10.94 0 0112 19c-7 0-11-7-11-7a21.3 21.3 0 015.06-5.94M9.9 4.24A10.4 10.4 0 0112 4c7 0 11 7 11 7a21.4 21.4 0 01-3.22 4.36M14.12 14.12a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>

        <div class="login-forgot">
          <a href="{{ route('password.help') }}">Forgotten your username or password?</a>
        </div>

        <button type="submit" class="btn btn-gradient btn-full">Log in</button>

        <label class="login-remember">
          <input type="checkbox" name="remember" value="1" class="switch-input">
          <span class="switch" aria-hidden="true"></span>
          <span>Remember username</span>
        </label>
      </form>

      <div class="login-sep"></div>

      <p class="login-oauth-label">Log in using your account on:</p>

      <a href="{{ route('auth.google') }}" class="btn btn-google btn-full login-oauth-btn">
        <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.36-8.16 2.36-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
        CSPC Mail
      </a>

      <p class="login-note">
        Accounts are created by the library administrator.<br>
        Need access? Visit the CSPC Library or <a href="{{ route('password.help') }}">request assistance</a>.
      </p>
    </div>
  </div>
</div>

<script src="/js/main.js"></script>
<script>
  (function () {
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
      var input = document.getElementById(btn.getAttribute('data-toggle-password'));
      if (!input) return;
      btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.classList.toggle('is-visible', show);
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      });
    });
  })();

  (function () {
    var form = document.getElementById('loginForm');
    if (!form) return;
    form.addEventListener('submit', function () {
      var btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.classList.add('is-loading');
        btn.setAttribute('aria-busy', 'true');
        btn.disabled = true;
      }
    });
  })();
</script>
</body>
</html>
