{{--
  Shared shell for the public legal pages (Terms and Conditions, Privacy
  Policy). These are reachable without signing in, so they deliberately use a
  plain document layout rather than the app chrome — there is no sidebar or
  topbar to hang off before a session exists.

  Expects: title, subtitle and updated attributes, plus the document body as
  the slot. `title` is also what partials.head renders into <title>.
--}}
@props(['title', 'subtitle' => '', 'updated' => ''])

@include('partials.head')

<div class="legal-page">
  <header class="legal-head">
    <a href="{{ route('login') }}" class="legal-brand">
      <img src="/images/cspc-logo.png" alt="CSPC" width="34" height="34">
      <span>C-BAMS <em>&mdash; CSPC Bluebook Archive</em></span>
    </a>
    <a href="{{ route('login') }}" class="legal-back">&larr; Back to sign in</a>
  </header>

  <main id="main-content" class="legal-doc">
    <h1>{{ $title }}</h1>
    <p class="legal-sub">{{ $subtitle }}</p>
    <p class="legal-updated">Last updated: {{ $updated }}</p>

    {{ $slot }}

    <nav class="legal-cross">
      @if (request()->routeIs('terms'))
        <a href="{{ route('privacy') }}">Read the Privacy Policy &rarr;</a>
      @else
        <a href="{{ route('terms') }}">Read the Terms and Conditions &rarr;</a>
      @endif
    </nav>
  </main>

  <footer class="legal-foot">
    C-BAMS &copy; {{ date('Y') }} &mdash; Camarines Sur Polytechnic Colleges. All Rights Reserved.
  </footer>
</div>

<script src="/js/main.js"></script>
</body>
</html>
