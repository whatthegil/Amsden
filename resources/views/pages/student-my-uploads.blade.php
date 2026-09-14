@php
  $title = 'My Uploads';

  $approvedCount = count(array_filter($bluebooks, fn($b) => $b['status'] === 'Approved'));
  $pendingCount  = count(array_filter($bluebooks, fn($b) => $b['status'] === 'Pending'));
  $rejectedCount = count(array_filter($bluebooks, fn($b) => $b['status'] === 'Rejected'));
@endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">My Uploads</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content">
      <div class="dash-hero slim">
        <div class="dash-hero-top" style="margin-bottom:0;">
          <div>
            <h1>My Uploads</h1>
            @if(count($bluebooks) > 0)
              <p>{{ count($bluebooks) }} {{ Str::plural('submission', count($bluebooks)) }} to the CSPC archive.</p>
              {{-- How they divide up, in a line rather than a row of cards. --}}
              <div class="hero-tally">
                @if($approvedCount > 0)
                  <span><span class="dot" style="background:#7ee2b8;"></span> {{ $approvedCount }} approved</span>
                @endif
                @if($pendingCount > 0)
                  @if($approvedCount > 0)<span class="sep">&middot;</span>@endif
                  <span><span class="dot" style="background:#fbbf24;"></span> {{ $pendingCount }} awaiting review</span>
                @endif
                @if($rejectedCount > 0)
                  @if($approvedCount > 0 || $pendingCount > 0)<span class="sep">&middot;</span>@endif
                  <span><span class="dot" style="background:#fca5a5;"></span> {{ $rejectedCount }} not published</span>
                @endif
              </div>
            @else
              <p>Nothing submitted yet.</p>
            @endif
          </div>
          @if($user['canUpload'] ?? false)
            <a href="{{ route('student.upload') }}" class="btn btn-sm btn-on-hero">
              <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0-12l-4 4m4-4l4 4M4 20h16"/></svg>
              Upload New
            </a>
          @endif
        </div>
      </div>

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif

      @if(count($bluebooks) > 0)
        <div class="submission-list">
          @foreach($bluebooks as $b)
            @php
              $state = $b['status'] === 'Approved' ? 'approved'
                     : ($b['status'] === 'Pending' ? 'pending' : 'rejected');
            @endphp
            <div class="submission is-{{ $state }}">
              <div class="submission-head">
                @if($b['status'] === 'Approved')
                  <a class="submission-title" href="{{ route('student.bluebook', $b['id']) }}">{{ $b['title'] }}</a>
                @else
                  <div class="submission-title">{{ $b['title'] }}</div>
                @endif
                <div style="flex-shrink:0;">
                  @if($b['status'] === 'Approved') <span class="badge badge-green">Approved</span>
                  @elseif($b['status'] === 'Pending') <span class="badge badge-yellow">Pending Review</span>
                  @else <span class="badge badge-red">Rejected</span>
                  @endif
                </div>
              </div>

              <div class="submission-meta">
                <span class="badge badge-blue">{{ $b['department'] }}</span>
                <span>{{ $b['year'] }}</span>
                <span class="sep">&middot;</span>
                <span>Submitted {{ $b['dateAdded'] }}</span>
                @if($b['status'] === 'Approved')
                  <span class="sep">&middot;</span>
                  <span>{{ $b['views'] }} {{ Str::plural('view', $b['views']) }}</span>
                @endif
              </div>

              {{-- What the badge means for the person who sent it in. A one-word
                   status leaves an author guessing whether anything is expected
                   of them. --}}
              <div class="submission-note">
                @if($b['status'] === 'Approved')
                  Published to the archive and readable by students and faculty.
                @elseif($b['status'] === 'Pending')
                  Waiting for an administrator to review it. Nothing is needed from you.
                @else
                  Not published to the archive. Contact your adviser or the CSPC Library if you think this is wrong.
                @endif
              </div>

              @if($b['hasFile'])
                {{-- OCR to us, but the author only cares what it decides: whether
                     the paper turns up in a search of its own contents. --}}
                <div class="submission-foot">
                  <span class="ocr">
                    <span class="ocr-label">Searchable text</span>
                    @if($b['ocrStatus'] === 'completed') <span class="badge badge-green">Ready</span>
                    @elseif($b['ocrStatus'] === 'processing') <span class="badge badge-yellow">Processing</span>
                    @elseif($b['ocrStatus'] === 'failed') <span class="badge badge-red">Failed</span>
                    @else <span class="badge badge-gray">Queued</span>
                    @endif
                    @if($b['ocrStatus'] === 'failed' && !empty($b['ocrError']))
                      <span style="color:var(--gray-400);">&mdash; {{ Str::limit($b['ocrError'], 70) }}</span>
                    @elseif($b['ocrStatus'] === 'completed')
                      <span style="color:var(--gray-400);">This paper can be found by the words inside it.</span>
                    @endif
                  </span>
                  @if($b['ocrStatus'] !== 'processing')
                    <form method="POST" action="{{ route('student.bluebook.reprocess-ocr', $b['id']) }}">
                      @csrf
                      <button type="submit" class="btn btn-outline btn-sm">
                        {{ $b['ocrStatus'] === 'failed' ? 'Try again' : 'Re-extract' }}
                      </button>
                    </form>
                  @endif
                </div>
              @endif
            </div>
          @endforeach
        </div>
      @else
        <div class="card">
          <div class="empty-state">
            <div class="icon">
              <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M4 20h16"/></svg>
            </div>
            <p>You have not submitted a bluebook yet.</p>
            @if($user['canUpload'] ?? false)
              <p style="font-size:0.8rem;color:var(--gray-400);margin-top:0.35rem;">Submissions are reviewed by an administrator before they appear in the archive.</p>
              <a href="{{ route('student.upload') }}" class="btn btn-primary btn-sm" style="margin-top:0.9rem;">Upload your first bluebook</a>
            @else
              <p style="font-size:0.8rem;color:var(--gray-400);margin-top:0.35rem;">Ask an administrator to enable uploads for your account.</p>
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
