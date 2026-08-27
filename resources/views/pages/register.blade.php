@php $title = 'Register'; @endphp
@include('partials.head')

<div class="auth-page">
  <div class="auth-art">
    <div class="auth-art-logo">
      <img src="/images/cspc-logo.png" alt="CSPC" width="42" height="42" style="border-radius:8px;">
      <span>C-BAMS — Bluebook Archive</span>
    </div>
    <h1>Join the<br>CSPC<br><span>Digital Library</span></h1>
    <p>Create your institutional account to access and upload bluebook capstone research papers.</p>
    <div class="auth-art-lines"></div>
  </div>

  <div class="auth-panel">
    <div class="auth-card">
      <h2>Create Account</h2>
      <p class="subtitle">Register with your CSPC institutional account</p>

      @if($error ?? session('error'))
        <div class="alert alert-error">{{ $error ?? session('error') }}</div>
      @endif

      {{-- Google Sign-Up --}}
      <a href="{{ route('auth.google') }}" class="btn btn-google btn-full" style="margin-bottom:1rem;justify-content:center;gap:0.65rem;text-decoration:none;">
        <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.36-8.16 2.36-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="none" d="M0 0h48v48H0z"/></svg>
        Continue with CSPC Google Account
      </a>

      <div class="divider"><span>or register with email</span></div>

      <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="form-group">
          <label for="register-name">Full Name</label>
          <input id="register-name" type="text" name="name" placeholder="e.g. Juan Dela Cruz" required>
        </div>
        <div class="form-group">
          <label for="register-email">Email Address</label>
          <input id="register-email" type="email" name="email" placeholder="yourname@cspc.edu.ph or @my.cspc.edu.ph" required aria-describedby="register-email-hint">
          <p class="form-hint" id="register-email-hint">Only @cspc.edu.ph or @my.cspc.edu.ph emails are accepted.</p>
        </div>
        <div class="form-group">
          <label for="register-password">Password</label>
          <input id="register-password" type="password" name="password" placeholder="Create a password" required>
        </div>
        <div class="form-group">
          <label for="register-confirm-password">Confirm Password</label>
          <input id="register-confirm-password" type="password" name="confirmPassword" placeholder="Repeat your password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full" style="margin-bottom:1rem;">Create Account</button>
      </form>

      <div style="text-align:center;font-size:0.85rem;color:var(--gray-400);">
        Already have an account?
        <a href="{{ route('login') }}" style="color:var(--primary);font-weight:600;">Sign in</a>
      </div>
    </div>
  </div>
</div>

<script src="/js/main.js"></script>
</body>
</html>
