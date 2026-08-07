@php $title = 'Sign In'; @endphp
@include('partials.head')

<div class="auth-page">
  <div class="auth-art">
    <div class="auth-art-logo">
      <img src="/images/cspc-logo.png" alt="CSPC" width="42" height="42" style="border-radius:8px;">
      <span>CSPC — Bluebook Archive</span>
    </div>
    <h1>Archive Management<br>System for<br><span>CSPC Bluebooks</span></h1>
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
      <a href="{{ route('auth.google') }}" class="btn btn-full" style="background:#fff;border:1.5px solid #dadce0;color:#3c4043;font-weight:500;margin-bottom:1rem;justify-content:center;gap:0.65rem;box-shadow:0 1px 3px rgba(0,0,0,0.1);text-decoration:none;">
        <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.36-8.16 2.36-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/><path fill="none" d="M0 0h48v48H0z"/></svg>
        Continue with CSPC Google Account
      </a>

      <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
        <div style="flex:1;height:1px;background:var(--gray-200);"></div>
        <span style="font-size:0.78rem;color:var(--gray-400);font-weight:500;">or sign in with email</span>
        <div style="flex:1;height:1px;background:var(--gray-200);"></div>
      </div>

      <form method="POST" action="/login">
        @csrf
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="yourname@cspc.edu.ph" required autofocus>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full" style="margin-bottom:1rem;">Sign In</button>
      </form>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
        <span style="font-size:0.85rem;color:var(--gray-400);">
          Don't have an account?
          <a href="{{ route('register') }}" style="color:var(--primary);font-weight:600;">Register here</a>
        </span>
        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('accountModal').style.display='flex'">
          Test Accounts
        </button>
      </div>

      <div style="padding:0.85rem 1rem;background:var(--primary-light);border-radius:8px;border:1px solid var(--primary-pale);">
        <p style="font-size:0.75rem;color:var(--primary-dark);font-weight:700;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.5rem;">Quick Test</p>
        <div style="font-size:0.81rem;color:var(--gray-600);">
          <div style="margin-bottom:0.3rem;"><strong>Admin:</strong> jealmonte@cspc.edu.ph / admin123</div>
          <div><strong>Student:</strong> bhokrealubit@my.cspc.edu.ph / student123</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Account Picker Modal -->
<div id="accountModal" style="display:none;position:fixed;inset:0;background:rgba(5,15,40,0.92);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(8px);">
  <div style="background:rgba(255,255,255,0.97);border-radius:16px;padding:2.5rem;width:90%;max-width:500px;box-shadow:0 24px 80px rgba(0,0,0,0.5);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
      <h3 style="font-weight:700;letter-spacing:-0.02em;color:var(--gray-800);font-size:1.2rem;">Select Test Account</h3>
      <button onclick="document.getElementById('accountModal').style.display='none'" style="border:none;background:none;cursor:pointer;font-size:1.4rem;color:var(--gray-400);">&times;</button>
    </div>

    <div style="display:flex;flex-direction:column;gap:1rem;">
      <form method="POST" action="/login">
        @csrf
        <input type="hidden" name="email" value="jealmonte@cspc.edu.ph">
        <input type="hidden" name="password" value="admin123">
        <button type="submit" style="width:100%;text-align:left;padding:1.25rem;border:2px solid var(--primary-pale);border-radius:10px;background:var(--primary-light);cursor:pointer;">
          <div style="display:flex;align-items:center;gap:1rem;">
            <div style="width:42px;height:42px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1.1rem;">J</div>
            <div>
              <div style="font-weight:600;color:var(--primary-dark);">Jeliard D. Almonte</div>
              <div style="font-size:0.8rem;color:var(--gray-600);">jealmonte@cspc.edu.ph &bull; Administrator</div>
            </div>
          </div>
        </button>
      </form>

      <form method="POST" action="/login">
        @csrf
        <input type="hidden" name="email" value="bhokrealubit@my.cspc.edu.ph">
        <input type="hidden" name="password" value="student123">
        <button type="submit" style="width:100%;text-align:left;padding:1.25rem;border:2px solid var(--gray-200);border-radius:10px;background:var(--cream);cursor:pointer;">
          <div style="display:flex;align-items:center;gap:1rem;">
            <div style="width:42px;height:42px;border-radius:50%;background:var(--gray-600);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1.1rem;">G</div>
            <div>
              <div style="font-weight:600;color:var(--gray-800);">Gil Rexis E. Realubit</div>
              <div style="font-size:0.8rem;color:var(--gray-600);">bhokrealubit@my.cspc.edu.ph &bull; Student</div>
            </div>
          </div>
        </button>
      </form>
    </div>
  </div>
</div>

<script src="/js/main.js"></script>
</body>
</html>
