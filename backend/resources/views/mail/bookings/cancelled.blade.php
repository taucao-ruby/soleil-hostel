@component('mail::message')
{{-- Brand Header --}}
@php
    $brand = config('email-branding');
    $logoUrl = $brand['logo']['url'] ?? (config('app.url') . '/logo.png');
@endphp

<div style="text-align: center; margin-bottom: 24px;">
@if($logoUrl)
<img src="{{ $logoUrl }}" alt="{{ $brand['logo']['alt'] ?? 'Soleil Hostel' }}" style="width: {{ $brand['logo']['width'] ?? 150 }}px; height: auto; max-width: 100%;">
@else
<h1 style="color: {{ $brand['colors']['primary'] ?? '#007BFF' }}; margin: 0; font-size: 28px; font-weight: bold;">{{ $brand['name'] ?? 'Soleil Hostel' }}</h1>
@endif
</div>

# Booking Cancelled

Hello **{{ $guestName }}**,

Your booking at **{{ config('email-branding.name', 'Soleil Hostel') }}** has been cancelled as requested.

---

## 📋 Cancelled Booking Details

@component('mail::panel')
| Detail | Information |
|:-------|:------------|
| **Booking Number** | #{{ $bookingId }} |
| **Room** | {{ $roomName }} |
| **Original Check-in** | {{ $checkIn }} |
| **Original Check-out** | {{ $checkOut }} |
@endcomponent

---

@if($refundAmount && $refundAmount > 0)
## 💰 Refund Information

@component('mail::panel')
A refund of **${{ number_format($refundAmount / 100, 2) }}** has been processed to your original payment method.

Please allow **5-10 business days** for the refund to appear on your statement.
@endcomponent
@elseif($hasPayment && $refundAmount === 0)
## 💳 Refund Information

Based on our cancellation policy, no refund is available for this booking. If you believe this is an error, please contact our support team.
@endif

---

## 🏨 Book Again?

We're sorry to see you go! If your plans change, we'd love to welcome you another time.

@component('mail::button', ['url' => $bookAgainUrl, 'color' => 'primary'])
Browse Available Rooms
@endcomponent

---

## 📞 Questions?

If this cancellation was a mistake or you have any questions, please contact us immediately:

- 📧 Email: {{ config('email-branding.contact.email', 'support@soleilhostel.com') }}
- 📞 Phone: {{ config('email-branding.contact.phone', '+1 (555) 123-4567') }}

---

We hope to serve you in the future!

Warm regards,<br>
**{{ config('email-branding.name', 'Soleil Hostel') }} Team**

---

<p style="text-align: center; color: #6C757D; font-style: italic; font-size: 14px;">
    {{ config('email-branding.tagline', 'Your Home Away From Home') }}
</p>
@endcomponent
