{{-- The checklist items the library marked not okay on this submission, with
     the admin's comment on each: what the author has to fix. --}}
@php $evalIssues = \App\Services\BluebookEvaluation::issues($b); @endphp
@if($evalIssues)
  <div class="rejection-reason eval-issues-box">
    <strong>The library's evaluation found {{ count($evalIssues) }} {{ Str::plural('problem', count($evalIssues)) }}:</strong>
    <ul class="eval-issues">
      @foreach($evalIssues as $issue)
        <li>
          {{ $issue['label'] }}
          @if($issue['comment'] !== '')
            <span class="eval-issue-comment">&ldquo;{{ $issue['comment'] }}&rdquo;</span>
          @endif
        </li>
      @endforeach
    </ul>
  </div>
@endif
