@php $title = 'Admin Dashboard'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.admin-sidebar')
  <div class="main">
    <header class="topbar">
      <h1 class="topbar-title">Dashboard</h1>
      <div class="topbar-right">
        <span class="topbar-badge">Administrator</span>
        <span style="font-size:0.85rem;color:var(--gray-600);">{{ $user['email'] }}</span>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div>
          <h1>Welcome, {{ explode(' ', $user['name'])[0] }}</h1>
          <p>Here's what's happening in the AMSDEN archive today.</p>
        </div>
        <a href="{{ route('admin.bluebooks.new') }}" class="btn btn-primary">
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Add Bluebook
        </a>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon blue">📚</div>
          <div class="stat-body">
            <div class="value">{{ $stats['totalBluebooks'] }}</div>
            <div class="label">Total Bluebooks</div>
          </div>
        </div>
        <div class="stat-card" style="--c:var(--green)">
          <div class="stat-icon green">✅</div>
          <div class="stat-body">
            <div class="value" style="color:var(--green)">{{ $stats['approved'] }}</div>
            <div class="label">Approved</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon yellow">⏳</div>
          <div class="stat-body">
            <div class="value" style="color:var(--yellow)">{{ $stats['pending'] }}</div>
            <div class="label">Pending Review</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue">👥</div>
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
            <div class="empty-state"><div class="icon">📭</div><p>No bluebooks yet</p></div>
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
              <div class="empty-state"><div class="icon">📋</div><p>No recent activity</p></div>
            @endif
          </div>
        </div>
      </div>
    </div>

    <footer style="padding:1rem 2rem;font-size:0.8rem;color:var(--gray-400);border-top:1px solid var(--gray-200);background:rgba(255,255,255,0.98);">
      AMSDEN &copy; {{ date('Y') }} &mdash; Camarines Sur Polytechnic Colleges. All Rights Reserved.
    </footer>
  </div>
</div>

@include('partials.footer')
