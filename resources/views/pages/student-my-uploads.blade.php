@php $title = 'My Uploads'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <div class="main">
    <header class="topbar">
      <h1 class="topbar-title">My Uploads</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content">
      <div class="page-header">
        <div>
          <h1>My Uploads</h1>
          <p>{{ count($bluebooks) }} submission(s)</p>
        </div>
        @if($user['canUpload'] ?? false)
          <a href="{{ route('student.upload') }}" class="btn btn-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            Upload New
          </a>
        @endif
      </div>

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif

      @if(count($bluebooks) > 0)
        <div class="card">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Title</th>
                  <th>Department</th>
                  <th>Year</th>
                  <th>Status</th>
                  <th>Date Added</th>
                  <th>Views</th>
                  <th>OCR</th>
                </tr>
              </thead>
              <tbody>
                @foreach($bluebooks as $i => $b)
                  <tr @if($b['status'] === 'Approved') data-href="{{ route('student.bluebook', $b['id']) }}" @endif>
                    <td class="mono">{{ $i + 1 }}</td>
                    <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500;">{{ $b['title'] }}</td>
                    <td><span class="badge badge-blue">{{ $b['department'] }}</span></td>
                    <td>{{ $b['year'] }}</td>
                    <td>
                      @if($b['status'] === 'Approved') <span class="badge badge-green">Approved</span>
                      @elseif($b['status'] === 'Pending') <span class="badge badge-yellow">Pending Review</span>
                      @else <span class="badge badge-red">Rejected</span>
                      @endif
                    </td>
                    <td style="font-size:0.83rem;">{{ $b['dateAdded'] }}</td>
                    <td>{{ $b['views'] }}</td>
                    <td>
                      @if($b['ocrStatus'] === 'completed') <span class="badge badge-green">Completed</span>
                      @elseif($b['ocrStatus'] === 'processing') <span class="badge badge-yellow">Processing</span>
                      @elseif($b['ocrStatus'] === 'failed') <span class="badge badge-red">Failed</span>
                      @else <span class="badge badge-blue">Pending</span>
                      @endif
                      @if($b['hasFile'] && $b['ocrStatus'] !== 'processing')
                        <form method="POST" action="{{ route('student.bluebook.reprocess-ocr', $b['id']) }}" style="display:inline-block;margin-left:0.35rem;" onclick="event.stopPropagation();">
                          @csrf <button type="submit" class="btn btn-outline btn-sm">Retry OCR</button>
                        </form>
                      @endif
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
            <p>You haven't uploaded any bluebooks yet.</p>
            @if($user['canUpload'] ?? false)
              <a href="{{ route('student.upload') }}" class="btn btn-primary" style="margin-top:1rem;">Upload your first bluebook</a>
            @else
              <p style="margin-top:0.5rem;font-size:0.82rem;">Contact an administrator to request upload permission.</p>
            @endif
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
