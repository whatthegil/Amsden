@php $title = 'My Bookmarks'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">My Bookmarks</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content">
      <div class="dash-hero slim">
        <div class="dash-hero-top" style="margin-bottom:0;">
          <div>
            <h1>Saved Papers</h1>
            <p>
              @if(count($bluebooks) > 0)
                {{ count($bluebooks) }} {{ Str::plural('paper', count($bluebooks)) }} saved to read later, newest first.
              @else
                Nothing saved yet.
              @endif
            </p>
          </div>
          @if(count($bluebooks) > 0)
            <a href="{{ route('student.bluebooks') }}" class="btn btn-sm btn-on-hero">
              <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
              Find more
            </a>
          @endif
        </div>
      </div>

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif

      @if(count($bluebooks) > 0)
        <div class="result-list">
          @foreach($bluebooks as $b)
            {{-- The same row the browse page uses, since it is the same thing: a
                 paper described well enough to decide whether to open it. It
                 holds a form, so it cannot be one anchor - the title links. --}}
            <div class="result-item">
              <div class="result-head">
                <div class="result-main">
                  <a class="result-title" href="{{ route('student.bluebook', $b['id']) }}">{{ $b['title'] }}</a>
                  <div class="result-authors">{{ implode(' • ', $b['authors']) }}</div>
                  {{-- The abstract was not shown at all, so a saved list was a
                       column of titles and no way to tell why any of them had
                       been saved. --}}
                  <div class="result-abstract">{{ $b['abstract'] }}</div>
                </div>
                <div class="result-side">
                  <span class="badge badge-blue">{{ $b['department'] }}</span>
                  <span>{{ $b['year'] }}</span>
                  <span>{{ $b['views'] }} {{ Str::plural('view', $b['views']) }}</span>
                </div>
              </div>

              <div class="saved-actions">
                <a href="{{ route('student.bluebook', $b['id']) }}" class="btn btn-primary btn-sm">Read</a>
                <form method="POST" action="{{ route('student.bookmarks.remove', $b['id']) }}">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-quiet">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/><path stroke-linecap="round" d="m4 4 16 16"/></svg>
                    Remove
                  </button>
                </form>
                @if(!empty($b['bookmarkedAt']))
                  <span class="saved-when">Saved {{ $b['bookmarkedAt'] }}</span>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      @else
        <div class="card">
          <div class="empty-state">
            <div class="icon">
              <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
            </div>
            <p>You have not saved any papers yet.</p>
            <p style="font-size:0.8rem;color:var(--gray-400);margin-top:0.35rem;">Bookmark a paper while reading it and it will be waiting here.</p>
            <a href="{{ route('student.bluebooks') }}" class="btn btn-primary btn-sm" style="margin-top:0.9rem;">Browse the archive</a>
          </div>
        </div>
      @endif
    </div>

    <footer class="app-footer">
      C-BAMS &copy; {{ date('Y') }} &mdash; Camarines Sur Polytechnic Colleges. All Rights Reserved.
    </footer>
  </main>
</div>

@include('partials.footer')
