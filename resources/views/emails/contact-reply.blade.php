<x-mail::message>
{{-- Header with Logo/Branding --}}
@component('mail::header', ['url' => config('app.url')])
<a href="{{ config('app.url') }}">
    <img src="{{ url('images/brand-logo.png') }}" alt="Marcelinos Logo" style="height: 60px;">
</a>
@endcomponent

{{-- Greeting --}}
<x-mail::panel>
## Hello {{ $contact->full_name }},

Thank you for reaching out to **Marcelinos**. We appreciate you taking the time to contact us regarding your inquiry.
</x-mail::panel>

{{-- Original Inquiry Summary --}}
<x-mail::panel>
### Your Original Inquiry
**Subject:** {{ $contact->subject }}

**Date Submitted:** {{ $contact->created_at->format('F j, Y \a\t g:i A') }}

**Your Message:**
> {{ $contact->message }}
</x-mail::panel>

{{-- Our Response --}}
<x-mail::panel>
### Our Response

{{ $replyMessage }}
</x-mail::panel>

{{-- Additional Information --}}
<x-mail::panel>
@if($contact->phone)
**Your Contact Information:**
- **Phone:** {{ $contact->phone }}
- **Email:** {{ $contact->email }}
@endif

If you have any additional questions or need further assistance, please don't hesitate to reply to this email or contact us directly.
</x-mail::panel>

{{-- Call to Action --}}
<x-mail::button :url="config('app.url') . '/contact'" color="primary">
Visit Our Website
</x-mail::button>

<x-mail::panel>
### Get in Touch
- **Website:** [{{ config('app.url') }}]({{ config('app.url') }})
- **Email:** info@marcelinos.com
- **Phone:** +1 (555) 123-4567

*Follow us on social media for updates and special offers!*
</x-mail::panel>

{{-- Footer --}}
<x-mail::footer>
© {{ date('Y') }} Marcelinos. All rights reserved.

*This email was sent in response to your inquiry. If you did not submit this inquiry, please ignore this message.*
</x-mail::footer>
</x-mail::message>