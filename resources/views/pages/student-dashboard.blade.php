@php $title = 'Dashboard'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">Dashboard</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
        <span class="topbar-meta">{{ $user['email'] }}</span>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div>
          <h1>Welcome, {{ explode(' ', $user['name'])[0] }}</h1>
          <p>Explore {{ $totalApproved }} approved research papers in the CSPC archive.
            @if($user['role'] === 'Faculty') &mdash; <span style="font-size:0.82rem;color:var(--primary);">Faculty</span> @endif
          </p>
        </div>
        @if($user['canUpload'] ?? false)
          <a href="{{ route('student.upload') }}" class="btn btn-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            Upload Bluebook
          </a>
        @endif
      </div>

      @php
        $myApprovedCount = count(array_filter($myBluebooks, fn($b) => $b['status'] === 'Approved'));
        $myPendingCount  = count(array_filter($myBluebooks, fn($b) => $b['status'] === 'Pending'));
      @endphp
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" fill="currentColor" fill-opacity="0.18"/><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
          <div class="stat-body">
            <div class="value">{{ $totalApproved }}</div>
            <div class="label">Approved in Archive</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" fill="currentColor" fill-opacity="0.18"/><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
          <div class="stat-body">
            <div class="value">{{ count($myBluebooks) }}</div>
            <div class="label">My Uploads</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" fill="currentColor" fill-opacity="0.18"/><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
          <div class="stat-body">
            <div class="value" style="color:var(--green)">{{ $myApprovedCount }}</div>
            <div class="label">My Approved</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon yellow"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" fill="currentColor" fill-opacity="0.18"/><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
          <div class="stat-body">
            <div class="value" style="color:var(--yellow)">{{ $myPendingCount }}</div>
            <div class="label">My Pending Review</div>
          </div>
        </div>
      </div>

      <!-- Literature Review Search -->
      <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
          <h3 class="card-title">Literature Review Search</h3>
        </div>
        <div class="card-body">
          <div style="display:flex;gap:0.75rem;margin-bottom:1rem;flex-wrap:wrap;">
            <input type="text" id="litSearch" placeholder="Search titles, authors, keywords, abstracts…" style="flex:1;min-width:240px;padding:0.65rem 1rem;border:1.5px solid var(--gray-200);border-radius:var(--radius-sm);font-size:0.9rem;background:var(--cream);" oninput="searchLiterature()">
            <button class="btn btn-outline btn-sm" onclick="document.getElementById('litSearch').value='';searchLiterature();">Clear</button>
          </div>
          <div id="litResults" style="display:none;">
            <div style="font-size:0.82rem;color:var(--gray-400);margin-bottom:0.75rem;" id="litCount"></div>
            <div id="litList" style="display:flex;flex-direction:column;gap:0.5rem;max-height:340px;overflow-y:auto;"></div>
          </div>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Recently Added</h3>
            <a href="{{ route('student.bluebooks') }}" style="font-size:0.82rem;color:var(--primary);">Browse all</a>
          </div>
          @if(count($recentBluebooks) > 0)
            <div class="table-wrap">
              <table>
                <thead><tr><th>Title</th><th>Year</th><th>Dept</th></tr></thead>
                <tbody>
                  @foreach($recentBluebooks as $b)
                    <tr data-href="{{ route('student.bluebook', $b['id']) }}" style="cursor:pointer;">
                      <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500;">{{ $b['title'] }}</td>
                      <td>{{ $b['year'] }}</td>
                      <td><span class="badge badge-blue">{{ $b['department'] }}</span></td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <div class="empty-state"><div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" fill="currentColor" fill-opacity="0.18"/><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><p>No bluebooks yet</p></div>
          @endif
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Most Popular</h3>
          </div>
          @if(count($popularBluebooks) > 0)
            <div class="table-wrap">
              <table>
                <thead><tr><th>Title</th><th>Views</th></tr></thead>
                <tbody>
                  @foreach($popularBluebooks as $b)
                    <tr data-href="{{ route('student.bluebook', $b['id']) }}" style="cursor:pointer;">
                      <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500;">{{ $b['title'] }}</td>
                      <td style="font-weight:600;color:var(--primary-dark);">{{ $b['views'] }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <div class="empty-state"><div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" fill="currentColor" fill-opacity="0.18"/><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><p>No views yet</p></div>
          @endif
        </div>
      </div>

      @if(count($myBluebooks) > 0)
        <div class="card" style="margin-top:1.5rem;">
          <div class="card-header">
            <h3 class="card-title">My Uploads</h3>
            <a href="{{ route('student.my-uploads') }}" style="font-size:0.82rem;color:var(--primary);">View all</a>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Title</th><th>Status</th><th>Date</th></tr></thead>
              <tbody>
                @foreach(array_slice($myBluebooks, 0, 4) as $b)
                  <tr>
                    <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500;">{{ $b['title'] }}</td>
                    <td>
                      @if($b['status'] === 'Approved') <span class="badge badge-green">Approved</span>
                      @elseif($b['status'] === 'Pending') <span class="badge badge-yellow">Pending</span>
                      @else <span class="badge badge-red">Rejected</span>
                      @endif
                    </td>
                    <td style="font-size:0.83rem;">{{ $b['dateAdded'] }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif
    </div>

    <footer class="app-footer">
      C-BAMS &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </main>
</div>

<script>
const allBluebooks = {!! json_encode($recentBluebooks) !!};
const allApproved  = {!! json_encode(array_merge($recentBluebooks, $popularBluebooks)) !!};

function highlight(text, query) {
  if (!query) return text;
  const re = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
  return text.replace(re, '<mark style="background:#fef08a;border-radius:2px;">$1</mark>');
}

function searchLiterature() {
  const q = document.getElementById('litSearch').value.trim().toLowerCase();
  const container = document.getElementById('litResults');
  const list = document.getElementById('litList');
  const countEl = document.getElementById('litCount');

  if (!q) { container.style.display = 'none'; return; }
  container.style.display = 'block';

  const results = allApproved.filter((b, i, arr) =>
    arr.findIndex(x => x.id === b.id) === i && (
      b.title.toLowerCase().includes(q) ||
      b.authors.some(a => a.toLowerCase().includes(q)) ||
      b.keywords.some(k => k.toLowerCase().includes(q)) ||
      b.abstract.toLowerCase().includes(q)
    )
  );

  countEl.textContent = results.length + ' result(s) found';
  list.innerHTML = results.length === 0
    ? '<p style="color:var(--gray-400);font-size:0.88rem;text-align:center;padding:1rem;">No results found for "' + q + '"</p>'
    : results.map(b => `
      <a href="/student/bluebooks/${b.id}" style="display:block;padding:0.85rem 1rem;border:1px solid var(--gray-200);border-radius:var(--radius-sm);background:var(--cream);text-decoration:none;transition:all 0.15s;">
        <div style="font-weight:600;color:var(--primary-dark);margin-bottom:0.3rem;">${highlight(b.title, q)}</div>
        <div style="font-size:0.8rem;color:var(--gray-600);">${b.authors.join(', ')} &bull; ${b.year} &bull; ${b.department}</div>
        <div style="font-size:0.8rem;color:var(--gray-400);margin-top:0.2rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${highlight(b.abstract, q)}</div>
      </a>
    `).join('');
}
</script>

@include('partials.footer')
