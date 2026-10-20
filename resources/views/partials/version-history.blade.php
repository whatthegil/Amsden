{{-- A bluebook's version history: every file submitted for it and what its
     review decided. The library sees who reviewed each one, every step taken
     on it, and can open the files it kept; the author ($forAuthor) sees their
     own submissions and the library's decisions on them. --}}
@php
  $forAuthor = $forAuthor ?? false;
  $outcomes  = [
      'Approved' => ['Approved', 'badge-green'],
      'Declined' => ['Declined', 'badge-red'],
      'Replaced' => ['Replaced before review', 'badge-gray'],
  ];
  $size = fn($bytes) => $bytes ? ($bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : number_format($bytes / 1024) . ' KB') : null;
@endphp
@if(($history['count'] ?? 0) > 0)
  <details class="version-history {{ $forAuthor ? '' : 'card' }}" id="versions" @if(!$forAuthor) open @endif>
    <summary>
      Version history
      <span class="version-tally">
        {{ $history['count'] }} {{ Str::plural('version', $history['count']) }}
        &middot; {{ $history['revisionsRequired'] }} {{ Str::plural('revision', $history['revisionsRequired']) }} required
        &middot; {{ $history['revisionsMade'] }} {{ Str::plural('revision', $history['revisionsMade']) }} made
        @if($history['issuesRaised'] > 0)
          &middot; {{ $history['issuesRaised'] }} checklist {{ Str::plural('item', $history['issuesRaised']) }} flagged
        @endif
      </span>
    </summary>

    <ol class="version-list">
      @foreach($history['versions'] as $i => $v)
        @php [$outcomeLabel, $outcomeBadge] = $outcomes[$v['outcome']] ?? ['Under review', 'badge-yellow']; @endphp
        <li class="version-item is-{{ strtolower($v['outcome'] ?? 'open') }}">
          <div class="version-head">
            <strong>Version {{ $v['version'] }}</strong>
            @if($i === 0) <span class="badge badge-blue">Current</span> @endif
            <span class="badge {{ $outcomeBadge }}">{{ $outcomeLabel }}</span>
            <span class="version-kind">{{ $v['kindLabel'] }}</span>
          </div>

          <div class="version-meta">
            Submitted {{ $v['submittedAt'] ?? '—' }}
            @if($v['submittedByName']) by {{ $v['submittedByName'] }}@unless($forAuthor) ({{ $v['submittedBy'] }})@endunless @endif
          </div>
          @if($v['fileOriginalName'])
            <div class="version-meta">
              File: {{ $v['fileOriginalName'] }}{{ $size($v['fileSize']) ? ' · ' . $size($v['fileSize']) : '' }}{{ $v['pages'] ? ' · ' . $v['pages'] . ' pages' : '' }}
              @if(!$forAuthor && $v['filePath'])
                &middot; <a href="{{ route('admin.bluebooks.versionFile', [$bluebook['id'], $v['version']]) }}" target="_blank" rel="noopener">Open this version</a>
              @endif
            </div>
          @endif
          @if($v['notes'])
            <div class="version-notes"><strong>What changed:</strong> {{ $v['notes'] }}</div>
          @endif

          @if($v['outcome'] && $v['outcome'] !== 'Replaced')
            <div class="version-meta">
              {{ $outcomeLabel }} {{ $v['reviewedAt'] }}
              @if(!$forAuthor && $v['reviewedByName']) by {{ $v['reviewedByName'] }} ({{ $v['reviewedBy'] }}) @endif
            </div>
          @endif
          @if($v['outcome'] === 'Declined')
            @if($v['reason'])
              <div class="rejection-reason"><strong>Reason:</strong> {{ $v['reason'] }}</div>
            @endif
            @if($v['evaluation'])
              <details class="version-eval">
                <summary>Evaluation &middot; {{ $v['issuesCount'] }} not okay</summary>
                @include('partials.evaluation-results', ['b' => $v])
              </details>
            @endif
          @endif

          @if(!$forAuthor && count($v['events']) > 1)
            <details class="version-events">
              <summary>Every step on this version ({{ count($v['events']) }})</summary>
              <ul>
                @foreach($v['events'] as $e)
                  <li>
                    <strong>{{ $e['action'] }}</strong> {{ $e['at'] }}
                    @if($e['name']) &middot; {{ $e['name'] }}@endif
                    @if($e['by']) ({{ $e['by'] }})@endif
                  </li>
                @endforeach
              </ul>
            </details>
          @endif
        </li>
      @endforeach
    </ol>
  </details>
@endif
