{{-- Markdown mail: keep the body flush left, or indented lines become code blocks. --}}
<x-mail::message>
# Hello{{ $name !== '' ? ', ' . $name : '' }}

@switch($event)
@case('received')
We received your bluebook **{{ $title }}**. It is now **Pending** and waiting for the library to review it. The library aims to evaluate a complete submission within three working days.
@break

@case('approved')
Your bluebook **{{ $title }}** has been **approved**.

It will be posted in the archive once you hand in the **signed Access Permission Waiver** to the CSPC Library. You can download the waiver form from My Uploads.
@break

@case('posted')
Your bluebook **{{ $title }}** is now **posted** in the C-BAMS archive, where students and faculty can find it.
@break

@case('rejected')
Your bluebook **{{ $title }}** was **not approved** yet. Please correct it and upload the revised PDF from My Uploads.

@if($note)
<x-mail::panel>
**Reason from the library:** {{ $note }}
</x-mail::panel>
@endif
@break

@case('recalled')
Your bluebook **{{ $title }}** has been returned to **Pending** for another review, and is hidden from the archive until it is approved again. The library may ask you for corrections.
@break

@case('evaluated')
The library evaluated your bluebook **{{ $title }}** and marked **{{ $issues }} {{ \Illuminate\Support\Str::plural('item', $issues) }}** as not okay. Open My Uploads to see each item and the library's comments.
@break
@endswitch

<x-mail::button :url="$url">
Open My Uploads
</x-mail::button>

Thank you,<br>
CSPC Library — {{ config('app.name') }}

<x-mail::subcopy>
You are receiving this because you uploaded this bluebook to {{ config('app.name') }}. This is an automated message; replies are not monitored.
</x-mail::subcopy>
</x-mail::message>
