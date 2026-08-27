<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title ?? 'C-BAMS' }} — CSPC Bluebook Archive</title>
  <link rel="stylesheet" href="/css/style.css">
  <link rel="icon" type="image/png" href="/images/cspc-logo.png">
  <script>
    // Apply the saved sidebar state before first paint to avoid a flash;
    // toggled by the topbar hamburger (see main.js).
    try { if (localStorage.getItem('cbams-sidebar') === 'collapsed') document.documentElement.classList.add('sidebar-collapsed'); } catch (e) {}
  </script>
</head>
<body>
<a href="#main-content" class="skip-link">Skip to main content</a>
