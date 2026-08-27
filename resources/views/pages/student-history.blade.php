@php $title = 'My History'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">My History</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div>
          <h1>Activity History</h1>
          <p>{{ count($logs) }} activity record(s)</p>
        </div>
      </div>

      @if(count($logs) > 0)
        <div class="card">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
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
                    <td><span class="badge badge-blue">{{ $log['action'] }}</span></td>
                    <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.85rem;">{{ $log['document'] }}</td>
                    <td class="mono" style="font-size:0.82rem;white-space:nowrap;">{{ $log['timestamp'] }}</td>
                    <td><span class="badge badge-green">{{ $log['status'] }}</span></td>
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
            <p>No activity recorded yet.</p>
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
