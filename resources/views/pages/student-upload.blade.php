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
      <div class="page-header">
        <div>
          <h1>Upload Bluebook</h1>
          <p>Submit your capstone research paper for admin review.</p>
        </div>
      </div>

      @if($error)
        <div class="alert alert-error">{{ $error }}</div>
      @endif
      @if($success)
        <div class="alert alert-success">{{ $success }}</div>
      @endif

      <div class="form-card">
        <div class="form-card-header">
          <span class="icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" fill="currentColor" fill-opacity="0.18"/><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <h2>Bluebook Details</h2>
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
            <input type="file" id="fileInput" name="file" accept=".pdf,application/pdf" required class="sr-only" onchange="handleFile(this)" aria-describedby="fileInfo">
            <div id="fileInfo" style="display:none;padding:0.75rem 1rem;background:var(--green-light);border-radius:var(--radius-sm);border:1px solid var(--green);font-size:0.88rem;color:var(--green);margin-bottom:1rem;" role="status"></div>

            <div class="form-group">
              <label for="upload-title">Research Paper Title</label>
              <input id="upload-title" type="text" name="title" required placeholder="Full title of your capstone research paper" value="{{ $old['title'] ?? '' }}">
            </div>

            <div class="form-group">
              <label for="upload-authors">Authors <span style="font-weight:400;color:var(--gray-400);">(separate with semicolons: Last, First; Last, First)</span></label>
              <input id="upload-authors" type="text" name="authors" required placeholder="e.g. Dela Cruz, Juan; Santos, Maria" value="{{ $old['authors'] ?? '' }}">
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="upload-department">College / Department</label>
                <select id="upload-department" name="department" required>
                  <option value="">Select Department</option>
                  <option value="CCS" @selected(($old['department'] ?? '') === 'CCS')>CCS — Computing Studies</option>
                  <option value="CENG" @selected(($old['department'] ?? '') === 'CENG')>CENG — Engineering</option>
                  <option value="CAS" @selected(($old['department'] ?? '') === 'CAS')>CAS — Arts and Sciences</option>
                  <option value="CHS" @selected(($old['department'] ?? '') === 'CHS')>CHS — Health Sciences</option>
                  <option value="CTDE" @selected(($old['department'] ?? '') === 'CTDE')>CTDE — Teacher Development</option>
                  <option value="CTHM" @selected(($old['department'] ?? '') === 'CTHM')>CTHM — Tourism &amp; Hospitality</option>
                  <option value="CBA" @selected(($old['department'] ?? '') === 'CBA')>CBA — Business Administration</option>
                </select>
              </div>
              <div class="form-group">
                <label for="upload-year">Academic Year</label>
                <input id="upload-year" type="number" name="year" required min="2000" max="{{ date('Y') + 1 }}" value="{{ $old['year'] ?? date('Y') }}">
              </div>
            </div>

            <div class="form-group">
              <label for="upload-program">Program</label>
              <select id="upload-program" name="program" required>
                <option value="">Select Program</option>
                <optgroup label="CCS — College of Computing Studies">
                  <option value="Bachelor of Science in Information Technology" @selected(($old['program'] ?? '') === 'Bachelor of Science in Information Technology')>Bachelor of Science in Information Technology</option>
                  <option value="Bachelor of Science in Computer Science" @selected(($old['program'] ?? '') === 'Bachelor of Science in Computer Science')>Bachelor of Science in Computer Science</option>
                </optgroup>
                <optgroup label="CENG — College of Engineering">
                  <option value="Bachelor of Science in Civil Engineering" @selected(($old['program'] ?? '') === 'Bachelor of Science in Civil Engineering')>Bachelor of Science in Civil Engineering</option>
                  <option value="Bachelor of Science in Electrical Engineering" @selected(($old['program'] ?? '') === 'Bachelor of Science in Electrical Engineering')>Bachelor of Science in Electrical Engineering</option>
                  <option value="Bachelor of Science in Mechanical Engineering" @selected(($old['program'] ?? '') === 'Bachelor of Science in Mechanical Engineering')>Bachelor of Science in Mechanical Engineering</option>
                </optgroup>
                <optgroup label="CAS — College of Arts and Sciences">
                  <option value="Bachelor of Arts in Communication" @selected(($old['program'] ?? '') === 'Bachelor of Arts in Communication')>Bachelor of Arts in Communication</option>
                  <option value="Bachelor of Science in Biology" @selected(($old['program'] ?? '') === 'Bachelor of Science in Biology')>Bachelor of Science in Biology</option>
                </optgroup>
                <optgroup label="CHS — College of Health Sciences">
                  <option value="Bachelor of Science in Nursing" @selected(($old['program'] ?? '') === 'Bachelor of Science in Nursing')>Bachelor of Science in Nursing</option>
                </optgroup>
                <optgroup label="CTDE — College of Teacher Development and Education">
                  <option value="Bachelor of Elementary Education" @selected(($old['program'] ?? '') === 'Bachelor of Elementary Education')>Bachelor of Elementary Education</option>
                  <option value="Bachelor of Secondary Education" @selected(($old['program'] ?? '') === 'Bachelor of Secondary Education')>Bachelor of Secondary Education</option>
                </optgroup>
                <optgroup label="CTHM — College of Tourism, Hospitality, and Management">
                  <option value="Bachelor of Science in Tourism Management" @selected(($old['program'] ?? '') === 'Bachelor of Science in Tourism Management')>Bachelor of Science in Tourism Management</option>
                  <option value="Bachelor of Science in Hospitality Management" @selected(($old['program'] ?? '') === 'Bachelor of Science in Hospitality Management')>Bachelor of Science in Hospitality Management</option>
                </optgroup>
                <optgroup label="CBA — College of Business Administration">
                  <option value="Bachelor of Science in Business Administration" @selected(($old['program'] ?? '') === 'Bachelor of Science in Business Administration')>Bachelor of Science in Business Administration</option>
                </optgroup>
              </select>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="upload-adviser">Adviser</label>
                <input id="upload-adviser" type="text" name="adviser" required placeholder="Prof. Name" value="{{ $old['adviser'] ?? '' }}">
              </div>
              <div class="form-group">
                <label for="upload-pages">Number of Pages</label>
                <input id="upload-pages" type="number" name="pages" required min="1" placeholder="e.g. 120" value="{{ $old['pages'] ?? '' }}">
              </div>
            </div>

            <div class="form-group">
              <label for="upload-keywords">Keywords <span style="font-weight:400;color:var(--gray-400);">(comma-separated)</span></label>
              <input id="upload-keywords" type="text" name="keywords" required placeholder="e.g. Machine Learning, IoT, Agriculture" value="{{ $old['keywords'] ?? '' }}">
            </div>

            <div class="form-group">
              <label for="upload-abstract">Abstract</label>
              <textarea id="upload-abstract" name="abstract" rows="5" required placeholder="Brief description of your research paper…">{{ $old['abstract'] ?? '' }}</textarea>
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
      C-BAMS &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </main>
</div>

<script>
function handleFile(input) {
  const file = input.files[0];
  if (file) {
    const info = document.getElementById('fileInfo');
    info.style.display = 'block';
    info.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
  }
}
</script>

@include('partials.footer')
