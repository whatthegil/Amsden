@php $title = 'Similarity Check'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">Similarity Check</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content form-page">
      <div class="page-header">
        <div>
          <h1>Pre-Proposal Similarity Check</h1>
          <p>Check your proposed capstone against the archive before submitting — enter your title, or upload your full pre-proposal PDF to compare it against existing research and similar bluebooks.</p>
        </div>
      </div>

      @if(!empty($error))
        <div class="alert alert-error" style="margin-bottom:1rem;">{{ $error }}</div>
      @endif

      @php $activeTab = (isset($proposed['sourceFile']) && $proposed['sourceFile']) ? 'file' : 'text'; @endphp

      {{-- Mode switch --}}
      <div class="sim-tabs" role="tablist" style="display:flex;gap:0.5rem;margin-bottom:1rem;">
        <button type="button" class="btn btn-outline sim-tab" data-tab="text" aria-selected="{{ $activeTab === 'text' ? 'true' : 'false' }}">Enter a title</button>
        <button type="button" class="btn btn-outline sim-tab" data-tab="file" aria-selected="{{ $activeTab === 'file' ? 'true' : 'false' }}">Upload pre-proposal (PDF)</button>
      </div>

      {{-- Upload Form --}}
      <div class="form-card sim-panel" data-panel="file" style="{{ $activeTab === 'file' ? '' : 'display:none;' }}">
        <div class="form-card-header">
          <span class="icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" fill="currentColor" fill-opacity="0.18"/><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <h2>Upload Your Pre-Proposal</h2>
        </div>
        <div class="form-card-body">
          <form method="POST" action="{{ route('student.similarity-check.post') }}" enctype="multipart/form-data">
            @csrf

            <label for="sim-file" class="file-drop-zone">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" style="margin:0 auto 0.5rem;display:block;"><path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" fill="var(--primary-dark)" fill-opacity="0.18"/><path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" fill="none" stroke="var(--primary-dark)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <div style="font-weight:600;color:var(--primary-dark);margin-bottom:0.25rem;">Click or drag your pre-proposal PDF here</div>
              <div style="font-size:0.83rem;color:var(--gray-400);">Accepted: .pdf, up to 25 MB. The file is only read for this check — it is not saved or submitted to the archive.</div>
            </label>
            <input type="file" id="sim-file" name="file" accept=".pdf,application/pdf" required class="sr-only" onchange="simShowFile(this)" aria-describedby="sim-file-info">
            <div id="sim-file-info" style="display:none;padding:0.75rem 1rem;background:var(--green-light);border-radius:var(--radius-sm);border:1px solid var(--green);font-size:0.88rem;color:var(--green);margin-bottom:1rem;" role="status"></div>

            <div style="background:var(--primary-light);border:1px solid var(--primary-pale);border-radius:var(--radius-sm);padding:0.875rem 1rem;margin-bottom:1.25rem;font-size:0.85rem;color:var(--primary-dark);">
              <strong>How this works:</strong> we extract the text from your PDF and compare its wording against the title, keywords, abstract, and full text of every approved bluebook. Scanned documents without a selectable text layer may not be readable — type your title instead if so.
            </div>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary">Check Similarity</button>
            </div>
          </form>
        </div>
      </div>

      {{-- Input Form --}}
      <div class="form-card sim-panel" data-panel="text" style="{{ $activeTab === 'text' ? '' : 'display:none;' }}">
        <div class="form-card-header">
          <span class="icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" fill="currentColor" fill-opacity="0.18"/><path d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <h2>Your Proposed Title</h2>
        </div>
        <div class="form-card-body">
          <form method="POST" action="{{ route('student.similarity-check.post') }}">
            @csrf

            <div class="form-group">
              <label for="sim-title">Proposed Research Title <span style="color:var(--red);">*</span></label>
              <input
                id="sim-title"
                type="text"
                name="title"
                required
                placeholder="e.g. Development of a Web-Based Attendance Monitoring System"
                value="{{ $proposed['title'] ?? '' }}"
                aria-describedby="sim-title-hint"
              >
              <small style="color:var(--gray-400);font-size:0.8rem;" id="sim-title-hint">This field is required. The title is the primary basis for similarity scoring.</small>
            </div>

            <div class="form-group">
              <label for="sim-keywords">Keywords <span style="font-weight:400;color:var(--gray-400);">(optional — comma-separated, improves accuracy)</span></label>
              <input
                id="sim-keywords"
                type="text"
                name="keywords"
                placeholder="e.g. Attendance, Web System, QR Code, Laravel"
                value="{{ isset($proposed['keywords']) ? implode(', ', $proposed['keywords']) : '' }}"
              >
            </div>

            <div class="form-group">
              <label for="sim-abstract">Abstract <span style="font-weight:400;color:var(--gray-400);">(optional — further improves accuracy)</span></label>
              <textarea id="sim-abstract" name="abstract" rows="4" placeholder="Brief description of your proposed research…">{{ $proposed['abstract'] ?? '' }}</textarea>
            </div>

            <div style="background:var(--primary-light);border:1px solid var(--primary-pale);border-radius:var(--radius-sm);padding:0.875rem 1rem;margin-bottom:1.25rem;font-size:0.85rem;color:var(--primary-dark);">
              <strong>How scoring works:</strong> Title match accounts for 50% of the score, keywords 20%, abstract 10%, and overlap with existing documents' full text 20%. Results with at least 8% similarity are shown.
            </div>

            <div class="form-actions">
              <button type="reset" class="btn btn-outline">Clear</button>
              <button type="submit" class="btn btn-primary">Check Similarity</button>
            </div>
          </form>
        </div>
      </div>

      {{-- Results Section --}}
      @if($results !== null)
        @if(!empty($proposed['sourceFile']))
          <div style="margin-top:1.5rem;font-size:0.88rem;color:var(--gray-600);background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-sm);padding:0.75rem 1rem;">
            Compared from uploaded file <strong>{{ $proposed['sourceFile'] }}</strong>.
            Detected title: <em>"{{ $proposed['title'] }}"</em>
            <span style="color:var(--gray-400);">(best guess from the document text — the full text was used for scoring)</span>
          </div>
        @endif
        @php
          $highCount = count(array_filter($results, fn($r) => $r['percentage'] >= 60));
          $modCount  = count(array_filter($results, fn($r) => $r['percentage'] >= 30 && $r['percentage'] < 60));
          $lowCount  = count(array_filter($results, fn($r) => $r['percentage'] < 30));
          $total     = count($results);
        @endphp

        {{-- Summary Banner --}}
        @if($total === 0)
          <div class="alert alert-success" style="margin-top:1.5rem;display:flex;align-items:center;gap:0.75rem;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" style="flex-shrink:0;"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" fill="currentColor" fill-opacity="0.18"/><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <div>
              <strong>No similar titles found.</strong><br>
              <span style="font-size:0.88rem;">Your proposed title shows no notable similarity to any existing bluebook in the archive. You may proceed with confidence.</span>
            </div>
          </div>
        @else
          @if($highCount > 0)
            <div class="alert alert-error" style="margin-top:1.5rem;display:flex;align-items:center;gap:0.75rem;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" style="flex-shrink:0;"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" fill="currentColor" fill-opacity="0.18"/><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <div>
                <strong>High similarity detected — {{ $highCount }} bluebook{{ $highCount !== 1 ? 's' : '' }} with 60%+ match.</strong><br>
                <span style="font-size:0.88rem;">Please review the results below and consider revising your title to avoid duplication.</span>
              </div>
            </div>
          @else
            <div style="background:var(--yellow-light);border:1px solid #f6d860;border-radius:var(--radius-sm);padding:0.875rem 1.25rem;margin-top:1.5rem;display:flex;align-items:center;gap:0.75rem;color:var(--yellow);">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" style="flex-shrink:0;"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" fill="currentColor" fill-opacity="0.18"/><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <div>
                <strong>Some similar titles found — {{ $total }} result{{ $total !== 1 ? 's' : '' }} returned.</strong><br>
                <span style="font-size:0.88rem;">No high-risk duplicates, but review the matches below to ensure your topic is sufficiently distinct.</span>
              </div>
            </div>
          @endif
        @endif

        {{-- Stats Row --}}
        @if($total > 0)
          <div style="display:flex;gap:1rem;margin-top:1rem;flex-wrap:wrap;">
            <div style="flex:1;min-width:120px;background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);padding:1rem;text-align:center;box-shadow:var(--shadow);">
              <div style="font-size:1.6rem;font-weight:700;color:var(--gray-800);">{{ $total }}</div>
              <div style="font-size:0.8rem;color:var(--gray-400);margin-top:0.2rem;">Total Matches</div>
            </div>
            <div style="flex:1;min-width:120px;background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);padding:1rem;text-align:center;box-shadow:var(--shadow);">
              <div style="font-size:1.6rem;font-weight:700;color:var(--red);">{{ $highCount }}</div>
              <div style="font-size:0.8rem;color:var(--gray-400);margin-top:0.2rem;">High Similarity (&ge;60%)</div>
            </div>
            <div style="flex:1;min-width:120px;background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);padding:1rem;text-align:center;box-shadow:var(--shadow);">
              <div style="font-size:1.6rem;font-weight:700;color:var(--yellow);">{{ $modCount }}</div>
              <div style="font-size:0.8rem;color:var(--gray-400);margin-top:0.2rem;">Moderate (30–59%)</div>
            </div>
            <div style="flex:1;min-width:120px;background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius);padding:1rem;text-align:center;box-shadow:var(--shadow);">
              <div style="font-size:1.6rem;font-weight:700;color:var(--green);">{{ $lowCount }}</div>
              <div style="font-size:0.8rem;color:var(--gray-400);margin-top:0.2rem;">Low (&lt;30%)</div>
            </div>
          </div>

          {{-- Results Table --}}
          <div class="card" style="margin-top:1.25rem;">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
              <span style="font-weight:600;font-size:0.95rem;">Similarity Results for: <em>"{{ $proposed['title'] }}"</em></span>
              <span style="font-size:0.82rem;color:var(--gray-400);">Sorted by highest similarity</span>
            </div>
            <div style="overflow-x:auto;">
              <table class="table">
                <thead>
                  <tr>
                    <th style="width:44px;">#</th>
                    <th>Existing Bluebook Title</th>
                    <th>Authors</th>
                    <th>Dept</th>
                    <th>Year</th>
                    <th style="width:120px;text-align:center;">Similarity</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($results as $i => $result)
                    @php
                      $pct = $result['percentage'];
                      if ($pct >= 60) {
                        $badgeBg    = 'var(--red-light)';
                        $badgeColor = 'var(--red)';
                        $label      = 'High';
                        $barColor   = 'var(--red)';
                      } elseif ($pct >= 30) {
                        $badgeBg    = 'var(--yellow-light)';
                        $badgeColor = 'var(--yellow)';
                        $label      = 'Moderate';
                        $barColor   = '#f6c90e';
                      } else {
                        $badgeBg    = 'var(--green-light)';
                        $badgeColor = 'var(--green)';
                        $label      = 'Low';
                        $barColor   = 'var(--green)';
                      }
                    @endphp
                    <tr>
                      <td style="color:var(--gray-400);font-size:0.82rem;">{{ $i + 1 }}</td>
                      <td>
                        <a href="{{ route('student.bluebook', ['id' => $result['bluebook']['id']]) }}"
                           style="color:var(--primary);font-weight:500;"
                           target="_blank">
                          {{ $result['bluebook']['title'] }}
                        </a>
                        @if(!empty($result['bluebook']['keywords']))
                          <div style="margin-top:0.3rem;display:flex;flex-wrap:wrap;gap:0.25rem;">
                            @foreach(array_slice($result['bluebook']['keywords'], 0, 4) as $kw)
                              <span style="font-size:0.72rem;background:var(--primary-light);color:var(--primary-dark);padding:0.1rem 0.45rem;border-radius:999px;">{{ $kw }}</span>
                            @endforeach
                          </div>
                        @endif
                      </td>
                      <td style="font-size:0.84rem;color:var(--gray-600);">
                        {{ implode(', ', array_slice($result['bluebook']['authors'], 0, 2)) }}{{ count($result['bluebook']['authors']) > 2 ? ' et al.' : '' }}
                      </td>
                      <td>
                        <span class="badge badge-default">{{ $result['bluebook']['department'] }}</span>
                      </td>
                      <td style="color:var(--gray-600);font-size:0.88rem;">{{ $result['bluebook']['year'] }}</td>
                      <td style="text-align:center;">
                        <div style="display:flex;flex-direction:column;align-items:center;gap:0.3rem;">
                          <span style="background:{{ $badgeBg }};color:{{ $badgeColor }};padding:0.2rem 0.6rem;border-radius:999px;font-size:0.78rem;font-weight:600;white-space:nowrap;">
                            {{ $pct }}% &mdash; {{ $label }}
                          </span>
                          <div style="width:80px;height:5px;background:var(--gray-200);border-radius:99px;overflow:hidden;">
                            <div style="height:100%;width:{{ min($pct, 100) }}%;background:{{ $barColor }};border-radius:99px;transition:width 0.4s;"></div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>

          {{-- Legend --}}
          <div style="margin-top:0.75rem;font-size:0.8rem;color:var(--gray-400);display:flex;gap:1.5rem;flex-wrap:wrap;">
            <span><span style="color:var(--red);font-weight:600;">●</span> High (&ge;60%) — Likely duplicate, revise your title</span>
            <span><span style="color:var(--yellow);font-weight:600;">●</span> Moderate (30–59%) — Review and differentiate</span>
            <span><span style="color:var(--green);font-weight:600;">●</span> Low (&lt;30%) — Minimal concern</span>
          </div>
        @endif
      @endif
    </div>

    <footer class="app-footer">
      C-BAMS &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </main>
</div>

<script>
(function () {
  var tabs   = document.querySelectorAll('.sim-tab');
  var panels = document.querySelectorAll('.sim-panel');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var name = tab.dataset.tab;
      tabs.forEach(function (t) { t.setAttribute('aria-selected', String(t === tab)); });
      panels.forEach(function (p) { p.style.display = (p.dataset.panel === name) ? '' : 'none'; });
    });
  });
})();

function simShowFile(input) {
  var file = input.files[0];
  if (!file) return;
  var info = document.getElementById('sim-file-info');
  info.style.display = 'block';
  info.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
}
</script>

<style>
  .sim-tab[aria-selected="true"] {
    background: var(--primary-light);
    border-color: var(--primary-mid);
    color: var(--primary-dark);
  }
</style>

@include('partials.footer')
