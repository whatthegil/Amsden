@php $title = $bluebook['title']; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <div class="main">
    <header class="topbar">
      <h1 class="topbar-title">Bluebook Detail</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content">
      <div style="margin-bottom:1.25rem;">
        <a href="{{ route('student.bluebooks') }}" class="btn btn-outline btn-sm">← Back to Browse</a>
      </div>

      <div class="bluebook-detail" id="bluebook-detail" data-bluebook-id="{{ $bluebook['id'] }}">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1rem;flex-wrap:wrap;">
          <h1>{{ $bluebook['title'] }}</h1>
          <div style="flex-shrink:0;">
            @if($isBookmarked)
              <form method="POST" action="{{ route('student.bookmarks.remove', $bluebook['id']) }}?from=view">
                @csrf
                <button type="submit" class="btn btn-warning btn-sm">
                  🔖 Remove Bookmark
                </button>
              </form>
            @else
              <form method="POST" action="{{ route('student.bookmarks.add', $bluebook['id']) }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">
                  🔖 Bookmark
                </button>
              </form>
            @endif
          </div>
        </div>

        <div class="bluebook-meta">
          <span class="meta-pill">{{ $bluebook['department'] }}</span>
          <span class="meta-pill">{{ $bluebook['year'] }}</span>
          <span class="meta-pill">{{ $bluebook['pages'] }} pages</span>
          <span class="meta-pill">{{ $bluebook['views'] }} views</span>
          <span class="meta-pill badge-green">Approved</span>
        </div>

        <div style="font-size:0.9rem;color:var(--gray-600);margin-bottom:1.5rem;">
          <strong>Authors:</strong> {{ implode(', ', $bluebook['authors']) }}
        </div>

        <h4 style="font-size:0.82rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:0.6rem;">Abstract</h4>
        <div class="bluebook-abstract">{{ $bluebook['abstract'] }}</div>

        <h4 style="font-size:0.82rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:0.6rem;">Keywords</h4>
        <div class="keywords">
          @foreach($bluebook['keywords'] as $kw)
            <span class="keyword">{{ $kw }}</span>
          @endforeach
        </div>

        <div class="info-grid">
          <div class="info-row">
            <span class="key">Program</span>
            <span class="val">{{ $bluebook['program'] }}</span>
          </div>
          <div class="info-row">
            <span class="key">Adviser</span>
            <span class="val">{{ $bluebook['adviser'] }}</span>
          </div>
          <div class="info-row">
            <span class="key">Uploaded By</span>
            <span class="val">{{ $bluebook['uploadedByName'] }}</span>
          </div>
          <div class="info-row">
            <span class="key">Date Added</span>
            <span class="val">{{ $bluebook['dateAdded'] }}</span>
          </div>
        </div>

        <h4 style="font-size:0.82rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:0.6rem;">Document</h4>
        @if($bluebook['hasFile'])
          <div class="watermark-overlay" style="margin-top:0;padding:0;overflow:hidden;">
            <iframe src="{{ route('student.bluebook.file', $bluebook['id']) }}#toolbar=0"
                    style="width:100%;height:75vh;border:0;display:block;"
                    title="{{ $bluebook['title'] }}"></iframe>
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;opacity:0.08;font-size:2.5rem;font-weight:700;letter-spacing:0.2em;transform:rotate(-25deg);color:var(--primary-dark);user-select:none;">CSPC ARCHIVE</div>
          </div>
          <div style="font-size:0.78rem;color:var(--gray-400);margin-top:0.5rem;">Access logged for: {{ $user['email'] }}</div>
        @else
          <div class="watermark-overlay" style="margin-top:0;">
            <div style="font-size:2rem;margin-bottom:1rem;">📄</div>
            <div style="font-size:0.9rem;color:var(--gray-600);margin-bottom:0.5rem;">No document file was attached to this record.</div>
            <div style="font-size:0.78rem;color:var(--gray-400);">Access logged for: {{ $user['email'] }}</div>
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;opacity:0.08;font-size:2.5rem;font-weight:700;letter-spacing:0.2em;transform:rotate(-25deg);color:var(--primary-dark);user-select:none;">CSPC ARCHIVE</div>
          </div>
        @endif
      </div>
    </div>

    <footer style="padding:1rem 2rem;font-size:0.8rem;color:var(--gray-400);border-top:1px solid var(--gray-200);background:rgba(255,255,255,0.98);">
      AMSDEN &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </div>
</div>

@include('partials.footer')
