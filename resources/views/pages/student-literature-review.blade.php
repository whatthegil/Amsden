@php $title = 'Literature Review'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">Literature Review</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content form-page">
      <x-page-hero heading="Literature Review Assistant"
                   sub="Enter a research topic to find related bluebooks, each with an AI-written summary grounded in the paper's own text." />

      @if(session('error'))
        <div class="alert alert-error" style="margin-bottom:1.25rem;">{{ session('error') }}</div>
      @endif

      {{-- Input Form --}}
      <div class="form-card">
        <div class="form-card-header">
          <span class="icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" fill="currentColor" fill-opacity="0.18"/><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <h2>Research Topic</h2>
        </div>
        <div class="form-card-body">
          <form method="POST" action="{{ route('student.literature-review.post') }}">
            @csrf

            <div class="form-group">
              <label for="litrev-topic">Topic / Research Question <span style="color:var(--red);">*</span></label>
              <input
                id="litrev-topic"
                type="text"
                name="topic"
                required
                placeholder="e.g. Web-based attendance monitoring using QR codes"
                value="{{ old('topic', $topic ?? '') }}"
              >
              <small style="color:var(--gray-400);font-size:0.8rem;">Describe the subject you're reviewing literature for, in a phrase or a sentence. Words can be in any order, and small misspellings are forgiven.</small>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="litrev-within">Published</label>
                <select id="litrev-within" name="within">
                  <option value="" @selected(!($within ?? null))>Any year</option>
                  <option value="5" @selected(($within ?? null) === 5)>Within the last 5 years</option>
                  <option value="10" @selected(($within ?? null) === 10)>Within the last 10 years</option>
                </select>
              </div>
              <div class="form-group">
                <label for="litrev-sort">Sort by</label>
                <select id="litrev-sort" name="sort">
                  <option value="relevance" @selected(($sort ?? 'relevance') === 'relevance')>Most relevant first</option>
                  <option value="newest" @selected(($sort ?? '') === 'newest')>Newest first</option>
                </select>
              </div>
            </div>

            <div style="background:var(--primary-light);border:1px solid var(--primary-pale);border-radius:var(--radius-sm);padding:0.875rem 1rem;margin-bottom:1.25rem;font-size:0.85rem;color:var(--primary-dark);">
              <strong>What you get:</strong> the related bluebooks in the archive, each with a summary of how it bears on your topic and an APA 7th edition citation to copy into your references, plus a draft synthesis paragraph you can adapt for your Review of Related Literature. Summaries and the synthesis are written by AI from each paper's own text; check them against the papers before using them.
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
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" style="flex-shrink:0;"><path d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" fill="currentColor" fill-opacity="0.18"/><path d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <div>
              <strong>No related bluebooks found.</strong><br>
              <span style="font-size:0.88rem;">Try broadening your topic or using different keywords.</span>
            </div>
          </div>
        @else
          <div class="alert alert-success" style="margin-top:1.5rem;display:flex;align-items:center;gap:0.75rem;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" style="flex-shrink:0;"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" fill="currentColor" fill-opacity="0.18"/><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <div>
              <strong>{{ $total }} related bluebook{{ $total !== 1 ? 's' : '' }} found for: <em>"{{ $topic }}"</em></strong><br>
              <span style="font-size:0.88rem;">
                {{ ($sort ?? 'relevance') === 'newest' ? 'Newest first' : 'Most relevant first' }}{{ ($within ?? null) ? ', published within the last ' . $within . ' years' : '' }}.
              </span>
            </div>
          </div>

          @php
            // The reference list: every result's citation, alphabetical, as APA wants.
            $references = collect($results)->pluck('citation.text')->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
          @endphp

          @if(!empty($overview))
            <div class="card" style="margin-top:1.25rem;">
              <div class="card-header">
                <div class="card-title">Draft synthesis for your Review of Related Literature</div>
                <button type="button" class="btn btn-outline btn-sm" data-copy="{{ $overview }}">Copy</button>
              </div>
              <div class="card-body" style="font-size:0.92rem;line-height:1.65;color:var(--gray-800);">
                <p>{{ $overview }}</p>
                <p style="font-size:0.78rem;color:var(--gray-400);margin-top:0.75rem;">Written by AI from the papers below. Rewrite it in your own words and check every claim against the papers before you use it.</p>
              </div>
            </div>
          @endif

          <div style="display:flex;justify-content:flex-end;margin-top:1rem;">
            <button type="button" class="btn btn-outline btn-sm" data-copy="{{ implode("\n\n", $references) }}">Copy all {{ $total }} citations (APA 7)</button>
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
                        <span style="background:var(--primary);color:var(--white);padding:0.05rem 0.5rem;border-radius:999px;font-size:0.68rem;letter-spacing:0.03em;">AI Summary</span>
                      @else
                        Key Points Summary
                      @endif
                    </div>
                    <div style="font-size:0.88rem;color:var(--gray-800);line-height:1.5;">
                      {{ $result['summary'] }}
                    </div>
                  </div>

                  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--gray-100);">
                    <div style="font-size:0.84rem;color:var(--gray-600);line-height:1.5;">
                      <span style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--gray-400);display:block;margin-bottom:0.15rem;">
                        APA 7 citation &middot; in text: {{ $result['citation']['inText'] }}
                      </span>
                      {!! $result['citation']['html'] !!}
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" style="flex-shrink:0;" data-copy="{{ $result['citation']['text'] }}">Copy</button>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        @endif
      @endif
    </div>

    <footer class="app-footer">
      C-BAMS &copy; {{ date('Y') }} &mdash; Camarines Sur Polytechnic Colleges. All Rights Reserved.
    </footer>
  </main>
</div>

@include('partials.footer')
