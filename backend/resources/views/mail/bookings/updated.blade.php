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

# 📝 Booking Updated

Hello **{{ $guestName }}**,

Your booking at **{{ config('email-branding.name', 'Soleil Hostel') }}** has been updated.

---

@if(!empty($changes))
## 🔄 Changes Made

@component('mail::panel')
| Field | New Value |
|:------|:----------|
@foreach($changes as $field => $value)
| **{{ ucfirst(str_replace('_', ' ', $field)) }}** | {{ $value instanceof \DateTimeInterface ? $value->format('M j, Y') : $value }} |
@endforeach
@endcomponent
@endif

---

## 📋 Current Booking Details

@component('mail::panel')
| Detail | Information |
|:-------|:------------|
| **Booking Number** | #{{ $bookingId }} |
| **Room** | {{ $roomName }} |
| **Check-in** | {{ $checkIn }} |
| **Check-out** | {{ $checkOut }} |
| **Total Price** | ${{ number_format($totalPrice / 100, 2) }} |
@endcomponent

---

@component('mail::button', ['url' => $viewBookingUrl, 'color' => 'primary'])
View Updated Booking
@endcomponent

---

## 📞 Need Help?

If you have any questions about these changes, please contact us:

- 📧 Email: {{ config('email-branding.contact.email', 'support@soleilhostel.com') }}
- 📞 Phone: {{ config('email-branding.contact.phone', '+1 (555) 123-4567') }}

---

Thank you for choosing us!

Warm regards,<br>
**{{ config('email-branding.name', 'Soleil Hostel') }} Team**

---

<p style="text-align: center; color: #6C757D; font-style: italic; font-size: 14px;">
    {{ config('email-branding.tagline', 'Your Home Away From Home') }}
</p>
@endcomponent
