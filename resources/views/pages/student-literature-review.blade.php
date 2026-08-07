@php $title = 'Literature Review'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <div class="main">
    <header class="topbar">
      <h1 class="topbar-title">Literature Review</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content form-page">
      <div class="page-header">
        <div>
          <h1>Literature Review Assistant</h1>
          <p>Enter a research topic to find related bluebooks in the archive, each with an AI-written key-points summary grounded in the paper's actual content.</p>
        </div>
      </div>

      @if(session('error'))
        <div class="alert alert-error" style="margin-bottom:1.25rem;">{{ session('error') }}</div>
      @endif

      {{-- Input Form --}}
      <div class="form-card">
        <div class="form-card-header">
          <span class="icon">📚</span>
          <h2>Research Topic</h2>
        </div>
        <div class="form-card-body">
          <form method="POST" action="{{ route('student.literature-review.post') }}">
            @csrf

            <div class="form-group">
              <label>Topic / Research Question <span style="color:var(--red);">*</span></label>
              <input
                type="text"
                name="topic"
                required
                placeholder="e.g. Web-based attendance monitoring using QR codes"
                value="{{ old('topic', $topic ?? '') }}"
              >
              <small style="color:var(--gray-400);font-size:0.8rem;">Describe the subject you're reviewing literature for. Results are matched against titles, abstracts, and keywords in the approved archive.</small>
            </div>

            <div style="background:var(--primary-light);border:1px solid var(--primary-pale);border-radius:var(--radius-sm);padding:0.875rem 1rem;margin-bottom:1.25rem;font-size:0.85rem;color:var(--primary-dark);">
              <strong>How this works:</strong> Bluebooks are ranked by term overlap with your topic. The top matches get an AI-written summary drawn from the paper's title, abstract, and uploaded document text — the rest use an automatically extracted summary of the abstract.
            </div>

            <div class="form-actions">
              <button type="reset" class="btn btn-outline">Clear</button>
              <button type="submit" class="btn btn-primary">Search Literature</button>
            </div>
          </form>
        </div>
      </div>

      {{-- Results Section --}}
      @if($results !== null)
        @php $total = count($results); @endphp

        @if($total === 0)
          <div class="alert alert-error" style="margin-top:1.5rem;display:flex;align-items:center;gap:0.75rem;">
            <span style="font-size:1.4rem;">🔍</span>
            <div>
              <strong>No related bluebooks found.</strong><br>
              <span style="font-size:0.88rem;">Try broadening your topic or using different keywords.</span>
            </div>
          </div>
        @else
          <div class="alert alert-success" style="margin-top:1.5rem;display:flex;align-items:center;gap:0.75rem;">
            <span style="font-size:1.4rem;">✅</span>
            <div>
              <strong>{{ $total }} related bluebook{{ $total !== 1 ? 's' : '' }} found for: <em>"{{ $topic }}"</em></strong><br>
              <span style="font-size:0.88rem;">Sorted by relevance to your topic.</span>
            </div>
          </div>

          <div style="display:flex;flex-direction:column;gap:1rem;margin-top:1.25rem;">
            @foreach($results as $result)
              @php
                $book = $result['bluebook'];
                $pct  = $result['relevance'];
                if ($pct >= 60) {
                  $badgeBg = 'var(--green-light)'; $badgeColor = 'var(--green)'; $barColor = 'var(--green)';
                } elseif ($pct >= 30) {
                  $badgeBg = 'var(--yellow-light)'; $badgeColor = 'var(--yellow)'; $barColor = '#f6c90e';
                } else {
                  $badgeBg = 'var(--primary-pale)'; $badgeColor = 'var(--primary-dark)'; $barColor = 'var(--primary)';
                }
              @endphp
              <div class="card">
                <div class="card-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;">
                  <div>
                    <a href="{{ route('student.bluebook', ['id' => $book['id']]) }}"
                       style="color:var(--primary);font-weight:600;font-size:1rem;"
                       target="_blank">
                      {{ $book['title'] }}
                    </a>
                    <div style="font-size:0.84rem;color:var(--gray-600);margin-top:0.3rem;">
                      {{ implode(', ', array_slice($book['authors'], 0, 2)) }}{{ count($book['authors']) > 2 ? ' et al.' : '' }}
                      &middot; <span class="badge badge-default">{{ $book['department'] }}</span>
                      &middot; {{ $book['year'] }}
                    </div>
                  </div>
                  <div style="display:flex;flex-direction:column;align-items:center;gap:0.3rem;flex-shrink:0;">
                    <span style="background:{{ $badgeBg }};color:{{ $badgeColor }};padding:0.2rem 0.6rem;border-radius:999px;font-size:0.78rem;font-weight:600;white-space:nowrap;">
                      {{ $pct }}% relevant
                    </span>
                    <div style="width:80px;height:5px;background:var(--gray-200);border-radius:99px;overflow:hidden;">
                      <div style="height:100%;width:{{ min($pct, 100) }}%;background:{{ $barColor }};border-radius:99px;"></div>
                    </div>
                  </div>
                </div>
                <div class="card-body">
                  @if(!empty($book['keywords']))
                    <div style="display:flex;flex-wrap:wrap;gap:0.25rem;margin-bottom:0.75rem;">
                      @foreach(array_slice($book['keywords'], 0, 6) as $kw)
                        <span style="font-size:0.72rem;background:var(--primary-light);color:var(--primary-dark);padding:0.1rem 0.45rem;border-radius:999px;">{{ $kw }}</span>
                      @endforeach
                    </div>
                  @endif
                  <div style="background:var(--cream);border:1px solid var(--gray-200);border-radius:var(--radius-sm);padding:0.75rem 1rem;">
                    <div style="font-size:0.78rem;font-weight:600;color:var(--gray-600);margin-bottom:0.3rem;display:flex;align-items:center;gap:0.4rem;">
                      @if($result['aiGenerated'] ?? false)
                        <span style="background:var(--primary);color:var(--white);padding:0.05rem 0.5rem;border-radius:999px;font-size:0.68rem;letter-spacing:0.03em;">✨ AI Summary</span>
                      @else
                        <span>🧠</span> Key Points Summary
                      @endif
                    </div>
                    <div style="font-size:0.88rem;color:var(--gray-800);line-height:1.5;">
                      {{ $result['summary'] }}
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        @endif
      @endif
    </div>

    <footer style="padding:1rem 2rem;font-size:0.8rem;color:var(--gray-400);border-top:1px solid var(--gray-200);background:rgba(255,255,255,0.98);">
      AMSDEN &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </div>
</div>

@include('partials.footer')
