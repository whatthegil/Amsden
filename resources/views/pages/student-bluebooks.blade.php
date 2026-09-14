@php
  $title = 'Browse Bluebooks';

  // What the list is currently narrowed to, so it can be shown as removable
  // chips. A select showing "CCS" among three other controls does not read as a
  // restriction on the results.
  $activeFilters = [];
  if (($query['search'] ?? '') !== '') {
    $activeFilters[] = ['label' => '“' . $query['search'] . '”', 'param' => 'search'];
  }
  if (($query['department'] ?? '') !== '') {
    $activeFilters[] = ['label' => $query['department'], 'param' => 'department'];
  }
  if (($query['year'] ?? '') !== '') {
    $activeFilters[] = ['label' => $query['year'], 'param' => 'year'];
  }
@endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">Browse Bluebooks</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content">
      <div class="dash-hero slim">
        <div class="dash-hero-top" style="margin-bottom:0;">
          <div>
            <h1>Browse the Archive</h1>
            <p>
              @if(count($activeFilters) > 0)
                {{ count($bluebooks) }} {{ Str::plural('paper', count($bluebooks)) }} match your filters.
              @else
                {{ count($bluebooks) }} approved {{ Str::plural('paper', count($bluebooks)) }} to read.
              @endif
            </p>
          </div>
        </div>
      </div>

      <form method="GET" action="{{ route('student.bluebooks') }}" class="filter-bar">
        <div class="filter-group grow">
          <label for="student-bluebooks-filter-search">Search</label>
          <input id="student-bluebooks-filter-search" type="text" name="search" value="{{ $query['search'] ?? '' }}" placeholder="Title, author, keyword…">
        </div>
        <div class="filter-group">
          <label for="student-bluebooks-filter-department">Department</label>
          <select id="student-bluebooks-filter-department" name="department">
            <option value="">All Departments</option>
            @foreach(config('departments') as $code => $dept)
              <option value="{{ $code }}" {{ ($query['department'] ?? '') === $code ? 'selected' : '' }}>{{ $code === $dept['name'] ? $code : "$code — {$dept['name']}" }}</option>
            @endforeach
          </select>
        </div>
        <div class="filter-group">
          <label for="student-bluebooks-filter-year">Year</label>
          <select id="student-bluebooks-filter-year" name="year">
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

      @if(count($activeFilters) > 0)
        <div class="filter-chips">
          <span class="lead">Filtered by</span>
          @foreach($activeFilters as $f)
            {{-- Drops this one term and keeps the rest. --}}
            <a class="filter-chip" href="{{ request()->fullUrlWithQuery([$f['param'] => null]) }}"
               title="Remove this filter">
              {{ $f['label'] }}
              <span class="x" aria-hidden="true">&times;</span>
              <span class="sr-only">Remove filter</span>
            </a>
          @endforeach
          @if(count($activeFilters) > 1)
            <a class="clear-all" href="{{ route('student.bluebooks') }}">Clear all</a>
          @endif
        </div>
      @endif

      @if(count($bluebooks) > 0)
        <div class="result-list">
          @foreach($bluebooks as $b)
            <a class="result-item" href="{{ route('student.bluebook', $b['id']) }}">
              <div class="result-head">
                <div class="result-main">
                  <div class="result-title">{{ $b['title'] }}</div>
                  <div class="result-authors">{{ implode(' • ', $b['authors']) }}</div>
                  <div class="result-abstract">{{ $b['abstract'] }}</div>
                </div>
                <div class="result-side">
                  <span class="badge badge-blue">{{ $b['department'] }}</span>
                  <span>{{ $b['year'] }}</span>
                  <span>{{ $b['views'] }} {{ Str::plural('view', $b['views']) }}</span>
                </div>
              </div>
              @if(count($b['keywords']) > 0)
                <div class="result-keywords">
                  @foreach(array_slice($b['keywords'], 0, 4) as $kw)
                    <span class="keyword">{{ $kw }}</span>
                  @endforeach
                </div>
              @endif
            </a>
          @endforeach
        </div>
      @else
        <div class="card">
          <div class="empty-state">
            <div class="icon">
              <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
            </div>
            {{-- An empty list has two quite different causes, and the way out of
                 one of them is not the way out of the other. --}}
            @if(count($activeFilters) > 0)
              <p>No papers match these filters.</p>
              <p style="font-size:0.8rem;color:var(--gray-400);margin-top:0.35rem;">Try removing one, or widening the year or department.</p>
              <a href="{{ route('student.bluebooks') }}" class="btn btn-outline btn-sm" style="margin-top:0.85rem;">Clear all filters</a>
            @else
              <p>No papers have been approved yet.</p>
              <p style="font-size:0.8rem;color:var(--gray-400);margin-top:0.35rem;">Approved submissions appear here as soon as they are published.</p>
            @endif
          </div>
        </div>
      @endif
    </div>

    <footer class="app-footer">
      C-BAMS &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </main>
</div>

@include('partials.footer')
