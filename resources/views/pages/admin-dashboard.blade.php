@php $title = 'Admin Dashboard'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.admin-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">Dashboard</h1>
      <div class="topbar-right">
        <span class="topbar-badge">Administrator</span>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div>
          <h1>Welcome, {{ explode(' ', $user['name'])[0] }}</h1>
          <p>Here's what's happening in the C-BAMS archive today.</p>
        </div>
        <a href="{{ route('admin.bluebooks.new') }}" class="btn btn-primary">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Add Bluebook
        </a>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Recent Bluebooks</h3>
            <a href="{{ route('admin.bluebooks') }}" style="font-size:0.82rem;color:var(--primary);">View all</a>
          </div>
          @if(count($stats['recentBluebooks']) > 0)
            <div class="table-wrap">
              <table>
                <thead><tr><th>Title</th><th>Status</th><th>Year</th></tr></thead>
                <tbody>
                  @foreach($stats['recentBluebooks'] as $b)
                    <tr data-href="{{ route('admin.bluebooks') }}">
                      <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $b['title'] }}</td>
                      <td>
                        @if($b['status'] === 'Approved') <span class="badge badge-green">Approved</span>
                        @elseif($b['status'] === 'Pending') <span class="badge badge-yellow">Pending</span>
                        @else <span class="badge badge-red">Rejected</span>
                        @endif
                      </td>
                      <td>{{ $b['year'] }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <div class="empty-state"><div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" fill="currentColor" fill-opacity="0.18"/><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><p>No bluebooks yet</p></div>
          @endif
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Recent Activity</h3>
            <a href="{{ route('admin.logs') }}" style="font-size:0.82rem;color:var(--primary);">View all</a>
          </div>
          <div class="card-body" style="padding:0 1.5rem;">
            @if(count($stats['recentLogs']) > 0)
              <ul class="activity-list">
                @foreach($stats['recentLogs'] as $log)
                  <li class="activity-item">
                    <div class="activity-dot"></div>
                    <div class="activity-info">
                      <div class="action">{{ $log['action'] }}
                        @if($log['document'] !== '—') — <em style="font-weight:400;">{{ Str::limit($log['document'], 40) }}</em> @endif
                      </div>
                      <div class="meta">{{ $log['userName'] }} &bull; {{ $log['timestamp'] }}</div>
                    </div>
                  </li>
                @endforeach
              </ul>
            @else
              <div class="empty-state"><div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" fill="currentColor" fill-opacity="0.18"/><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><p>No recent activity</p></div>
            @endif
          </div>
        </div>
      </div>
    </div>

    <footer class="app-footer">
      C-BAMS &copy; {{ date('Y') }} &mdash; Camarines Sur Polytechnic Colleges. All Rights Reserved.
    </footer>
  </main>
</div>

@include('partials.footer')
