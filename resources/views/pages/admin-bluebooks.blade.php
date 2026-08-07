@php $title = 'Manage Bluebooks'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.admin-sidebar')
  <div class="main">
    <header class="topbar">
      <h1 class="topbar-title">Bluebook Management</h1>
      <div class="topbar-right">
        <span class="topbar-badge">Administrator</span>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div>
          <h1>Bluebooks</h1>
          <p>{{ count($bluebooks) }} record(s) found</p>
        </div>
        <a href="{{ route('admin.bluebooks.new') }}" class="btn btn-primary">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Add Bluebook
        </a>
      </div>

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif

      <form method="GET" action="{{ route('admin.bluebooks') }}" class="filter-bar">
        <div class="filter-group grow">
          <label>Search</label>
          <input type="text" name="search" value="{{ $query['search'] ?? '' }}" placeholder="Title, author, keyword…">
        </div>
        <div class="filter-group">
          <label>Department</label>
          <select name="department">
            <option value="">All Departments</option>
            @foreach(['CCS','CENG','CAS','CHS','CTDE','CTHM','CBA'] as $dept)
              <option value="{{ $dept }}" {{ ($query['department'] ?? '') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
            @endforeach
          </select>
        </div>
        <div class="filter-group">
          <label>Year</label>
          <select name="year">
            <option value="">All Years</option>
            @foreach($years as $y)
              <option value="{{ $y }}" {{ ($query['year'] ?? '') == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endforeach
          </select>
        </div>
        <div class="filter-group">
          <label>Status</label>
          <select name="status">
            <option value="">All Status</option>
            @foreach(['Approved','Pending','Rejected'] as $s)
              <option value="{{ $s }}" {{ ($query['status'] ?? '') === $s ? 'selected' : '' }}>{{ $s }}</option>
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
                    <td style="max-width:260px;">
                      <div style="font-weight:600;color:var(--primary-dark);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $b['title'] }}</div>
                      <div style="font-size:0.78rem;color:var(--gray-400);margin-top:0.2rem;">{{ $b['program'] }}</div>
                    </td>
                    <td style="font-size:0.83rem;">{{ implode(', ', array_slice($b['authors'], 0, 2)) }}{{ count($b['authors']) > 2 ? '…' : '' }}</td>
                    <td><span class="badge badge-blue">{{ $b['department'] }}</span></td>
                    <td>{{ $b['year'] }}</td>
                    <td>
                      @if($b['status'] === 'Approved') <span class="badge badge-green">Approved</span>
                      @elseif($b['status'] === 'Pending') <span class="badge badge-yellow">Pending</span>
                      @else <span class="badge badge-red">Rejected</span>
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
                        <a href="{{ route('admin.bluebooks.edit', $b['id']) }}" class="btn btn-outline btn-sm">Edit</a>
                        @if($b['status'] === 'Pending')
                          <form method="POST" action="{{ route('admin.bluebooks.approve', $b['id']) }}" style="display:inline;">
                            @csrf <button type="submit" class="btn btn-success btn-sm">Approve</button>
                          </form>
                          <form method="POST" action="{{ route('admin.bluebooks.reject', $b['id']) }}" style="display:inline;">
                            @csrf <button type="submit" class="btn btn-warning btn-sm">Reject</button>
                          </form>
                        @endif
                        @if($b['hasFile'] && $b['ocrStatus'] !== 'processing')
                          <form method="POST" action="{{ route('admin.bluebooks.reprocessOcr', $b['id']) }}" style="display:inline;">
                            @csrf <button type="submit" class="btn btn-outline btn-sm">Reprocess OCR</button>
                          </form>
                        @endif
                        <form method="POST" action="{{ route('admin.bluebooks.delete', $b['id']) }}" style="display:inline;">
                          @csrf <button type="submit" class="btn btn-danger btn-sm confirm-delete">Delete</button>
                        </form>
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
            <div class="icon">📭</div>
            <p>No bluebooks found matching your filters.</p>
          </div>
        </div>
      @endif
    </div>

    <footer style="padding:1rem 2rem;font-size:0.8rem;color:var(--gray-400);border-top:1px solid var(--gray-200);background:rgba(255,255,255,0.98);">
      AMSDEN &copy; {{ date('Y') }} &mdash; CSPC. All Rights Reserved.
    </footer>
  </div>
</div>

@include('partials.footer')
