{{--
  Readers' requests for the full text of Restricted and Partial Access
  bluebooks (Library Manual 4.3.1.4). Each is decided on its own: approve on
  the authorization the author's waiver requires, recording what it was, or
  deny with a reason the reader sees. An approval can later be revoked.
--}}
@php
  $title = 'Access Requests';
  $tabs  = [
    \App\Models\AccessRequest::STATUS_PENDING  => 'Pending',
    \App\Models\AccessRequest::STATUS_APPROVED => 'Approved',
    \App\Models\AccessRequest::STATUS_DENIED   => 'Denied',
    \App\Models\AccessRequest::STATUS_REVOKED  => 'Revoked',
    'all'                                      => 'All',
  ];
@endphp
@include('partials.head')

<div class="app">
  @include('partials.admin-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">Access Requests</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ ($user['role'] ?? '') === 'Sub-Admin' ? 'Sub-Admin' : 'Administrator' }}</span>
      </div>
    </header>

    <div class="content">
      <x-page-hero heading="Access Requests"
                   sub="Requests to view the full text of Restricted and Partial Access bluebooks. Decide each one by the authorization its author's waiver requires." />

      @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="alert alert-error" style="margin-bottom:1rem;">{{ session('error') }}</div>
      @endif
      @if($errors->any())
        <div class="alert alert-error" style="margin-bottom:1rem;">
          @foreach($errors->all() as $message) <div>{{ $message }}</div> @endforeach
        </div>
      @endif

      <nav class="filter-bar" aria-label="Request status" style="gap:0.4rem;">
        @foreach($tabs as $value => $label)
          <a href="{{ route('admin.access-requests', ['status' => $value]) }}"
             class="btn btn-sm {{ $status === $value ? 'btn-primary' : 'btn-outline' }}">
            {{ $label }}@if($value !== 'all') ({{ $counts[$value] ?? 0 }})@endif
          </a>
        @endforeach
      </nav>

      @if($requests->count() > 0)
        <div class="card">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Bluebook</th>
                  <th>Requested by</th>
                  <th>Research, program &amp; adviser</th>
                  <th>Purpose</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                @foreach($requests as $r)
                  <tr>
                    <td style="max-width:220px;">
                      @if($r->bluebook)
                        <a href="{{ route('admin.bluebooks.view', $r->bluebook_id) }}" class="clip" style="display:block;max-width:220px;font-weight:600;color:var(--primary-dark);" title="{{ $r->bluebook->title }}">{{ $r->bluebook->title }}</a>
                        <div style="font-size:0.76rem;color:var(--gray-400);margin-top:0.2rem;">{{ \App\Models\Bluebook::accessName($r->bluebook->access_level) }}</div>
                      @else
                        <em>Removed</em>
                      @endif
                    </td>
                    <td style="font-size:0.83rem;">
                      <div class="clip" style="max-width:160px;" title="{{ $r->user_email }}">{{ $r->user_name }}</div>
                      <div style="font-size:0.76rem;color:var(--gray-400);white-space:nowrap;">{{ $r->created_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</div>
                    </td>
                    <td style="max-width:240px;font-size:0.8rem;">
                      <div><strong>{{ $r->research_title }}</strong></div>
                      <div style="color:var(--gray-600);">{{ $r->program }} &middot; Adviser: {{ $r->adviser }}</div>
                    </td>
                    <td style="max-width:240px;font-size:0.8rem;white-space:pre-line;">{{ $r->purpose }}</td>
                    <td style="font-size:0.8rem;">
                      <span class="badge {{ $r->status === 'Approved' ? 'badge-green' : ($r->status === 'Pending' ? 'badge-yellow' : 'badge-red') }}">{{ $r->status }}</span>
                      @if($r->decided_at)
                        <div style="color:var(--gray-400);margin-top:0.25rem;">{{ $r->decided_at->timezone('Asia/Manila')->format('M j, Y') }} by {{ $r->decided_by }}</div>
                        <div style="color:var(--gray-600);margin-top:0.2rem;max-width:220px;">{{ $r->decision_note }}</div>
                      @endif
                    </td>
                    <td>
                      <div class="td-actions">
                        @if($r->status === \App\Models\AccessRequest::STATUS_PENDING)
                          <details class="reject-box">
                            <summary class="btn btn-success btn-sm">Approve</summary>
                            <form method="POST" action="{{ route('admin.access-requests.approve', $r->id) }}">
                              @csrf
                              <label for="ar-auth-{{ $r->id }}">Authorization on record</label>
                              <textarea id="ar-auth-{{ $r->id }}" name="authorization" rows="3" maxlength="1000" required
                                        placeholder="e.g. Written consent of the author/s dated …, or consultation with the author on …"></textarea>
                              <button type="submit" class="btn btn-success btn-sm">Confirm Approve</button>
                            </form>
                          </details>
                          <details class="reject-box">
                            <summary class="btn btn-warning btn-sm">Deny</summary>
                            <form method="POST" action="{{ route('admin.access-requests.deny', $r->id) }}">
                              @csrf
                              <label for="ar-deny-{{ $r->id }}">Reason (shown to the reader)</label>
                              <textarea id="ar-deny-{{ $r->id }}" name="reason" rows="3" maxlength="1000" required
                                        placeholder="e.g. The author/s did not authorize access beyond the permitted parts."></textarea>
                              <button type="submit" class="btn btn-warning btn-sm">Confirm Deny</button>
                            </form>
                          </details>
                        @elseif($r->status === \App\Models\AccessRequest::STATUS_APPROVED)
                          <details class="reject-box">
                            <summary class="btn btn-danger btn-sm">Revoke</summary>
                            <form method="POST" action="{{ route('admin.access-requests.revoke', $r->id) }}">
                              @csrf
                              <label for="ar-revoke-{{ $r->id }}">Reason</label>
                              <textarea id="ar-revoke-{{ $r->id }}" name="reason" rows="3" maxlength="1000" required></textarea>
                              <button type="submit" class="btn btn-danger btn-sm">Confirm Revoke</button>
                            </form>
                          </details>
                        @else
                          &mdash;
                        @endif
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
        @if($requests->hasPages())
          <div style="margin-top:1rem;display:flex;gap:0.5rem;align-items:center;">
            @if($requests->previousPageUrl()) <a href="{{ $requests->previousPageUrl() }}" class="btn btn-outline btn-sm">&larr; Newer</a> @endif
            <span style="font-size:0.82rem;color:var(--gray-600);">Page {{ $requests->currentPage() }} of {{ $requests->lastPage() }}</span>
            @if($requests->nextPageUrl()) <a href="{{ $requests->nextPageUrl() }}" class="btn btn-outline btn-sm">Older &rarr;</a> @endif
          </div>
        @endif
      @else
        <div class="card">
          <div class="empty-state">
            <p>{{ $status === 'Pending' ? 'No requests are waiting for a decision.' : 'No requests here.' }}</p>
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
