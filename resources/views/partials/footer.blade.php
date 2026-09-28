@if(session('user'))
<div class="modal-overlay" id="idle-modal" role="alertdialog" aria-modal="true" aria-labelledby="idle-modal-title" aria-describedby="idle-modal-text"
     data-warn="{{ \App\Http\Middleware\ExpireIdleSession::IDLE_WARN }}"
     data-grace="{{ \App\Http\Middleware\ExpireIdleSession::IDLE_GRACE }}"
     data-keep-alive="{{ route('session.keepAlive') }}"
     data-logout="{{ route('logout', ['idle' => 1]) }}">
  <div class="modal-card">
    <div class="modal-header">
      <h3 id="idle-modal-title">Are you still there?</h3>
    </div>
    <div class="modal-body">
      <p id="idle-modal-text">You have been inactive for {{ \App\Http\Middleware\ExpireIdleSession::IDLE_WARN / 60 }} minutes. For your security you will be signed out in <strong id="idle-countdown" aria-live="polite">2:00</strong>.</p>
      <div style="display:flex;gap:0.5rem;justify-content:flex-end;flex-wrap:wrap;">
        <a href="{{ route('logout') }}" class="btn btn-outline">Sign out</a>
        <button type="button" class="btn btn-primary" id="idle-extend">Stay signed in</button>
      </div>
    </div>
  </div>
</div>
@endif
<script src="/js/main.js?v={{ filemtime(public_path('js/main.js')) }}"></script>
</body>
</html>
