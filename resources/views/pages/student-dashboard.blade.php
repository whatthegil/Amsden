@php $title = 'Dashboard'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">Dashboard</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
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
          <div class="stat-icon blue"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M3.375 3C2.339 3 1.5 3.84 1.5 4.875v.75c0 1.036.84 1.875 1.875 1.875h17.25c1.035 0 1.875-.84 1.875-1.875v-.75C22.5 3.839 21.66 3 20.625 3H3.375Z"/><path fill-rule="evenodd" d="m3.087 9 .54 9.176A3 3 0 0 0 6.62 21h10.757a3 3 0 0 0 2.995-2.824L20.913 9H3.087Zm6.163 3.75A.75.75 0 0 1 10 12h4a.75.75 0 0 1 0 1.5h-4a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/></svg></div>
          <div class="stat-body">
            <div class="value">{{ $totalApproved }}</div>
            <div class="label">Approved in Archive</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M11.47 2.47a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 0 1-1.06 1.06l-3.22-3.22V16.5a.75.75 0 0 1-1.5 0V4.81L8.03 8.03a.75.75 0 0 1-1.06-1.06l4.5-4.5ZM3 15.75a.75.75 0 0 1 .75.75v2.25a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5V16.5a.75.75 0 0 1 1.5 0v2.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V16.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd"/></svg></div>
          <div class="stat-body">
            <div class="value">{{ count($myBluebooks) }}</div>
            <div class="label">My Uploads</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd"/></svg></div>
          <div class="stat-body">
            <div class="value" style="color:var(--green)">{{ $myApprovedCount }}</div>
            <div class="label">My Approved</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon yellow"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z" clip-rule="evenodd"/></svg></div>
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
