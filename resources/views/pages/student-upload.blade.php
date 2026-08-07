@php $title = 'Upload Bluebook'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <div class="main">
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
          <span class="icon">📤</span>
          <h2>Bluebook Details</h2>
        </div>
        <div class="form-card-body">
          <form method="POST" action="{{ route('student.upload.store') }}" enctype="multipart/form-data">
            @csrf

            <!-- Drag-drop zone -->
            <div style="border:2px dashed var(--primary-pale);border-radius:var(--radius);padding:2rem;text-align:center;background:var(--primary-light);margin-bottom:1.5rem;cursor:pointer;"
                 onclick="document.getElementById('fileInput').click()">
              <div style="font-size:2rem;margin-bottom:0.5rem;">📁</div>
              <div style="font-weight:600;color:var(--primary-dark);margin-bottom:0.25rem;">Click or drag your PDF here</div>
              <div style="font-size:0.83rem;color:var(--gray-400);">Accepted: .pdf, up to 25 MB</div>
            </div>
            <input type="file" id="fileInput" name="file" accept=".pdf,application/pdf" required style="display:none;" onchange="handleFile(this)">
            <div id="fileInfo" style="display:none;padding:0.75rem 1rem;background:var(--green-light);border-radius:var(--radius-sm);border:1px solid var(--green);font-size:0.88rem;color:var(--green);margin-bottom:1rem;"></div>

            <div class="form-group">
              <label>Research Paper Title</label>
              <input type="text" name="title" required placeholder="Full title of your capstone research paper">
            </div>

            <div class="form-group">
              <label>Authors <span style="font-weight:400;color:var(--gray-400);">(separate with semicolons: Last, First; Last, First)</span></label>
              <input type="text" name="authors" required placeholder="e.g. Dela Cruz, Juan; Santos, Maria">
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>College / Department</label>
                <select name="department" required>
                  <option value="">Select Department</option>
                  <option value="CCS">CCS — Computing Studies</option>
                  <option value="CENG">CENG — Engineering</option>
                  <option value="CAS">CAS — Arts and Sciences</option>
                  <option value="CHS">CHS — Health Sciences</option>
                  <option value="CTDE">CTDE — Teacher Development</option>
                  <option value="CTHM">CTHM — Tourism &amp; Hospitality</option>
                  <option value="CBA">CBA — Business Administration</option>
                </select>
              </div>
              <div class="form-group">
                <label>Academic Year</label>
                <input type="number" name="year" required min="2000" max="{{ date('Y') + 1 }}" value="{{ date('Y') }}">
              </div>
            </div>

            <div class="form-group">
              <label>Program</label>
              <select name="program" required>
                <option value="">Select Program</option>
                <optgroup label="CCS — College of Computing Studies">
                  <option value="Bachelor of Science in Information Technology">Bachelor of Science in Information Technology</option>
                  <option value="Bachelor of Science in Computer Science">Bachelor of Science in Computer Science</option>
                </optgroup>
                <optgroup label="CENG — College of Engineering">
                  <option value="Bachelor of Science in Civil Engineering">Bachelor of Science in Civil Engineering</option>
                  <option value="Bachelor of Science in Electrical Engineering">Bachelor of Science in Electrical Engineering</option>
                  <option value="Bachelor of Science in Mechanical Engineering">Bachelor of Science in Mechanical Engineering</option>
                </optgroup>
                <optgroup label="CAS — College of Arts and Sciences">
                  <option value="Bachelor of Arts in Communication">Bachelor of Arts in Communication</option>
                  <option value="Bachelor of Science in Biology">Bachelor of Science in Biology</option>
                </optgroup>
                <optgroup label="CHS — College of Health Sciences">
                  <option value="Bachelor of Science in Nursing">Bachelor of Science in Nursing</option>
                </optgroup>
                <optgroup label="CTDE — College of Teacher Development and Education">
                  <option value="Bachelor of Elementary Education">Bachelor of Elementary Education</option>
                  <option value="Bachelor of Secondary Education">Bachelor of Secondary Education</option>
                </optgroup>
                <optgroup label="CTHM — College of Tourism, Hospitality, and Management">
                  <option value="Bachelor of Science in Tourism Management">Bachelor of Science in Tourism Management</option>
                  <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                </optgroup>
                <optgroup label="CBA — College of Business Administration">
                  <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                </optgroup>
              </select>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Adviser</label>
                <input type="text" name="adviser" required placeholder="Prof. Name">
              </div>
              <div class="form-group">
                <label>Number of Pages</label>
                <input type="number" name="pages" required min="1" placeholder="e.g. 120">
              </div>
            </div>

            <div class="form-group">
              <label>Keywords <span style="font-weight:400;color:var(--gray-400);">(comma-separated)</span></label>
              <input type="text" name="keywords" required placeholder="e.g. Machine Learning, IoT, Agriculture">
            </div>

            <div class="form-group">
              <label>Abstract</label>
              <textarea name="abstract" rows="5" required placeholder="Brief description of your research paper…"></textarea>
            </div>

            <div class="form-actions">
              <a href="{{ route('student.dashboard') }}" class="btn btn-outline">Cancel</a>
              <button type="submit" class="btn btn-primary">Submit for Review</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <footer style="padding:1rem 2rem;font-size:0.8rem;color:var(--gray-400);border-top:1px solid var(--gray-200);background:rgba(255,255,255,0.98);">
      AMSDEN &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </div>
</div>

<script>
function handleFile(input) {
  const file = input.files[0];
  if (file) {
    const info = document.getElementById('fileInfo');
    info.style.display = 'block';
    info.textContent = '✓ ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
  }
}
</script>

@include('partials.footer')
