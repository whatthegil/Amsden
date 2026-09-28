{{-- How many uses of a daily-limited tool are left today (App\Services\UsageLimit). --}}
<span class="form-hint usage-left" style="{{ $usage['remaining'] === 0 ? 'color:var(--red);font-weight:600;' : '' }}">
  @if($usage['remaining'] === 0)
    No {{ $noun }} left today. They reset at midnight.
  @else
    {{ $usage['remaining'] }} of {{ $usage['limit'] }} {{ $noun }} left today
  @endif
</span>
