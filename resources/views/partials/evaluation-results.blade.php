{{-- The library's whole evaluation of a rejected submission: what is not okay
     (with the admin's comment), what passed, and what was left unchecked, so
     the author knows what to fix and what to leave alone. --}}
@php
  $evalResults = \App\Services\BluebookEvaluation::results($b);
  $evalGroups  = [
      'issue'     => ['Not okay — please fix', 'badge-red', 'Not okay'],
      'ok'        => ['Okay', 'badge-green', 'Okay'],
      'unchecked' => ['Not checked by the library', 'badge-gray', 'Not checked'],
  ];
  $evalCount = fn($s) => count(array_filter($evalResults, fn($r) => $r['status'] === $s));
@endphp
@if($evalResults)
  <div class="rejection-reason eval-issues-box eval-results-box">
    <strong>The library's evaluation{{ !empty($b['evaluatedAt']) ? ' (' . $b['evaluatedAt'] . ')' : '' }}:</strong>
    {{ $evalCount('ok') }} okay, {{ $evalCount('issue') }} not okay{{ $evalCount('unchecked') ? ', ' . $evalCount('unchecked') . ' not checked' : '' }}.
    @foreach($evalGroups as $status => [$heading, $badge, $short])
      @if($evalCount($status))
        <div class="eval-results-heading">{{ $heading }}</div>
        <ul class="eval-issues eval-results">
          @foreach($evalResults as $r)
            @continue($r['status'] !== $status)
            <li>
              <span class="badge {{ $badge }}">{{ $short }}</span>
              {{ $r['label'] }}
              @if($r['comment'] !== '')
                <span class="eval-issue-comment">&ldquo;{{ $r['comment'] }}&rdquo;</span>
              @endif
            </li>
          @endforeach
        </ul>
      @endif
    @endforeach
  </div>
@endif
