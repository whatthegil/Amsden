{{--
  The banner every in-app page opens on.

  These were a .page-header each: a heading, a line of grey text and sometimes a
  button, repeated across a dozen files with nothing holding them in step. One
  component means a change to how a page introduces itself is one edit rather
  than twelve, and that no page is left behind when the next one happens.

  Usage:

    <x-page-hero heading="Bluebooks" sub="12 records found">
      <x-slot name="action">
        <a href="..." class="btn btn-sm btn-on-hero">Add Bluebook</a>
      </x-slot>
    </x-page-hero>

  The `action` slot renders on the panel, so anything put in it wants
  .btn-on-hero rather than .btn-primary - a filled primary button on the blue
  disappears into it.
--}}
@props(['heading', 'sub' => null, 'slim' => true])

<div class="dash-hero{{ $slim ? ' slim' : '' }}">
  <div class="dash-hero-top" style="margin-bottom:0;">
    <div>
      <h1>{{ $heading }}</h1>
      @if($sub)
        <p>{{ $sub }}</p>
      @endif
      {{ $slot }}
    </div>
    @isset($action)
      <div style="flex-shrink:0;">{{ $action }}</div>
    @endisset
  </div>
</div>
