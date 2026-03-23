<x-mail::message>
@component('mail::header', ['url' => config('app.url')])
<a href="{{ config('app.url') }}">
    <img src="{{ url('images/brand-logo.png') }}" alt="Marcelinos Logo" style="height: 60px;">
</a>
@endcomponent

# Hello {{ $booking->full_name ?? $booking->guest_name }},

This is a friendly reminder that your booking at **Marcelino's Resort and Hotel** is scheduled for **tomorrow**.

<x-mail::panel>
**Check-in Date:** {{ \Carbon\Carbon::parse($booking->check_in)->format('F j, Y') }}

**Check-out Date:** {{ \Carbon\Carbon::parse($booking->check_out)->format('F j, Y') }}

**Reference No.:** {{ $booking->reference_no ?? $booking->id }}
</x-mail::panel>

Please make sure to prepare the following before your arrival:

- Valid ID
- Booking reference
- Any remaining payment, if applicable

<x-mail::button :url="config('app.url') . '/booking'">
View Booking
</x-mail::button>

If you have questions or need assistance, just reply to this email.

Thanks,<br>
{{ config('app.name') }}

<x-mail::footer>
© {{ date('Y') }} Marcelino's Resort and Hotel. All rights reserved.
</x-mail::footer>
</x-mail::message>