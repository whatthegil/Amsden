<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title ?? 'C-BAMS' }} — CSPC Bluebook Archive</title>
  <link rel="stylesheet" href="/css/style.css?v={{ filemtime(public_path('css/style.css')) }}">
  <link rel="icon" type="image/png" href="/images/cspc-logo.png">
  <script nonce="{{ $cspNonce ?? '' }}">
    // Apply the saved sidebar state before first paint to avoid a flash;
    // toggled by the topbar hamburger (see main.js).
    try { if (localStorage.getItem('cbams-sidebar') === 'collapsed') document.documentElement.classList.add('sidebar-collapsed'); } catch (e) {}
    // A Google profile picture that fails to load gives way to the initial
    // beside it. Listened for here, capturing, so a picture that fails before
    // main.js loads is still caught - the CSP allows no onerror attribute.
    document.addEventListener('error', function (e) {
      var img = e.target;
      if (img.tagName === 'IMG' && img.hasAttribute('data-avatar-fallback')) {
        img.hidden = true;
        if (img.nextElementSibling) img.nextElementSibling.hidden = false;
      }
    }, true);
  </script>
</head>
<body>
<a href="#main-content" class="skip-link">Skip to main content</a>
