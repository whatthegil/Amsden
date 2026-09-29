{{-- Signing out is a POST with the CSRF token, not a link: a link could be
     followed by any other page (an <img src="/logout">) to sign a reader out,
     and by browsers that prefetch links. The button keeps the look of whatever
     it replaces through the classes and style passed in. --}}
<form method="POST" action="{{ route('logout') }}" class="logout-form">
  @csrf
  <button type="submit" {{ $attributes->merge(['class' => 'logout-button']) }}>{{ $slot }}</button>
</form>
