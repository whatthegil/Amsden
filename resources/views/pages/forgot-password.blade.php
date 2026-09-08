@php $title = 'Account Help'; @endphp
@include('partials.head')

<div class="auth-page">
  <div class="auth-art">
    <div class="auth-art-logo">
      <img src="/images/cspc-logo.png" alt="CSPC" width="42" height="42" style="border-radius:8px;">
      <span>C-BAMS — CSPC Bluebook Archive Management System</span>
    </div>
    <h1>Need help<br>signing in to<br><span>C-BAMS?</span></h1>
    <p>Accounts for the Bluebook Archive Management System are managed by the CSPC Library. Self-service registration is not available.</p>
    <div class="auth-art-lines"></div>
  </div>

  <div class="auth-panel">
    <div class="auth-card login-card">
      <div class="login-logo">
        <img src="/images/cspc-logo.png" alt="CSPC-LeOnS" width="160">
      </div>

      <h2>Account &amp; password help</h2>

      <p class="subtitle">
        C-BAMS accounts are created by the library administrator. If you can't
        sign in, or you've forgotten your password, please do one of the
        following:
      </p>

      <ul class="help-list">
        <li>
          <strong>Sign in with your CSPC Google account.</strong>
          If your institutional email ends in <code>@cspc.edu.ph</code> or
          <code>@my.cspc.edu.ph</code>, use the <em>CSPC Mail</em> button on the
          login page — no separate C-BAMS password is needed.
        </li>
        <li>
          <strong>Contact the library administrator.</strong>
          Email <a href="mailto:library@cspc.edu.ph">library@cspc.edu.ph</a> or
          visit the CSPC Library to request an account or a password reset.
          Include your full name, institutional email, and program.
        </li>
      </ul>

      <a href="{{ route('login') }}" class="btn btn-gradient btn-full">Back to login</a>
    </div>
  </div>
</div>

<script src="/js/main.js"></script>
</body>
</html>
