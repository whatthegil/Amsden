@php $title = 'Upload Denied'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">Upload Bluebook</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content" style="display:flex;align-items:center;justify-content:center;min-height:calc(100vh - var(--header-h) - 4rem);">
      <div class="card" style="max-width:500px;padding:3rem;text-align:center;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" style="margin:0 auto 1rem;display:block;"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zM8 11V7a4 4 0 118 0v4" fill="var(--gray-400)" fill-opacity="0.18"/><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zM8 11V7a4 4 0 118 0v4" fill="none" stroke="var(--gray-400)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
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

    <footer class="app-footer">
      C-BAMS &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </main>
</div>

@include('partials.footer')
