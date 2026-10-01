@php $title = 'Access Requests'; @endphp
@include('partials.head')

<div class="app">
  @include('partials.student-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">Access Requests</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ $user['role'] }}</span>
      </div>
    </header>

    <div class="content">
      <x-page-hero heading="Access Requests"
                   sub="Your requests for the full text of restricted and partial-access bluebooks, and the library's decision on each." />

      @if(count($requests) > 0)
        <div class="card">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Bluebook</th>
                  <th>For your research</th>
                  <th>Requested</th>
                  <th>Status</th>
                  <th>Library's note</th>
                </tr>
              </thead>
              <tbody>
                @foreach($requests as $r)
                  @php
                    $badge = match ($r->status) {
                      \App\Models\AccessRequest::STATUS_APPROVED => 'badge-green',
                      \App\Models\AccessRequest::STATUS_PENDING  => 'badge-yellow',
                      default                                     => 'badge-red',
                    };
                  @endphp
                  <tr>
                    <td style="max-width:260px;">
                      @if($r->bluebook)
                        <a href="{{ route('student.bluebook', $r->bluebook_id) }}" class="clip" style="display:block;max-width:260px;font-weight:600;color:var(--primary-dark);" title="{{ $r->bluebook->title }}">{{ $r->bluebook->title }}</a>
                      @else
                        <em>Removed from the archive</em>
                      @endif
                    </td>
                    <td style="max-width:220px;font-size:0.83rem;"><div class="clip" style="max-width:220px;" title="{{ $r->research_title }}">{{ $r->research_title }}</div></td>
                    <td class="mono" style="font-size:0.82rem;white-space:nowrap;">{{ $r->created_at->timezone('Asia/Manila')->format('M j, Y') }}</td>
                    <td><span class="badge {{ $badge }}">{{ $r->status }}</span></td>
                    <td style="max-width:260px;font-size:0.83rem;">
                      @if($r->status === \App\Models\AccessRequest::STATUS_APPROVED)
                        You may view the full text. Viewing only &mdash; no copies.
                      @elseif($r->status === \App\Models\AccessRequest::STATUS_PENDING)
                        Being evaluated.
                      @else
                        {{ $r->decision_note ?: '—' }}
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
            <p>You have not requested access to any bluebook.</p>
            <p style="font-size:0.8rem;color:var(--gray-400);margin-top:0.35rem;">On a Restricted or Partial Access bluebook, use <em>Request full-text access</em> to ask the library for the rest of it.</p>
            <a href="{{ route('student.bluebooks') }}" class="btn btn-outline btn-sm" style="margin-top:0.85rem;">Browse the archive</a>
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
