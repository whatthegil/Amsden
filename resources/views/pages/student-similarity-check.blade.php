@php $title = 'Similarity Check'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <div class="main">
    <header class="topbar">
      <h1 class="topbar-title">Similarity Check</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content form-page">
      <div class="page-header">
        <div>
          <h1>Pre-Title Proposal Similarity Check</h1>
          <p>Enter your proposed capstone title to check for similar or duplicate research in the archive before submitting.</p>
        </div>
      </div>

      {{-- Input Form --}}
      <div class="form-card">
        <div class="form-card-header">
          <span class="icon">🔍</span>
          <h2>Your Proposed Title</h2>
        </div>
        <div class="form-card-body">
          <form method="POST" action="{{ route('student.similarity-check.post') }}">
            @csrf

            <div class="form-group">
              <label>Proposed Research Title <span style="color:var(--red);">*</span></label>
              <input
                type="text"
                name="title"
                required
                placeholder="e.g. Development of a Web-Based Attendance Monitoring System"
                value="{{ $proposed['title'] ?? '' }}"
              >
              <small style="color:var(--gray-400);font-size:0.8rem;">This field is required. The title is the primary basis for similarity scoring.</small>
            </div>

            <div class="form-group">
              <label>Keywords <span style="font-weight:400;color:var(--gray-400);">(optional — comma-separated, improves accuracy)</span></label>
              <input
                type="text"
                name="keywords"
                placeholder="e.g. Attendance, Web System, QR Code, Laravel"
                value="{{ isset($proposed['keywords']) ? implode(', ', $proposed['keywords']) : '' }}"
              >
            </div>

            <div class="form-group">
              <label>Abstract <span style="font-weight:400;color:var(--gray-400);">(optional — further improves accuracy)</span></label>
              <textarea name="abstract" rows="4" placeholder="Brief description of your proposed research…">{{ $proposed['abstract'] ?? '' }}</textarea>
            </div>

            <div style="background:var(--primary-light);border:1px solid var(--primary-pale);border-radius:var(--radius-sm);padding:0.875rem 1rem;margin-bottom:1.25rem;font-size:0.85rem;color:var(--primary-dark);">
              <strong>How scoring works:</strong> Title match accounts for 60% of the score, keywords 25%, and abstract 15%. Results with at least 8% similarity are shown.
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
        @php
          $highCount = count(array_filter($results, fn($r) => $r['percentage'] >= 60));
          $modCount  = count(array_filter($results, fn($r) => $r['percentage'] >= 30 && $r['percentage'] < 60));
          $lowCount  = count(array_filter($results, fn($r) => $r['percentage'] < 30));
          $total     = count($results);
        @endphp

        {{-- Summary Banner --}}
        @if($total === 0)
          <div class="alert alert-success" style="margin-top:1.5rem;display:flex;align-items:center;gap:0.75rem;">
            <span style="font-size:1.4rem;">✅</span>
            <div>
              <strong>No similar titles found.</strong><br>
              <span style="font-size:0.88rem;">Your proposed title shows no notable similarity to any existing bluebook in the archive. You may proceed with confidence.</span>
            </div>
          </div>
        @else
          @if($highCount > 0)
            <div class="alert alert-error" style="margin-top:1.5rem;display:flex;align-items:center;gap:0.75rem;">
              <span style="font-size:1.4rem;">⚠️</span>
              <div>
                <strong>High similarity detected — {{ $highCount }} bluebook{{ $highCount !== 1 ? 's' : '' }} with 60%+ match.</strong><br>
                <span style="font-size:0.88rem;">Please review the results below and consider revising your title to avoid duplication.</span>
              </div>
            </div>
          @else
            <div style="background:var(--yellow-light);border:1px solid #f6d860;border-radius:var(--radius-sm);padding:0.875rem 1.25rem;margin-top:1.5rem;display:flex;align-items:center;gap:0.75rem;color:var(--yellow);">
              <span style="font-size:1.4rem;">🔶</span>
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

    <footer style="padding:1rem 2rem;font-size:0.8rem;color:var(--gray-400);border-top:1px solid var(--gray-200);background:rgba(255,255,255,0.98);">
      AMSDEN &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </div>
</div>

@include('partials.footer')
