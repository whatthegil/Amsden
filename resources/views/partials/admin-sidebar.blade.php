<meta name="auth-user" data-name="{{ $user['name'] ?? '' }}" data-email="{{ $user['email'] ?? '' }}">
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-brand-icon">
      <img src="/images/cspc-logo.png" alt="CSPC" width="26" height="26" style="border-radius:4px;object-fit:cover;">
    </div>
    <button type="button" class="sidebar-expand-btn" aria-label="Expand sidebar" title="Expand sidebar">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="9" y1="4" x2="9" y2="20"/></svg>
    </button>
    <div class="sidebar-brand-text">
      <span class="name">C-BAMS</span>
      <span class="sub">Admin Portal</span>
    </div>
    <button type="button" class="sidebar-collapse-btn" aria-label="Collapse sidebar" title="Collapse sidebar">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="9" y1="4" x2="9" y2="20"/></svg>
    </button>
  </div>

  <div class="sidebar-search">
    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
    <input type="search" data-nav-filter aria-label="Search menu" placeholder="Search">
  </div>

  <nav class="sidebar-section" aria-label="Main">
    <span class="sidebar-label">Main</span>
    <a href="{{ route('admin.dashboard') }}" class="nav-item {{ $active === 'dashboard' ? 'active' : '' }}">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>
    <a href="{{ route('admin.bluebooks') }}" class="nav-item {{ $active === 'bluebooks' ? 'active' : '' }}">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
      Bluebooks
      @if(($pendingCount ?? 0) > 0)
        <span class="badge badge-yellow" style="margin-left:auto;">{{ $pendingCount }}<span class="sr-only"> pending</span></span>
      @endif
    </a>
  </nav>

  <nav class="sidebar-section" aria-label="Management">
    <span class="sidebar-label">Management</span>
    <a href="{{ route('admin.users') }}" class="nav-item {{ $active === 'users' ? 'active' : '' }}">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
      Users
    </a>
    <a href="{{ route('admin.logs') }}" class="nav-item {{ $active === 'logs' ? 'active' : '' }}">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Access Logs
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="{{ route('profile') }}" class="user-chip {{ ($active ?? '') === 'profile' ? 'active' : '' }}" title="View my profile">
      <div class="user-chip-avatar" aria-hidden="true">{{ strtoupper(substr($user['name'] ?? 'A', 0, 1)) }}</div>
      <div class="user-chip-info">
        <span class="name">{{ $user['name'] ?? '' }}</span>
        <span class="role">Administrator &middot; View profile</span>
      </div>
    </a>
    <a href="{{ route('logout') }}" class="nav-item" style="margin-top:0.5rem;color:var(--red);">
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Sign Out
    </a>
  </div>
</aside>
