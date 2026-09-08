@php $title = $bluebook ? 'Edit Bluebook' : 'Add Bluebook'; $isEdit = !!$bluebook; @endphp
@include('partials.head')

<div class="app">
  @include('partials.admin-sidebar')
  <main class="main" id="main-content">
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
          <span class="icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" fill="currentColor" fill-opacity="0.18"/><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
          <h2>{{ $isEdit ? 'Edit Record' : 'Bluebook Details' }}</h2>
        </div>
        <div class="form-card-body">
          <form method="POST" action="{{ $isEdit ? route('admin.bluebooks.update', $bluebook['id']) : route('admin.bluebooks.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="form-group">
              <label for="bb-form-file">PDF File <span style="font-weight:400;color:var(--gray-400);">(optional{{ $isEdit ? ' — leave blank to keep the current file' : '' }})</span></label>
              @if($isEdit && $bluebook['hasFile'])
                <div style="font-size:0.85rem;color:var(--gray-600);margin-bottom:0.4rem;">Current file: {{ $bluebook['fileOriginalName'] }}</div>
              @endif
              <input id="bb-form-file" type="file" name="file" accept=".pdf,application/pdf">
            </div>

            <div class="form-group">
              <label for="bb-form-title">Title</label>
              <input id="bb-form-title" type="text" name="title" value="{{ $bluebook['title'] ?? '' }}" placeholder="Full research paper title" required>
            </div>

            <div class="form-group">
              <label for="bb-form-authors">Authors <span style="font-weight:400;color:var(--gray-400);">(separate with semicolons)</span></label>
              <input id="bb-form-authors" type="text" name="authors" value="{{ $bluebook ? implode('; ', $bluebook['authors']) : '' }}" placeholder="Last, First; Last, First" required>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="bb-form-department">Department</label>
                <select id="bb-form-department" name="department" required>
                  <option value="">Select Department</option>
                  @foreach(config('departments') as $code => $dept)
                    <option value="{{ $code }}" {{ ($bluebook['department'] ?? '') === $code ? 'selected' : '' }}>{{ $code === $dept['name'] ? $code : "$code — {$dept['name']}" }}</option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label for="bb-form-year">Year</label>
                <input id="bb-form-year" type="number" name="year" value="{{ $bluebook['year'] ?? date('Y') }}" min="2000" max="{{ date('Y') + 1 }}" required>
              </div>
            </div>

            <div class="form-group">
              <label for="bb-form-program">Program</label>
              <select id="bb-form-program" name="program" required>
                <option value="">Select Program</option>
                @foreach(config('departments') as $code => $dept)
                  <optgroup label="{{ $code === $dept['name'] ? $code : "$code — {$dept['name']}" }}">
                    @foreach($dept['programs'] as $program)
                      <option value="{{ $program }}" {{ ($bluebook['program'] ?? '') === $program ? 'selected' : '' }}>{{ $program }}</option>
                    @endforeach
                  </optgroup>
                @endforeach
              </select>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="bb-form-adviser">Adviser</label>
                <input id="bb-form-adviser" type="text" name="adviser" value="{{ $bluebook['adviser'] ?? '' }}" placeholder="Prof. Name" required>
              </div>
              <div class="form-group">
                <label for="bb-form-pages">Pages</label>
                <input id="bb-form-pages" type="number" name="pages" value="{{ $bluebook['pages'] ?? '' }}" placeholder="e.g. 120" min="1" required>
              </div>
            </div>

            <div class="form-group">
              <label for="bb-form-keywords">Keywords <span style="font-weight:400;color:var(--gray-400);">(comma-separated)</span></label>
              <input id="bb-form-keywords" type="text" name="keywords" value="{{ $bluebook ? implode(', ', $bluebook['keywords']) : '' }}" placeholder="e.g. AI, Machine Learning, CNN" required>
            </div>

            <div class="form-group">
              <label for="bb-form-abstract">Abstract</label>
              <textarea id="bb-form-abstract" name="abstract" rows="5" placeholder="Brief description of the research paper…" required>{{ $bluebook['abstract'] ?? '' }}</textarea>
            </div>

            <div class="form-actions">
              <a href="{{ route('admin.bluebooks') }}" class="btn btn-outline">Cancel</a>
              <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Bluebook' : 'Add Bluebook' }}</button>
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

@include('partials.footer')
