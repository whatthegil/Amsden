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
      {{-- The greeting and the search together. The search used to sit in a card
           of its own, below the heading and styled like every other panel, which
           left the page with nothing to look at and buried the one tool a reader
           comes here to use. --}}
      <div class="dash-hero">
        <div class="dash-hero-top">
          <div>
            <h1>Welcome, {{ explode(' ', $user['name'])[0] }}</h1>
            <p>
              {{ $totalApproved }} approved {{ Str::plural('paper', $totalApproved) }} in the CSPC archive, ready to read.
              @if($user['role'] === 'Faculty') &middot; Faculty access @endif
            </p>
          </div>
          @if($user['canUpload'] ?? false)
            <a href="{{ route('student.upload') }}" class="btn btn-sm btn-on-hero">
              <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0-12l-4 4m4-4l4 4M4 20h16"/></svg>
              Upload Bluebook
            </a>
          @endif
        </div>

        <div class="hero-search">
          <div class="field">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
            <label for="litSearch" class="sr-only">Search the archive</label>
            <input type="text" id="litSearch" autocomplete="off"
                   placeholder="Search titles, authors, keywords, abstracts…"
                   oninput="searchLiterature()">
          </div>
          <button type="button" class="btn btn-sm btn-on-hero"
                  onclick="document.getElementById('litSearch').value='';searchLiterature();">Clear</button>
        </div>
        <div class="hero-hint">Searches every approved paper, including the text inside the documents.</div>
      </div>

      {{-- Results land directly under the search that produced them. --}}
      <div class="card" id="litResults" style="display:none;margin-bottom:1.5rem;">
        <div class="card-header">
          <h3 class="card-title">Search Results</h3>
          <span style="font-size:0.82rem;color:var(--gray-400);" id="litCount"></span>
        </div>
        <div class="card-body">
          <div id="litList" style="display:flex;flex-direction:column;gap:0.5rem;max-height:340px;overflow-y:auto;"></div>
        </div>
      </div>

      {{-- The archive's own content, which was a bare three-column table. It is
           the most interesting thing on this page, so it gets the room. --}}
      <div style="margin-bottom:1.5rem;">
        <div class="section-head">
          <h2>Recently Added</h2>
          <a href="{{ route('student.bluebooks') }}">Browse all &rarr;</a>
        </div>
        @if(count($recentBluebooks) > 0)
          <div class="doc-grid">
            @foreach($recentBluebooks as $b)
              <a class="doc-card" href="{{ route('student.bluebook', $b['id']) }}">
                <span class="badge badge-blue" style="align-self:flex-start;">{{ $b['department'] }}</span>
                <div class="doc-title">{{ $b['title'] }}</div>
                <div class="doc-authors">{{ implode(', ', $b['authors']) }}</div>
                <div class="doc-foot">
                  <span>{{ $b['year'] }}</span>
                  <span class="doc-views">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    {{ $b['views'] }}
                  </span>
                </div>
              </a>
            @endforeach
          </div>
        @else
          <div class="card"><div class="empty-state">
            <div class="icon"><svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.25A9.7 9.7 0 0 0 6 4.5 9.7 9.7 0 0 0 3 5v14a9.7 9.7 0 0 1 3-.5 9.7 9.7 0 0 1 6 1.75 9.7 9.7 0 0 1 6-1.75 9.7 9.7 0 0 1 3 .5V5a9.7 9.7 0 0 0-3-.5 9.7 9.7 0 0 0-6 1.75Zm0 0V20"/></svg></div>
            <p>No papers have been approved yet. Once they are, they will appear here.</p>
          </div></div>
        @endif
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Most Read</h3>
          </div>
          @if(count($popularBluebooks) > 0)
            <div class="rank-list">
              @foreach($popularBluebooks as $i => $b)
                <a class="rank-item" href="{{ route('student.bluebook', $b['id']) }}">
                  <span class="rank-num">{{ $i + 1 }}</span>
                  <span class="rank-body">
                    <span class="t">{{ $b['title'] }}</span>
                    <span class="s">{{ $b['department'] }} &middot; {{ $b['year'] }}</span>
                  </span>
                  <span class="rank-count">{{ $b['views'] }}</span>
                </a>
              @endforeach
            </div>
          @else
            <div class="empty-state">
              <div class="icon"><svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M3 20h18M7 20v-7m5 7V7m5 13v-4"/></svg></div>
              <p>Nothing has been read yet.</p>
            </div>
          @endif
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="card-title">My Uploads</h3>
            @if(count($myBluebooks) > 0)
              <a href="{{ route('student.my-uploads') }}" style="font-size:0.82rem;color:var(--primary);">View all</a>
            @endif
          </div>
          @if(count($myBluebooks) > 0)
            <div class="table-wrap">
              <table>
                <thead><tr><th>Title</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                  @foreach(array_slice($myBluebooks, 0, 4) as $b)
                    <tr>
                      <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500;">{{ $b['title'] }}</td>
                      <td>
                        @if($b['status'] === 'Approved') <span class="badge badge-green">Approved</span>
                        @elseif($b['status'] === \App\Models\Bluebook::STATUS_AWAITING_WAIVER) <span class="badge badge-blue">Awaiting Waiver</span>
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
          @else
            {{-- An empty panel said nothing. This says what the space is for, and
                 how to fill it for a reader who is allowed to. --}}
            <div class="empty-state">
              <div class="icon"><svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M4 20h16"/></svg></div>
              <p>You have not submitted a paper yet.</p>
              @if($user['canUpload'] ?? false)
                <a href="{{ route('student.upload') }}" class="btn btn-outline btn-sm" style="margin-top:0.75rem;">Upload your first</a>
              @else
                <p style="font-size:0.78rem;color:var(--gray-400);margin-top:0.4rem;">Ask an administrator to enable uploads for your account.</p>
              @endif
            </div>
          @endif
        </div>
      </div>
    </div>

    <footer class="app-footer">
      C-BAMS &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </main>
</div>

<script>
const allApproved = {!! json_encode(array_merge($recentBluebooks, $popularBluebooks)) !!};

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

  countEl.textContent = results.length + ' result' + (results.length === 1 ? '' : 's');
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
