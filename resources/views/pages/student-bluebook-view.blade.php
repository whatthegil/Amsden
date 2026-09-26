@php
  $title = $bluebook['title'];
  // The same page serves the admin, who reads any bluebook at any stage and all
  // of it, from the admin side of the app.
  $asAdmin = $asAdmin ?? false;
@endphp
@include('partials.head')

<div class="app">
  @include($asAdmin ? 'partials.admin-sidebar' : 'partials.student-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">Bluebook Detail</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content">
      <div style="margin-bottom:1.25rem;display:flex;gap:0.5rem;">
        @if($asAdmin)
          <a href="{{ route('admin.bluebooks') }}" class="btn btn-outline btn-sm">← Back to Bluebooks</a>
          @if(\App\Models\User::allows($user, 'manage_bluebooks') || \App\Models\User::allows($user, 'review_bluebooks'))
            <a href="{{ route('admin.bluebooks.edit', $bluebook['id']) }}" class="btn btn-outline btn-sm">{{ \App\Models\User::allows($user, 'manage_bluebooks') ? 'Edit' : 'Record waiver' }}</a>
          @endif
        @else
          <a href="{{ route('student.bluebooks') }}" class="btn btn-outline btn-sm">← Back to Browse</a>
        @endif
      </div>

      <div class="bluebook-detail" id="bluebook-detail" data-bluebook-id="{{ $bluebook['id'] }}"
           data-viewer="{{ $user['email'] ?? '' }}">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1rem;flex-wrap:wrap;">
          <h1>{{ $bluebook['title'] }}</h1>
          @unless($asAdmin)
          <div style="flex-shrink:0;">
            @if($isBookmarked)
              <form method="POST" action="{{ route('student.bookmarks.remove', $bluebook['id']) }}?from=view">
                @csrf
                <button type="submit" class="btn btn-warning btn-sm">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" fill="currentColor" fill-opacity="0.18"/><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  Remove Bookmark
                </button>
              </form>
            @else
              <form method="POST" action="{{ route('student.bookmarks.add', $bluebook['id']) }}">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" fill="currentColor" fill-opacity="0.18"/><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  Bookmark
                </button>
              </form>
            @endif
          </div>
          @endunless
        </div>

        <div class="bluebook-meta">
          @if($asAdmin)
            @if($bluebook['status'] === 'Approved') <span class="meta-pill badge-green">Posted</span>
            @elseif($bluebook['status'] === \App\Models\Bluebook::STATUS_AWAITING_WAIVER) <span class="meta-pill badge-blue">Awaiting Waiver</span>
            @elseif($bluebook['status'] === 'Pending') <span class="meta-pill badge-yellow">Pending</span>
            @else <span class="meta-pill badge-red">Rejected</span>
            @endif
          @endif
          <span class="meta-pill">{{ $bluebook['department'] }}</span>
          <span class="meta-pill">{{ $bluebook['year'] }}</span>
          <span class="meta-pill">{{ $bluebook['pages'] }} pages</span>
          <span class="meta-pill">{{ $bluebook['views'] }} views</span>
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
        </div>

        <h4 style="font-size:0.82rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:0.6rem;">Document</h4>
        @if($bluebook['hasFile'] && $bluebook['accessLevel'] === 'consultation' && !$asAdmin)
          <div class="alert alert-info" style="margin-bottom:0.5rem;">
            The author has not permitted this bluebook for general use. It is accessible after consultation with the author.
          </div>
          <div style="font-size:0.78rem;color:var(--gray-400);">Access logged for: {{ $user['email'] }}</div>
        @elseif($bluebook['hasFile'])
          @if($asAdmin)
            <div class="alert alert-info" style="margin-bottom:0.75rem;">
              You are seeing the whole document. Readers get:
              <strong>{{ \App\Models\Bluebook::ACCESS_LEVELS[$bluebook['accessLevel']] ?? $bluebook['accessLevel'] }}</strong>@if($bluebook['accessLevel'] === 'partial' && $bluebook['accessParts']) &mdash;
                @foreach($bluebook['accessParts'] as $key => $range){{ \App\Models\Bluebook::ACCESS_PARTS[$key] ?? $key }} (pp. {{ $range['from'] }}–{{ $range['to'] }}){{ $loop->last ? '' : ', ' }}@endforeach
              @endif.
              @unless($bluebook['waiverRecorded']) <em>(Not recorded yet.)</em> @endunless
            </div>
          @elseif($bluebook['accessLevel'] === 'partial')
            <div class="alert alert-info" style="margin-bottom:0.75rem;">
              The author has permitted only certain parts of this bluebook to be viewed:
              @foreach($bluebook['accessParts'] as $key => $range)
                <strong>{{ \App\Models\Bluebook::ACCESS_PARTS[$key] ?? $key }}</strong>
                (pp. {{ $range['from'] }}–{{ $range['to'] }}){{ $loop->last ? '.' : ',' }}
              @endforeach
            </div>
          @endif
          {{-- Rendered page by page to canvas by PDF.js rather than handed to
               the browser's own viewer in an iframe. Android's WebView ships no
               PDF renderer at all, so the iframe was simply blank there; this
               also removes the built-in viewer's download and print controls,
               and lets the watermark sit over the pages instead of beside
               them. --}}
          {{-- Where the disk can sign a link the document is fetched straight
               from storage: the object stops travelling through PHP on every
               read, and the bucket answers range requests, so the first page
               arrives after a few kilobytes instead of after all 29 MB. The
               streaming route stays as the fallback - it is what serves a disk
               that cannot sign, and what the viewer retries on if the direct
               fetch is refused (a bucket without CORS configured, or a link
               that has outlived the reading session). --}}
          <div class="pdf-view watermark-overlay"
               id="pdf-view"
               data-pdf-url="{{ $asAdmin ? route('admin.bluebooks.file', $bluebook['id']) : ($fileUrl ?? route('student.bluebook.file', $bluebook['id'])) }}"
               @if($fileUrl)
                 data-direct="1"
                 data-fallback-url="{{ route('student.bluebook.file', $bluebook['id']) }}"
               @endif
               data-worker-url="/vendor/pdfjs/pdf.worker.min.js">
            <div class="pdf-status" id="pdf-status">Loading document&hellip;</div>
            <div class="pdf-pages" id="pdf-pages"></div>
          </div>
          <div style="font-size:0.78rem;color:var(--gray-400);margin-top:0.5rem;">Access logged for: {{ $user['email'] }}</div>
        @else
          <div class="watermark-overlay" style="margin-top:0;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" style="margin:0 auto 1rem;display:block;"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" fill="var(--gray-400)" fill-opacity="0.18"/><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" fill="none" stroke="var(--gray-400)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <div style="font-size:0.9rem;color:var(--gray-600);margin-bottom:0.5rem;">No document file was attached to this record.</div>
            <div style="font-size:0.78rem;color:var(--gray-400);">Access logged for: {{ $user['email'] }}</div>
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;opacity:0.08;font-size:2.5rem;font-weight:700;letter-spacing:0.2em;transform:rotate(-25deg);color:var(--primary-dark);user-select:none;">CSPC ARCHIVE</div>
          </div>
        @endif
      </div>
    </div>

    <footer class="app-footer">
      C-BAMS &copy; {{ date('Y') }} &mdash; Camarines Sur Polytechnic Colleges. All Rights Reserved.
    </footer>
  </main>
</div>

@if($bluebook['hasFile'])
  <script src="/vendor/pdfjs/pdf.min.js"></script>
  <script src="/js/pdf-viewer.js?v={{ filemtime(public_path('js/pdf-viewer.js')) }}"></script>
@endif
@include('partials.footer')
