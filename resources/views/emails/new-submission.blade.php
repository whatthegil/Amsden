{{-- Markdown mail: keep the body flush left, or indented lines become code blocks. --}}
<x-mail::message>
# {{ $resubmitted ? 'A bluebook was resubmitted' : 'A new bluebook was submitted' }}

@if($resubmitted)
**{{ $uploaderName !== '' ? $uploaderName : $uploaderEmail }}** uploaded a corrected PDF of a bluebook you rejected. It is back in the Pending queue.
@else
**{{ $uploaderName !== '' ? $uploaderName : $uploaderEmail }}** submitted a bluebook. It is waiting in the Pending queue for your review.
@endif

<x-mail::panel>
**Title:** {{ $title }}<br>
**Department:** {{ $department !== '' ? $department : '—' }}<br>
**Uploaded by:** {{ $uploaderName }} ({{ $uploaderEmail }})
</x-mail::panel>

The Library Manual gives the library three working days to evaluate a complete submission.

<x-mail::button :url="$url">
Review the bluebook
</x-mail::button>

Or open the [Pending queue]({{ $queue }}) to see everything waiting.

{{ config('app.name') }}

<x-mail::subcopy>
You are receiving this because you are an Admin of {{ config('app.name') }}. This is an automated message; replies are not monitored.
</x-mail::subcopy>
</x-mail::message>
