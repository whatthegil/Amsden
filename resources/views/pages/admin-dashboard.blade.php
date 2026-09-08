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

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M11.25 4.533A9.707 9.707 0 0 0 6 3a9.735 9.735 0 0 0-3.25.555.75.75 0 0 0-.5.707v14.25a.75.75 0 0 0 1 .707A8.237 8.237 0 0 1 6 18.75c1.995 0 3.823.707 5.25 1.886V4.533ZM12.75 20.636A8.214 8.214 0 0 1 18 18.75c.966 0 1.89.166 2.75.47a.75.75 0 0 0 1-.708V4.262a.75.75 0 0 0-.5-.707A9.735 9.735 0 0 0 18 3a9.707 9.707 0 0 0-5.25 1.533v16.103Z"/></svg></div>
          <div class="stat-body">
            <div class="value">{{ $stats['totalBluebooks'] }}</div>
            <div class="label">Total Bluebooks</div>
          </div>
        </div>
        <div class="stat-card" style="--c:var(--green)">
          <div class="stat-icon green"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M8.603 3.799A4.49 4.49 0 0 1 12 2.25c1.357 0 2.573.6 3.397 1.549a4.49 4.49 0 0 1 3.498 1.307 4.491 4.491 0 0 1 1.307 3.497A4.49 4.49 0 0 1 21.75 12a4.49 4.49 0 0 1-1.549 3.397 4.491 4.491 0 0 1-1.307 3.497 4.491 4.491 0 0 1-3.497 1.307A4.49 4.49 0 0 1 12 21.75a4.49 4.49 0 0 1-3.397-1.549 4.49 4.49 0 0 1-3.498-1.306 4.491 4.491 0 0 1-1.307-3.498A4.49 4.49 0 0 1 2.25 12c0-1.357.6-2.573 1.549-3.397a4.49 4.49 0 0 1 1.307-3.497 4.49 4.49 0 0 1 3.497-1.307Zm7.007 6.387a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd"/></svg></div>
          <div class="stat-body">
            <div class="value" style="color:var(--green)">{{ $stats['approved'] }}</div>
            <div class="label">Approved</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon yellow"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM12.75 6a.75.75 0 0 0-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V6Z" clip-rule="evenodd"/></svg></div>
          <div class="stat-body">
            <div class="value" style="color:var(--yellow)">{{ $stats['pending'] }}</div>
            <div class="label">Pending Review</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.01.75.75 0 0 0 .42-.643 4.875 4.875 0 0 0-6.957-4.611 8.586 8.586 0 0 1 1.71 5.157v.003Z"/></svg></div>
          <div class="stat-body">
            <div class="value">{{ $stats['totalUsers'] }}</div>
            <div class="label">Registered Users</div>
          </div>
        </div>
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
