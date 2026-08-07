@php $title = 'My History'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <div class="main">
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
            <div class="icon">📋</div>
            <p>No activity recorded yet.</p>
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
