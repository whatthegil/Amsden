{{--
  The two working lists beside Bluebooks.

  Pending - the review queue, longest-waiting first: overdue submissions are
  flagged, each is checked against the posted archive for a likely duplicate,
  several can be approved at once, and a rejection can start from a common
  reason. Rejected - what was turned down and why, flagging authors who have
  not re-uploaded, with delete for those who manage bluebooks.

  $mode is 'pending' or 'rejected'.
--}}
@php
  $isPending = $mode === 'pending';
  $title     = $isPending ? 'Pending Review' : 'Rejected Bluebooks';
  $canReview = \App\Models\User::allows($user, 'review_bluebooks');
  $canManage = \App\Models\User::allows($user, 'manage_bluebooks');
  $count     = count($bluebooks);
  $filtered  = collect($query ?? [])->only(['search', 'department'])->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty();

  // The waiver the author asked for, in a word.
  $waiverLabel = function (array $b) {
    if (!$b['waiverRequested'] && !$b['waiverRecorded']) return '—';
    return match ($b['accessLevel']) {
      \App\Models\Bluebook::ACCESS_CONSULTATION => 'Consultation only',
      \App\Models\Bluebook::ACCESS_PARTIAL      => 'Selected parts',
      default                                   => 'Full document',
    };
  };
  $days = fn(int $d) => $d === 0 ? 'Today' : ($d === 1 ? '1 day' : $d . ' days');

  // One click to start a rejection from what is most often wrong; the text
  // can still be edited before it is sent.
  $reasons = [
    'Missing chapters' => 'The PDF is missing one or more chapters. Please upload the complete manuscript.',
    'Unreadable scan'  => 'Some pages of the scan are blurred or unreadable. Please upload a clearer copy.',
    'Wrong file'       => 'The uploaded file is not the final bluebook. Please upload the approved final copy.',
    'Details incomplete' => 'Some of the details (title, authors, adviser or abstract) do not match the document. Please correct them and re-upload.',
  ];
@endphp
@include('partials.head')

<div class="app">
  @include('partials.admin-sidebar')
  <main class="main" id="main-content">
    <header class="topbar">
      <h1 class="topbar-title">{{ $title }}</h1>
      <div class="topbar-right">
        <span class="topbar-badge">{{ ($user['role'] ?? '') === 'Sub-Admin' ? 'Sub-Admin' : 'Administrator' }}</span>
      </div>
    </header>

    <div class="content">
      <x-page-hero heading="{{ $isPending ? 'Pending' : 'Rejected' }}"
                   sub="{{ $isPending
                      ? 'Submissions waiting for review.'
                      : 'Submissions turned down, and why. A paper leaves this list when its author re-uploads it.' }}" />

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
      @endif

      {{-- At a glance: how much is waiting, and what needs chasing. --}}
      <div class="queue-summary">
        @if($isPending)
          <div class="queue-stat"><span class="num">{{ $summary['total'] }}</span><span class="label">waiting</span></div>
          <div class="queue-stat"><span class="num">{{ $summary['total'] ? $days($summary['oldest']) : '—' }}</span><span class="label">oldest wait</span></div>
          <div class="queue-stat {{ $summary['overdue'] ? 'is-alert' : '' }}"><span class="num">{{ $summary['overdue'] }}</span><span class="label">overdue (over {{ $overdueDays }} days)</span></div>
        @else
          <div class="queue-stat"><span class="num">{{ $summary['total'] }}</span><span class="label">rejected</span></div>
          <div class="queue-stat {{ $summary['noReply'] ? 'is-alert' : '' }}"><span class="num">{{ $summary['noReply'] }}</span><span class="label">no re-upload in {{ $noReplyDays }}+ days</span></div>
        @endif
      </div>

      <form method="GET" action="{{ route($isPending ? 'admin.pending' : 'admin.rejected') }}" class="filter-bar">
        <div class="filter-group grow">
          <label for="queue-search">Search</label>
          <input id="queue-search" type="text" name="search" value="{{ $query['search'] ?? '' }}" placeholder="Title, author or uploader…">
        </div>
        <div class="filter-group">
          <label for="queue-department">Department</label>
          <select id="queue-department" name="department">
            <option value="">All Departments</option>
            @foreach(config('departments') as $code => $dept)
              <option value="{{ $code }}" @selected(($query['department'] ?? '') === $code)>{{ $code }}</option>
            @endforeach
          </select>
        </div>
        @if($isPending)
          <div class="filter-group">
            <label for="queue-sort">Order</label>
            <select id="queue-sort" name="sort">
              <option value="oldest" @selected(($query['sort'] ?? 'oldest') !== 'newest')>Longest waiting first</option>
              <option value="newest" @selected(($query['sort'] ?? '') === 'newest')>Newest first</option>
            </select>
          </div>
        @endif
        <div style="display:flex;gap:0.5rem;align-items:flex-end;">
          <button type="submit" class="btn btn-primary btn-sm">Filter</button>
          <a href="{{ route($isPending ? 'admin.pending' : 'admin.rejected') }}" class="btn btn-outline btn-sm">Clear</a>
        </div>
      </form>

      @if($count > 0)
        {{-- Approve selected: the checkboxes below belong to this form. --}}
        @if($isPending && $canReview)
          <form method="POST" action="{{ route('admin.bluebooks.approveSelected') }}" id="approve-selected">
            @csrf
          </form>
          <div class="queue-bulk">
            <label class="access-option" style="margin:0;"><input type="checkbox" id="select-all"> <span>Select all</span></label>
            <button type="submit" form="approve-selected" class="btn btn-success btn-sm" id="approve-selected-btn" disabled>Approve selected</button>
          </div>
        @endif

        <div class="card">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  @if($isPending && $canReview)<th><span class="sr-only">Select</span></th>@endif
                  <th>Title</th>
                  <th>Uploaded by</th>
                  @if($isPending)
                    <th>Waiting</th>
                    <th>Waiver asked for</th>
                    <th>Check</th>
                  @else
                    <th>Rejected</th>
                    <th>Reason</th>
                  @endif
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @foreach($bluebooks as $b)
                  <tr>
                    @if($isPending && $canReview)
                      <td><input type="checkbox" name="ids[]" value="{{ $b['id'] }}" form="approve-selected" class="row-select" aria-label="Select {{ $b['title'] }}"></td>
                    @endif
                    <td style="max-width:260px;">
                      <a href="{{ route('admin.bluebooks.view', $b['id']) }}" title="Read {{ $b['title'] }}" class="clip" style="display:block;max-width:260px;font-weight:600;color:var(--primary-dark);">{{ $b['title'] }}</a>
                      <div class="clip" style="max-width:260px;font-size:0.78rem;color:var(--gray-400);margin-top:0.2rem;" title="{{ implode('; ', $b['authors']) }}">
                        {{ $b['department'] }} &middot; {{ $b['year'] }} &middot; {{ implode(', ', array_slice($b['authors'], 0, 2)) }}{{ count($b['authors']) > 2 ? '…' : '' }}
                      </div>
                    </td>
                    <td style="font-size:0.83rem;">
                      <div class="clip" style="max-width:150px;" title="{{ $b['uploadedBy'] }}">{{ $b['uploadedByName'] }}</div>
                    </td>
                    @if($isPending)
                      <td style="font-size:0.83rem;white-space:nowrap;" title="Since {{ $b['updatedAt'] }}">
                        {{ $days($b['waitingDays']) }}
                        @if($b['waitingDays'] > $overdueDays) <span class="badge badge-red">Overdue</span> @endif
                      </td>
                      <td style="font-size:0.83rem;white-space:nowrap;">{{ $waiverLabel($b) }}</td>
                      <td style="font-size:0.8rem;">
                        @if($b['duplicate'])
                          <a href="{{ route('admin.bluebooks.view', $b['duplicate']['id']) }}" class="badge badge-yellow" title="Possible duplicate of: {{ $b['duplicate']['title'] }}">
                            {{ round($b['duplicate']['score'] * 100) }}% like a posted paper
                          </a>
                        @else
                          <span class="badge badge-green" title="No posted paper is close to this one">No duplicate</span>
                        @endif
                        @if($b['ocrStatus'] === 'failed') <span class="badge badge-red" title="The text could not be read">OCR failed</span> @endif
                      </td>
                    @else
                      <td style="font-size:0.83rem;white-space:nowrap;" title="On {{ $b['updatedAt'] }}">
                        {{ $b['waitingDays'] === 0 ? 'Today' : $days($b['waitingDays']) . ' ago' }}
                        @if($b['waitingDays'] > $noReplyDays) <span class="badge badge-yellow">No re-upload</span> @endif
                      </td>
                      <td style="font-size:0.83rem;max-width:300px;">
                        <div class="clip" style="max-width:300px;" title="{{ $b['rejectionReason'] }}">{{ $b['rejectionReason'] ?: '—' }}</div>
                      </td>
                    @endif
                    <td>
                      <div class="td-actions">
                        <a href="{{ route('admin.bluebooks.view', $b['id']) }}" class="btn btn-outline btn-sm">View</a>
                        @if($isPending && $canReview)
                          <form method="POST" action="{{ route('admin.bluebooks.approve', $b['id']) }}" style="display:inline;">
                            @csrf
                            <input type="hidden" name="from" value="pending">
                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                          </form>
                          <details class="reject-box">
                            <summary class="btn btn-warning btn-sm">Reject</summary>
                            <form method="POST" action="{{ route('admin.bluebooks.reject', $b['id']) }}">
                              @csrf
                              <input type="hidden" name="from" value="pending">
                              <span style="font-size:0.78rem;color:var(--gray-600);">Common reasons:</span>
                              <div class="reason-picks">
                                @foreach($reasons as $label => $text)
                                  <button type="button" class="reason-pick" data-reason="{{ $text }}">{{ $label }}</button>
                                @endforeach
                              </div>
                              <label for="queue-reason-{{ $b['id'] }}">Reason (shown to the author)</label>
                              <textarea id="queue-reason-{{ $b['id'] }}" name="reason" rows="3" maxlength="1000" required
                                        placeholder="Why it cannot be accepted, and what to fix."></textarea>
                              <button type="submit" class="btn btn-warning btn-sm">Confirm Reject</button>
                            </form>
                          </details>
                        @elseif(!$isPending && $canManage)
                          <details class="reject-box">
                            <summary class="btn btn-danger btn-sm">Delete</summary>
                            <form method="POST" action="{{ route('admin.bluebooks.delete', $b['id']) }}">
                              @csrf
                              <input type="hidden" name="from" value="rejected">
                              <span style="font-size:0.8rem;">Delete this submission and its PDF for good?</span>
                              <button type="submit" class="btn btn-danger btn-sm">Confirm Delete</button>
                            </form>
                          </details>
                        @endif
                      </div>
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
            @if($filtered)
              <p>No submissions match these filters.</p>
              <a href="{{ route($isPending ? 'admin.pending' : 'admin.rejected') }}" class="btn btn-outline btn-sm" style="margin-top:0.85rem;">Clear filters</a>
            @else
              <p>{{ $isPending ? 'Nothing is waiting for review.' : 'No submissions have been rejected.' }}</p>
              <a href="{{ route('admin.bluebooks') }}" class="btn btn-outline btn-sm" style="margin-top:0.85rem;">All bluebooks</a>
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

<script>
// Approve selected: enabled once something is ticked; "Select all" ticks the lot.
(function () {
  const all  = document.getElementById('select-all');
  const btn  = document.getElementById('approve-selected-btn');
  const rows = () => Array.from(document.querySelectorAll('.row-select'));
  const sync = () => {
    const ticked = rows().filter(r => r.checked).length;
    if (btn) { btn.disabled = ticked === 0; btn.textContent = ticked ? 'Approve selected (' + ticked + ')' : 'Approve selected'; }
    if (all) all.checked = ticked > 0 && ticked === rows().length;
  };
  if (all) all.addEventListener('change', () => { rows().forEach(r => { r.checked = all.checked; }); sync(); });
  document.addEventListener('change', e => { if (e.target.classList && e.target.classList.contains('row-select')) sync(); });

  // A common reason fills the box, which can still be edited.
  document.addEventListener('click', e => {
    const pick = e.target.closest('.reason-pick');
    if (!pick) return;
    const box = pick.closest('form').querySelector('textarea');
    box.value = pick.dataset.reason;
    box.focus();
  });
})();
</script>

@include('partials.footer')
