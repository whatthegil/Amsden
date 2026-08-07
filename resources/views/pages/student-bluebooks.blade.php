@php $title = 'Browse Bluebooks'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <div class="main">
    <header class="topbar">
      <h1 class="topbar-title">Browse Bluebooks</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div>
          <h1>Approved Bluebooks</h1>
          <p>{{ count($bluebooks) }} research paper(s) available</p>
        </div>
      </div>

      <form method="GET" action="{{ route('student.bluebooks') }}" class="filter-bar">
        <div class="filter-group grow">
          <label>Search</label>
          <input type="text" name="search" value="{{ $query['search'] ?? '' }}" placeholder="Title, author, keyword…">
        </div>
        <div class="filter-group">
          <label>Department</label>
          <select name="department">
            <option value="">All Departments</option>
            @foreach(['CCS','CENG','CAS','CHS','CTDE','CTHM','CBA'] as $dept)
              <option value="{{ $dept }}" {{ ($query['department'] ?? '') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
            @endforeach
          </select>
        </div>
        <div class="filter-group">
          <label>Year</label>
          <select name="year">
            <option value="">All Years</option>
            @foreach($years as $y)
              <option value="{{ $y }}" {{ ($query['year'] ?? '') == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
          </select>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:flex-end;">
          <button type="submit" class="btn btn-primary btn-sm">Search</button>
          <a href="{{ route('student.bluebooks') }}" class="btn btn-outline btn-sm">Clear</a>
        </div>
      </form>

      @if(count($bluebooks) > 0)
        <div style="display:flex;flex-direction:column;gap:0.75rem;">
          @foreach($bluebooks as $b)
            <a href="{{ route('student.bluebook', $b['id']) }}" style="display:block;text-decoration:none;">
              <div class="card" style="padding:1.5rem;transition:box-shadow 0.2s;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                  <div style="flex:1;min-width:0;">
                    <h3 style="font-weight:600;font-size:1.05rem;color:var(--gray-800);margin-bottom:0.5rem;line-height:1.4;">{{ $b['title'] }}</h3>
                    <div style="font-size:0.83rem;color:var(--gray-600);margin-bottom:0.75rem;">{{ implode(' • ', $b['authors']) }}</div>
                    <div style="font-size:0.83rem;color:var(--gray-400);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $b['abstract'] }}</div>
                  </div>
                  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.5rem;flex-shrink:0;">
                    <span class="badge badge-blue">{{ $b['department'] }}</span>
                    <span style="font-size:0.78rem;color:var(--gray-400);">{{ $b['year'] }}</span>
                    <span style="font-size:0.78rem;color:var(--gray-400);">{{ $b['views'] }} views</span>
                  </div>
                </div>
                <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-top:0.75rem;">
                  @foreach(array_slice($b['keywords'], 0, 4) as $kw)
                    <span class="keyword">{{ $kw }}</span>
                  @endforeach
                </div>
              </div>
            </a>
          @endforeach
        </div>
      @else
        <div class="card">
          <div class="empty-state">
            <div class="icon">📭</div>
            <p>No bluebooks found matching your filters.</p>
          </div>
        </div>
      @endif
    </div>

    <footer style="padding:1rem 2rem;font-size:0.8rem;color:var(--gray-400);border-top:1px solid var(--gray-200);background:rgba(255,255,255,0.98);">
      AMSDEN &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </div>
</div>

@include('partials.footer')
