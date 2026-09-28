@php $title = 'Manage Bluebooks'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.admin-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">Bluebook Management</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ ($user['role'] ?? '') === 'Sub-Admin' ? 'Sub-Admin' : 'Administrator' }}</span>
      </div>
    </header>

    <div class="content">
      @php
        $canReview = \App\Models\User::allows($user, 'review_bluebooks');
        $canManage = \App\Models\User::allows($user, 'manage_bluebooks');
      @endphp
      <x-page-hero heading="Bluebooks"
                   sub="{{ count($bluebooks) }} {{ Str::plural('record', count($bluebooks)) }} in the archive.">
        @if($canManage)
        <x-slot name="action">
          <a href="{{ route('admin.bluebooks.new') }}" class="btn btn-sm btn-primary">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add Bluebook
          </a>
        </x-slot>
        @endif
      </x-page-hero>

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif

      {{-- Submissions waiting for review live on their own page. --}}
      @if(($pendingCount ?? 0) > 0)
        <div class="queue-links">
          <a href="{{ route('admin.pending') }}"><span class="badge badge-yellow">{{ $pendingCount }}</span> pending review &rarr;</a>
        </div>
      @endif

      <form method="GET" action="{{ route('admin.bluebooks') }}" class="filter-bar">
        <div class="filter-group grow">
          <label for="bluebooks-filter-search">Search</label>
          <input id="bluebooks-filter-search" type="text" name="search" value="{{ $query['search'] ?? '' }}" placeholder="Title, author, keyword…">
        </div>
        <div class="filter-group">
          <label for="bluebooks-filter-department">Department</label>
          <select id="bluebooks-filter-department" name="department">
            <option value="">All Departments</option>
            @foreach(config('departments') as $code => $dept)
              <option value="{{ $code }}" {{ ($query['department'] ?? '') === $code ? 'selected' : '' }}>{{ $code === $dept['name'] ? $code : "$code — {$dept['name']}" }}</option>
            @endforeach
          </select>
        </div>
        <div class="filter-group">
          <label for="bluebooks-filter-year">Year</label>
          <select id="bluebooks-filter-year" name="year">
            <option value="">All Years</option>
            @foreach($years as $y)
              <option value="{{ $y }}" {{ ($query['year'] ?? '') == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
          </select>
        </div>
        <div class="filter-group">
          <label for="bluebooks-filter-status">Status</label>
          <select id="bluebooks-filter-status" name="status">
            <option value="">All Status</option>
            @foreach(['Approved' => 'Posted', \App\Models\Bluebook::STATUS_AWAITING_WAIVER => 'Awaiting Waiver'] as $s => $label)
              <option value="{{ $s }}" {{ ($query['status'] ?? '') === $s ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:flex-end;">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="{{ route('admin.bluebooks') }}" class="btn btn-outline btn-sm">Clear</a>
        </div>
      </form>

      @if(count($bluebooks) > 0)
        <div class="card">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Title</th>
                  <th>Authors</th>
                  <th>Dept</th>
                  <th>Year</th>
                  <th>Status</th>
                  <th>Views</th>
                  <th>OCR</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @foreach($bluebooks as $i => $b)
                  <tr>
                    <td class="mono">{{ $i + 1 }}</td>
                    <td style="max-width:220px;">
                      <a href="{{ route('admin.bluebooks.view', $b['id']) }}" title="Read {{ $b['title'] }}" style="display:block;font-weight:600;color:var(--primary-dark);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $b['title'] }}</a>
                      <div class="clip" style="max-width:220px;font-size:0.78rem;color:var(--gray-400);margin-top:0.2rem;" title="{{ $b['program'] }}">{{ $b['program'] }}</div>
                    </td>
                    <td style="font-size:0.83rem;"><div class="clip" style="max-width:140px;" title="{{ implode('; ', $b['authors']) }}">{{ implode(', ', array_slice($b['authors'], 0, 2)) }}{{ count($b['authors']) > 2 ? '…' : '' }}</div></td>
                    <td><span class="badge badge-blue">{{ $b['department'] }}</span></td>
                    <td>{{ $b['year'] }}</td>
                    <td>
                      @if($b['status'] === 'Approved') <span class="badge badge-green">Posted</span>
                      @else <span class="badge badge-blue">Awaiting Waiver</span>
                      @endif
                    </td>
                    <td>{{ $b['views'] }}</td>
                    <td>
                      @if($b['ocrStatus'] === 'completed') <span class="badge badge-green">Completed</span>
                      @elseif($b['ocrStatus'] === 'processing') <span class="badge badge-yellow">Processing</span>
                      @elseif($b['ocrStatus'] === 'failed') <span class="badge badge-red">Failed</span>
                      @else <span class="badge badge-blue">Pending</span>
                      @endif
                    </td>
                    <td>
                      <div class="td-actions">
                        @if($canReview || $canManage)
                          <a href="{{ route('admin.bluebooks.edit', $b['id']) }}" class="btn btn-outline btn-sm">{{ $canManage ? 'Edit' : 'Waiver' }}</a>
                        @else
                          <a href="{{ route('admin.bluebooks.view', $b['id']) }}" class="btn btn-outline btn-sm">View</a>
                        @endif
                        @if(!$canReview)
                          {{-- Reviewing is not this account's to do. --}}
                        @elseif($b['status'] === \App\Models\Bluebook::STATUS_AWAITING_WAIVER && !$b['waiverRecorded'])
                          <a href="{{ route('admin.bluebooks.edit', $b['id']) }}" class="btn btn-primary btn-sm" title="Record the access level from the signed waiver first">Set Waiver Level</a>
                        @elseif($b['status'] === \App\Models\Bluebook::STATUS_AWAITING_WAIVER)
                          <form method="POST" action="{{ route('admin.bluebooks.waiverReceived', $b['id']) }}" style="display:inline;">
                            @csrf <button type="submit" class="btn btn-success btn-sm" title="The signed waiver was handed in; post this bluebook in Browse">Waiver Received</button>
                          </form>
                        @endif
                        @if($canManage && $b['status'] === 'Approved' && $b['hasFile'] && $b['ocrStatus'] !== 'processing')
                          <form method="POST" action="{{ route('admin.bluebooks.reprocessOcr', $b['id']) }}" style="display:inline;">
                            @csrf <button type="submit" class="btn btn-outline btn-sm">Reprocess OCR</button>
                          </form>
                        @endif
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @else
        <div class="card">
          <div class="empty-state">
            <div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" fill="currentColor" fill-opacity="0.18"/><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
            {{-- Filtered to nothing and genuinely empty are different problems,
                 and the way out of one is not the way out of the other. --}}
            @php $isFiltered = collect($query ?? [])->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty(); @endphp
            @if($isFiltered)
              <p>No bluebooks match these filters.</p>
              <p style="font-size:0.8rem;color:var(--gray-400);margin-top:0.35rem;">Try a different status, department or year.</p>
              <a href="{{ route('admin.bluebooks') }}" class="btn btn-outline btn-sm" style="margin-top:0.85rem;">Clear all filters</a>
            @else
              <p>The archive has no records yet.</p>
              <a href="{{ route('admin.bluebooks.new') }}" class="btn btn-primary btn-sm" style="margin-top:0.85rem;">Add the first bluebook</a>
            @endif
          </div>
        </div>
      @endif
    </div>

    <footer class="app-footer">
      C-BAMS &copy; {{ date('Y') }} &mdash; Camarines Sur Polytechnic Colleges. All Rights Reserved.
    </footer>
  </main>
</div>

@include('partials.footer')
