@php $title = 'Upload Denied'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <div class="main">
    <header class="topbar">
      <h1 class="topbar-title">Upload Bluebook</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content" style="display:flex;align-items:center;justify-content:center;min-height:calc(100vh - var(--header-h) - 4rem);">
      <div class="card" style="max-width:500px;padding:3rem;text-align:center;">
        <div style="font-size:3rem;margin-bottom:1rem;">🔒</div>
        <h2 style="font-weight:700;letter-spacing:-0.02em;color:var(--gray-800);margin-bottom:0.75rem;font-size:1.4rem;">Upload Permission Required</h2>
        <p style="color:var(--gray-600);font-size:0.92rem;line-height:1.7;margin-bottom:1.5rem;">
          Your account does not have permission to upload bluebooks.<br>
          Please contact your administrator to request upload access.
        </p>
        <div style="display:flex;gap:0.75rem;justify-content:center;">
          <a href="{{ route('student.dashboard') }}" class="btn btn-primary">Go to Dashboard</a>
          <a href="{{ route('student.bluebooks') }}" class="btn btn-outline">Browse Bluebooks</a>
        </div>
      </div>
    </div>

    <footer style="padding:1rem 2rem;font-size:0.8rem;color:var(--gray-400);border-top:1px solid var(--gray-200);background:rgba(255,255,255,0.98);">
      AMSDEN &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </div>
</div>

@include('partials.footer')
