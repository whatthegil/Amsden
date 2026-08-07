@php $title = 'My Bookmarks'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <div class="main">
    <header class="topbar">
      <h1 class="topbar-title">My Bookmarks</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div>
          <h1>Bookmarks</h1>
          <p>{{ count($bluebooks) }} saved research paper(s)</p>
        </div>
      </div>

      @if(count($bluebooks) > 0)
        <div style="display:flex;flex-direction:column;gap:0.75rem;">
          @foreach($bluebooks as $b)
            <div class="card" style="padding:1.5rem;">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                <div style="flex:1;min-width:0;">
                  <a href="{{ route('student.bluebook', $b['id']) }}" style="text-decoration:none;">
                    <h3 style="font-weight:600;font-size:1.05rem;color:var(--gray-800);margin-bottom:0.4rem;line-height:1.4;">{{ $b['title'] }}</h3>
                  </a>
                  <div style="font-size:0.83rem;color:var(--gray-600);margin-bottom:0.4rem;">{{ implode(', ', $b['authors']) }}</div>
                  <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                    <span class="badge badge-blue">{{ $b['department'] }}</span>
                    <span style="font-size:0.78rem;color:var(--gray-400);">{{ $b['year'] }}</span>
                    <span style="font-size:0.78rem;color:var(--gray-400);">{{ $b['views'] }} views</span>
                  </div>
                </div>
                <div style="display:flex;gap:0.5rem;flex-shrink:0;">
                  <a href="{{ route('student.bluebook', $b['id']) }}" class="btn btn-outline btn-sm">View</a>
                  <form method="POST" action="{{ route('student.bookmarks.remove', $b['id']) }}">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                  </form>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      @else
        <div class="card">
          <div class="empty-state">
            <div class="icon">🔖</div>
            <p>You haven't bookmarked any bluebooks yet.</p>
            <a href="{{ route('student.bluebooks') }}" class="btn btn-primary" style="margin-top:1rem;">Browse Bluebooks</a>
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
