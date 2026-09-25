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
      <div class="dash-hero">
        <div class="dash-hero-top" style="margin-bottom:0;">
          <div>
            <h1>Welcome, {{ explode(' ', $user['name'])[0] }}</h1>
            <p>
              {{ $stats['totalBluebooks'] }} {{ Str::plural('bluebook', $stats['totalBluebooks']) }} in the archive &middot;
              {{ $stats['approved'] }} approved &middot;
              {{ $stats['totalUsers'] }} registered {{ Str::plural('user', $stats['totalUsers']) }}
            </p>
          </div>
          <a href="{{ route('admin.bluebooks.new') }}" class="btn btn-sm btn-on-hero">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add Bluebook
          </a>
        </div>
      </div>

      {{-- What is actually waiting on this administrator, with the way to deal
           with it attached. A counter that always reads zero is noise; this
           appears only when there is something to do. --}}
      @if($stats['pending'] > 0)
        <div class="callout">
          <div class="callout-icon">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
          </div>
          <div class="callout-body">
            <strong>{{ $stats['pending'] }} {{ Str::plural('bluebook', $stats['pending']) }} awaiting review</strong>
            <span>Submitted by students and not yet approved or rejected.</span>
          </div>
          <a href="{{ route('admin.bluebooks') }}?status=Pending" class="btn btn-primary btn-sm">Review now</a>
        </div>
      @endif
      @if($stats['awaitingWaiver'] > 0)
        <div class="callout">
          <div class="callout-icon">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M7 3h7l5 5v13H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
          </div>
          <div class="callout-body">
            <strong>{{ $stats['awaitingWaiver'] }} approved {{ Str::plural('bluebook', $stats['awaitingWaiver']) }} awaiting a signed waiver</strong>
            <span>Not posted in Browse until the author hands the printed waiver in to the library.</span>
          </div>
          <a href="{{ route('admin.bluebooks') }}?status={{ urlencode(\App\Models\Bluebook::STATUS_AWAITING_WAIVER) }}" class="btn btn-primary btn-sm">View</a>
        </div>
      @endif

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
                        @elseif($b['status'] === \App\Models\Bluebook::STATUS_AWAITING_WAIVER) <span class="badge badge-blue">Awaiting Waiver</span>
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
