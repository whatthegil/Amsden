@php $title = 'Upload Bluebook'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">Upload Bluebook</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content form-page">
      <x-page-hero heading="Upload Bluebook"
                   sub="Submit your capstone paper. An administrator reviews it before it appears in the archive.">
        <x-slot name="action">
          <a href="{{ route('student.my-uploads') }}" class="btn btn-sm btn-on-hero">My uploads</a>
        </x-slot>
      </x-page-hero>

      @if($error)
        <div class="alert alert-error">{{ $error }}</div>
      @endif
      @if($success)
        <div class="alert alert-success">{{ $success }}</div>
      @endif

      @if(!empty($uploaded))
        @php $deptName = config('departments.' . $uploaded['department'] . '.name'); @endphp
        <div class="form-card" style="margin-bottom:1.25rem;">
          <div class="form-card-header">
            <h2>Details read from your PDF</h2>
          </div>
          <div class="form-card-body" style="font-size:0.9rem;">
            @if(!empty($missing))
              <div class="alert alert-info" style="margin-bottom:1rem;">
                @if(empty($uploaded['ocrText']) && count($missing) >= 6)
                  Your PDF looks like a scan with no selectable text, so its details will be read once its text has been scanned (OCR).
                @else
                  We couldn't find the {{ implode(', ', $missing) }} in your PDF.
                @endif
                The library will complete anything still missing when it reviews your submission.
              </div>
            @endif
            <dl class="upload-details">
              <dt>Title</dt><dd>{{ in_array('title', $missing) ? '—' : $uploaded['title'] }}</dd>
              <dt>Authors</dt><dd>{{ implode('; ', array_filter($uploaded['authors'])) ?: '—' }}</dd>
              <dt>Adviser</dt><dd>{{ $uploaded['adviser'] ?: '—' }}</dd>
              <dt>College</dt><dd>{{ $deptName ?: '—' }}</dd>
              <dt>Program</dt><dd>{{ $uploaded['program'] ?: '—' }}</dd>
              <dt>Academic Year</dt><dd>{{ $uploaded['year'] }}</dd>
              <dt>Number of Pages</dt><dd>{{ $uploaded['pages'] ?: '—' }}</dd>
              <dt>Keywords</dt><dd>{{ implode(', ', array_filter($uploaded['keywords'])) ?: '—' }}</dd>
              <dt>Abstract</dt><dd style="max-height:10rem;overflow-y:auto;">{{ $uploaded['abstract'] ?: '—' }}</dd>
            </dl>
            <p style="margin-top:0.75rem;color:var(--gray-400);font-size:0.82rem;">Something wrong? The library corrects the details while it reviews your submission. You don't need to upload again.</p>
          </div>
        </div>
        <style>
          .upload-details { display: grid; grid-template-columns: 9rem 1fr; gap: 0.4rem 1rem; margin: 0; }
          .upload-details dt { font-weight: 600; color: var(--gray-600); }
          .upload-details dd { margin: 0; color: var(--gray-800); }
          @media (max-width: 560px) { .upload-details { grid-template-columns: 1fr; } .upload-details dd { margin-bottom: 0.4rem; } }
        </style>
      @endif

      {{-- The waiver is set by the admin from the signed paper copy, which the
           author downloads from My Uploads once the bluebook is sent in. --}}
      <div class="alert alert-info">
        <strong>Access permission waiver.</strong>
        After uploading, download the waiver from My Uploads, fill it in and sign it, then hand it in to the CSPC Library.
        Your bluebook is posted once the library receives it.
      </div>

      <div class="form-card">
        <div class="form-card-header">
          <span class="icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" fill="currentColor" fill-opacity="0.18"/><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <h2>Your Bluebook</h2>
        </div>
        <div class="form-card-body">
          <form method="POST" action="{{ route('student.upload.store') }}" enctype="multipart/form-data">
            @csrf

            <!-- Drag-drop zone -->
            <label for="fileInput" class="file-drop-zone">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" style="margin:0 auto 0.5rem;display:block;"><path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" fill="var(--primary-dark)" fill-opacity="0.18"/><path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" fill="none" stroke="var(--primary-dark)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <div style="font-weight:600;color:var(--primary-dark);margin-bottom:0.25rem;">Click or drag your PDF here</div>
              <div style="font-size:0.83rem;color:var(--gray-400);">Accepted: .pdf, up to 25 MB</div>
            </label>
            <input type="file" id="fileInput" name="file" accept=".pdf,application/pdf" required class="sr-only" aria-describedby="fileInfo">
            <div id="fileInfo" style="display:none;padding:0.75rem 1rem;background:var(--green-light);border-radius:var(--radius-sm);border:1px solid var(--green);font-size:0.88rem;color:var(--green);margin-bottom:1rem;" role="status"></div>

            <div class="form-group">
              <label for="upload-year">Academic Year</label>
              <input id="upload-year" type="number" name="year" required min="2000" max="{{ date('Y') + 1 }}" value="{{ $old['year'] ?? date('Y') }}">
            </div>

            <div style="background:var(--primary-light);border:1px solid var(--primary-pale);border-radius:var(--radius-sm);padding:0.875rem 1rem;margin-bottom:1.25rem;font-size:0.85rem;color:var(--primary-dark);">
              <strong>No need to type your paper's details.</strong>
              The title, authors, adviser, college, program, keywords, abstract and number of pages are read from your PDF's
              title page, abstract page and acknowledgements. Use the final manuscript in the standard CSPC format.
              Anything that can't be read is completed by the library when it reviews your submission.
            </div>

            <div class="form-actions">
              <a href="{{ route('student.dashboard') }}" class="btn btn-outline">Cancel</a>
              <button type="submit" class="btn btn-primary">Submit for Review</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <footer class="app-footer">
      C-BAMS &copy; {{ date('Y') }} &mdash; Camarines Sur Polytechnic Colleges. All Rights Reserved.
    </footer>
  </main>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
function handleFile(input) {
  const file = input.files[0];
  if (file) {
    const info = document.getElementById('fileInfo');
    info.style.display = 'block';
    info.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
  }
}
document.getElementById('fileInput').addEventListener('change', function () { handleFile(this); });
</script>

@include('partials.footer')
