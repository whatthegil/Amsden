@php $title = $bluebook ? 'Edit Bluebook' : 'Add Bluebook'; $isEdit = !!$bluebook; @endphp
@include('partials.head')

<div class="app">
  @include('partials.admin-sidebar')
  <div class="main">
    <header class="topbar">
      <h1 class="topbar-title">{{ $isEdit ? 'Edit' : 'Add' }} Bluebook</h1>
      <div class="topbar-right">
        <span class="topbar-badge">Administrator</span>
      </div>
    </header>

    <div class="content form-page">
      <div class="page-header">
        <div>
          <h1>{{ $isEdit ? 'Edit Bluebook' : 'Add New Bluebook' }}</h1>
          <p>{{ $isEdit ? 'Update the details of this research paper.' : 'Register a new capstone research paper.' }}</p>
        </div>
        <a href="{{ route('admin.bluebooks') }}" class="btn btn-outline">← Back</a>
      </div>

      <div class="form-card">
        <div class="form-card-header">
          <span class="icon">📘</span>
          <h2>{{ $isEdit ? 'Edit Record' : 'Bluebook Details' }}</h2>
        </div>
        <div class="form-card-body">
          <form method="POST" action="{{ $isEdit ? route('admin.bluebooks.update', $bluebook['id']) : route('admin.bluebooks.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="form-group">
              <label>PDF File <span style="font-weight:400;color:var(--gray-400);">(optional{{ $isEdit ? ' — leave blank to keep the current file' : '' }})</span></label>
              @if($isEdit && $bluebook['hasFile'])
                <div style="font-size:0.85rem;color:var(--gray-600);margin-bottom:0.4rem;">Current file: {{ $bluebook['fileOriginalName'] }}</div>
              @endif
              <input type="file" name="file" accept=".pdf,application/pdf">
            </div>

            <div class="form-group">
              <label>Title</label>
              <input type="text" name="title" value="{{ $bluebook['title'] ?? '' }}" placeholder="Full research paper title" required>
            </div>

            <div class="form-group">
              <label>Authors <span style="font-weight:400;color:var(--gray-400);">(separate with semicolons)</span></label>
              <input type="text" name="authors" value="{{ $bluebook ? implode('; ', $bluebook['authors']) : '' }}" placeholder="Last, First; Last, First" required>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Department</label>
                <select name="department" required>
                  <option value="">Select Department</option>
                  @foreach(['CCS','CENG','CAS','CHS','CTDE','CTHM','CBA'] as $dept)
                    <option value="{{ $dept }}" {{ ($bluebook['department'] ?? '') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label>Year</label>
                <input type="number" name="year" value="{{ $bluebook['year'] ?? date('Y') }}" min="2000" max="{{ date('Y') + 1 }}" required>
              </div>
            </div>

            <div class="form-group">
              <label>Program</label>
              <select name="program" required>
                <option value="">Select Program</option>
                <optgroup label="CCS — College of Computing Studies">
                  <option value="Bachelor of Science in Information Technology" {{ ($bluebook['program'] ?? '') === 'Bachelor of Science in Information Technology' ? 'selected' : '' }}>Bachelor of Science in Information Technology</option>
                  <option value="Bachelor of Science in Computer Science" {{ ($bluebook['program'] ?? '') === 'Bachelor of Science in Computer Science' ? 'selected' : '' }}>Bachelor of Science in Computer Science</option>
                </optgroup>
                <optgroup label="CENG — College of Engineering">
                  <option value="Bachelor of Science in Civil Engineering" {{ ($bluebook['program'] ?? '') === 'Bachelor of Science in Civil Engineering' ? 'selected' : '' }}>Bachelor of Science in Civil Engineering</option>
                  <option value="Bachelor of Science in Electrical Engineering" {{ ($bluebook['program'] ?? '') === 'Bachelor of Science in Electrical Engineering' ? 'selected' : '' }}>Bachelor of Science in Electrical Engineering</option>
                  <option value="Bachelor of Science in Mechanical Engineering" {{ ($bluebook['program'] ?? '') === 'Bachelor of Science in Mechanical Engineering' ? 'selected' : '' }}>Bachelor of Science in Mechanical Engineering</option>
                </optgroup>
                <optgroup label="CAS — College of Arts and Sciences">
                  <option value="Bachelor of Arts in Communication" {{ ($bluebook['program'] ?? '') === 'Bachelor of Arts in Communication' ? 'selected' : '' }}>Bachelor of Arts in Communication</option>
                  <option value="Bachelor of Science in Biology" {{ ($bluebook['program'] ?? '') === 'Bachelor of Science in Biology' ? 'selected' : '' }}>Bachelor of Science in Biology</option>
                </optgroup>
                <optgroup label="CHS — College of Health Sciences">
                  <option value="Bachelor of Science in Nursing" {{ ($bluebook['program'] ?? '') === 'Bachelor of Science in Nursing' ? 'selected' : '' }}>Bachelor of Science in Nursing</option>
                </optgroup>
                <optgroup label="CTDE — College of Teacher Development and Education">
                  <option value="Bachelor of Elementary Education" {{ ($bluebook['program'] ?? '') === 'Bachelor of Elementary Education' ? 'selected' : '' }}>Bachelor of Elementary Education</option>
                  <option value="Bachelor of Secondary Education" {{ ($bluebook['program'] ?? '') === 'Bachelor of Secondary Education' ? 'selected' : '' }}>Bachelor of Secondary Education</option>
                </optgroup>
                <optgroup label="CTHM — College of Tourism, Hospitality, and Management">
                  <option value="Bachelor of Science in Tourism Management" {{ ($bluebook['program'] ?? '') === 'Bachelor of Science in Tourism Management' ? 'selected' : '' }}>Bachelor of Science in Tourism Management</option>
                  <option value="Bachelor of Science in Hospitality Management" {{ ($bluebook['program'] ?? '') === 'Bachelor of Science in Hospitality Management' ? 'selected' : '' }}>Bachelor of Science in Hospitality Management</option>
                </optgroup>
                <optgroup label="CBA — College of Business Administration">
                  <option value="Bachelor of Science in Business Administration" {{ ($bluebook['program'] ?? '') === 'Bachelor of Science in Business Administration' ? 'selected' : '' }}>Bachelor of Science in Business Administration</option>
                </optgroup>
              </select>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Adviser</label>
                <input type="text" name="adviser" value="{{ $bluebook['adviser'] ?? '' }}" placeholder="Prof. Name" required>
              </div>
              <div class="form-group">
                <label>Pages</label>
                <input type="number" name="pages" value="{{ $bluebook['pages'] ?? '' }}" placeholder="e.g. 120" min="1" required>
              </div>
            </div>

            <div class="form-group">
              <label>Keywords <span style="font-weight:400;color:var(--gray-400);">(comma-separated)</span></label>
              <input type="text" name="keywords" value="{{ $bluebook ? implode(', ', $bluebook['keywords']) : '' }}" placeholder="e.g. AI, Machine Learning, CNN" required>
            </div>

            <div class="form-group">
              <label>Abstract</label>
              <textarea name="abstract" rows="5" placeholder="Brief description of the research paper…" required>{{ $bluebook['abstract'] ?? '' }}</textarea>
            </div>

            <div class="form-actions">
              <a href="{{ route('admin.bluebooks') }}" class="btn btn-outline">Cancel</a>
              <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Bluebook' : 'Add Bluebook' }}</button>
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

@include('partials.footer')
