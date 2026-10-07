{{-- The sidebar's picture of the signed-in user: their Google profile picture,
     or their initial when they have none or it fails to load. --}}
@php($initial = strtoupper(substr($user['name'] ?? '?', 0, 1)))
@if(!empty($user['avatar']))
  <img src="{{ $user['avatar'] }}" alt="" class="user-chip-avatar user-chip-photo" referrerpolicy="no-referrer"
       data-avatar-fallback>
@endif
<div class="user-chip-avatar" aria-hidden="true" @if(!empty($user['avatar'])) hidden @endif>{{ $initial }}</div>
