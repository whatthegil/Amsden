@php $title = 'Access Logs'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.admin-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">Access Logs</h1>
      <div class="topbar-right">
        <span class="topbar-badge">Administrator</span>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div>
          <h1>Access Logs</h1>
          <p>{{ count($logs) }} log entr{{ count($logs) === 1 ? 'y' : 'ies' }} found</p>
        </div>
      </div>

      <form method="GET" action="{{ route('admin.logs') }}" class="filter-bar">
        <div class="filter-group grow">
          <label for="logs-filter-search">Search</label>
          <input id="logs-filter-search" type="text" name="search" value="{{ $query['search'] ?? '' }}" placeholder="Name, email, or document…">
        </div>
        <div class="filter-group">
          <label for="logs-filter-action">Action</label>
          <select id="logs-filter-action" name="action">
            <option value="">All Actions</option>
            @foreach($actions as $a)
              <option value="{{ $a }}" {{ ($query['action'] ?? '') === $a ? 'selected' : '' }}>{{ $a }}</option>
            @endforeach
          </select>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:flex-end;">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="{{ route('admin.logs') }}" class="btn btn-outline btn-sm">Clear</a>
        </div>
      </form>

      @if(count($logs) > 0)
        <div class="card">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>User</th>
                  <th>Email</th>
                  <th>Action</th>
                  <th>Document</th>
                  <th>Timestamp</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @foreach($logs as $i => $log)
                  <tr>
                    <td class="mono">{{ $i + 1 }}</td>
                    <td style="font-weight:600;">{{ $log['userName'] }}</td>
                    <td class="mono" style="font-size:0.82rem;">{{ $log['email'] }}</td>
                    <td><span class="badge badge-blue">{{ $log['action'] }}</span></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.85rem;">{{ $log['document'] }}</td>
                    <td class="mono" style="font-size:0.82rem;white-space:nowrap;">{{ $log['timestamp'] }}</td>
                    <td>
                      @php
                        $statusBadge = match($log['status']) {
                          'Success' => 'badge-green',
                          'Denied', 'Flagged' => 'badge-red',
                          default => 'badge-gray',
                        };
                      @endphp
                      <span class="badge {{ $statusBadge }}">{{ $log['status'] }}</span>
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
            <div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" fill="currentColor" fill-opacity="0.18"/><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
            <p>No logs found matching your filters.</p>
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
