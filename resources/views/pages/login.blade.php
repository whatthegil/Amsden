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
    <div class="auth-card">
      <h2>Welcome Back</h2>
      <p class="subtitle">Sign in with your CSPC institutional account</p>

      @if($error ?? session('error'))
        <div class="alert alert-error">{{ $error ?? session('error') }}</div>
      @endif
      @if($success ?? null)
        <div class="alert alert-success">{{ $success }}</div>
      @endif

      {{-- Google Sign-In --}}
      <a href="{{ route('auth.google') }}" class="btn btn-google btn-full" style="margin-bottom:1rem;justify-content:center;gap:0.65rem;text-decoration:none;">
        <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.36-8.16 2.36-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="none" d="M0 0h48v48H0z"/></svg>
        Continue with CSPC Google Account
      </a>

      <div class="divider"><span>or sign in with email</span></div>

      <form method="POST" action="/login">
        @csrf
        <div class="form-group">
          <label for="login-email">Email Address</label>
          <input id="login-email" type="email" name="email" placeholder="yourname@cspc.edu.ph" required autofocus>
        </div>
        <div class="form-group">
          <label for="login-password">Password</label>
          <input id="login-password" type="password" name="password" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full" style="margin-bottom:1rem;">Sign In</button>
      </form>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
        <span style="font-size:0.85rem;color:var(--gray-400);">
          Don't have an account?
          <a href="{{ route('register') }}" style="color:var(--primary);font-weight:600;">Register here</a>
        </span>
        <button type="button" id="testAccountsBtn" class="btn btn-outline btn-sm">
          Test Accounts
        </button>
      </div>

      <div class="quick-test-box">
        <p class="label">Quick Test</p>
        <div>
          <div class="row"><strong>Admin:</strong> jealmonte@cspc.edu.ph / admin123</div>
          <div class="row"><strong>Student:</strong> bhokrealubit@my.cspc.edu.ph / student123</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Account Picker Modal -->
<div id="accountModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="accountModalTitle">
  <div class="modal-card">
    <div class="modal-header">
      <h3 id="accountModalTitle">Select Test Account</h3>
      <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </div>

    <div class="modal-body">
      <form method="POST" action="/login">
        @csrf
        <input type="hidden" name="email" value="jealmonte@cspc.edu.ph">
        <input type="hidden" name="password" value="admin123">
        <button type="submit" class="account-option">
          <div class="avatar" style="background:var(--primary);">J</div>
          <div>
            <div class="name">Jeliard D. Almonte</div>
            <div class="detail">jealmonte@cspc.edu.ph &bull; Administrator</div>
          </div>
        </button>
      </form>

      <form method="POST" action="/login">
        @csrf
        <input type="hidden" name="email" value="bhokrealubit@my.cspc.edu.ph">
        <input type="hidden" name="password" value="student123">
        <button type="submit" class="account-option">
          <div class="avatar" style="background:var(--gray-600);">G</div>
          <div>
            <div class="name">Gil Rexis E. Realubit</div>
            <div class="detail">bhokrealubit@my.cspc.edu.ph &bull; Student</div>
          </div>
        </button>
      </form>
    </div>
  </div>
</div>

<script src="/js/main.js"></script>
<script>
  (function () {
    var modal = document.getElementById('accountModal');
    var openBtn = document.getElementById('testAccountsBtn');
    if (!modal || !openBtn) return;

    function open()  { modal.classList.add('open'); }
    function close() { modal.classList.remove('open'); }

    openBtn.addEventListener('click', open);
    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
    modal.querySelectorAll('[data-modal-close]').forEach(function (btn) {
      btn.addEventListener('click', close);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('open')) close();
    });
  })();
</script>
</body>
</html>
