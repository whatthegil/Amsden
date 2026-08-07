<meta name="auth-user" data-name="{{ $user['name'] ?? '' }}" data-email="{{ $user['email'] ?? '' }}">
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-brand-icon">
      <img src="/images/cspc-logo.png" alt="CSPC" width="26" height="26" style="border-radius:4px;object-fit:cover;">
    </div>
    <button type="button" class="sidebar-expand-btn" aria-label="Expand sidebar" title="Expand sidebar">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l6 6-6 6"/><path d="M13 6l6 6-6 6"/></svg>
    </button>
    <div class="sidebar-brand-text">
      <span class="name">AMSDEN</span>
      <span class="sub">CSPC Portal</span>
    </div>
  </div>

  <nav class="sidebar-section">
    <span class="sidebar-label">Main</span>
    <a href="{{ route('student.dashboard') }}" class="nav-item {{ $active === 'dashboard' ? 'active' : '' }}">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>
    <a href="{{ route('student.bluebooks') }}" class="nav-item {{ $active === 'bluebooks' ? 'active' : '' }}">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
      Browse Bluebooks
    </a>
  </nav>

  <nav class="sidebar-section">
    <span class="sidebar-label">My Work</span>
    <a href="{{ route('student.similarity-check') }}" class="nav-item {{ $active === 'similarity' ? 'active' : '' }}">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
      Similarity Check
    </a>
    <a href="{{ route('student.literature-review') }}" class="nav-item {{ $active === 'literature-review' ? 'active' : '' }}">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5a6 6 0 105.454 8.454l3.096 3.096a1 1 0 001.414-1.414l-3.096-3.096A6 6 0 0011 5zm-2 5h4m-4 3h2"/></svg>
      Literature Review
    </a>
    <a href="{{ route('student.upload') }}" class="nav-item {{ $active === 'upload' ? 'active' : '' }}">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
      Upload Bluebook
    </a>
    <a href="{{ route('student.my-uploads') }}" class="nav-item {{ $active === 'my-uploads' ? 'active' : '' }}">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      My Uploads
    </a>
    <a href="{{ route('student.bookmarks') }}" class="nav-item {{ $active === 'bookmarks' ? 'active' : '' }}">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
      Bookmarks
    </a>
    <a href="{{ route('student.history') }}" class="nav-item {{ $active === 'history' ? 'active' : '' }}">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      My History
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="{{ route('profile') }}" class="user-chip {{ ($active ?? '') === 'profile' ? 'active' : '' }}" title="View my profile">
      <div class="user-chip-avatar">{{ strtoupper(substr($user['name'] ?? 'S', 0, 1)) }}</div>
      <div class="user-chip-info">
        <span class="name">{{ $user['name'] ?? '' }}</span>
        <span class="role">{{ $user['role'] ?? 'User' }} &middot; View profile</span>
      </div>
    </a>
    <a href="{{ route('logout') }}" class="nav-item" style="margin-top:0.5rem;color:var(--red);">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Sign Out
    </a>
  </div>
</aside>
