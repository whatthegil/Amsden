@php $title = 'Access Logs'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.admin-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">Access Logs</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ ($user['role'] ?? '') === 'Sub-Admin' ? 'Sub-Admin' : 'Administrator' }}</span>
      </div>
    </header>

    <div class="content">
      <x-page-hero heading="Access Logs"
                   sub="{{ number_format($total) }} {{ Str::plural('entry', $total) }} — who opened what, and when." />

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
                    <td class="mono">{{ $offset + $i + 1 }}</td>
                    <td style="font-weight:600;white-space:nowrap;">{{ $log['userName'] }}</td>
                    <td class="mono" style="font-size:0.82rem;">{{ $log['email'] }}</td>
                    <td><span class="badge badge-blue">{{ $log['action'] }}</span></td>
                    <td style="font-size:0.85rem;"><div class="clip" style="max-width:150px;" title="{{ $log['document'] }}">{{ $log['document'] }}</div></td>
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
          @if($pages > 1)
            <div class="pager">
              <span>Showing {{ number_format($offset + 1) }}&ndash;{{ number_format($offset + count($logs)) }} of {{ number_format($total) }}</span>
              <div class="pager-links">
                @if($page > 1)
                  <a class="btn btn-outline btn-sm" href="{{ route('admin.logs', array_merge($query, ['page' => $page - 1])) }}">&larr; Newer</a>
                @endif
                <span class="pager-page">Page {{ $page }} of {{ $pages }}</span>
                @if($page < $pages)
                  <a class="btn btn-outline btn-sm" href="{{ route('admin.logs', array_merge($query, ['page' => $page + 1])) }}">Older &rarr;</a>
                @endif
              </div>
            </div>
          @endif
        </div>
      @else
        <div class="card">
          <div class="empty-state">
            <div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" fill="currentColor" fill-opacity="0.18"/><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
            @php $isFiltered = collect($query ?? [])->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty(); @endphp
            @if($isFiltered)
              <p>No entries match these filters.</p>
              <a href="{{ route('admin.logs') }}" class="btn btn-outline btn-sm" style="margin-top:0.85rem;">Clear all filters</a>
            @else
              <p>Nothing has been recorded yet.</p>
              <p style="font-size:0.8rem;color:var(--gray-400);margin-top:0.35rem;">Logins, document views and capture attempts all appear here as they happen.</p>
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
