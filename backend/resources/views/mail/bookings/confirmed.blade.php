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

# 🎉 Booking Confirmed!

Hello **{{ $guestName }}**,

Great news! Your booking at **{{ config('email-branding.name', 'Soleil Hostel') }}** has been confirmed. We're excited to welcome you!

---

## 📋 Booking Details

@component('mail::panel')
| Detail | Information |
|:-------|:------------|
| **Confirmation Number** | #{{ $bookingId }} |
| **Room** | {{ $roomName }} |
| **Check-in** | {{ $checkIn }} |
| **Check-out** | {{ $checkOut }} |
| **Total Price** | ${{ number_format($totalPrice / 100, 2) }} |
@endcomponent

---

## 📍 What's Next?

1. **Save this email** - It contains your booking reference
2. **Check-in time** - 2:00 PM onwards
3. **Check-out time** - By 11:00 AM
4. **Bring valid ID** - Required at check-in

@component('mail::button', ['url' => $viewBookingUrl, 'color' => 'primary'])
View Your Booking
@endcomponent

---

## 🏨 Need to Make Changes?

You can modify or cancel your booking through our website up to 24 hours before check-in. For any questions, our team is happy to help!

**Contact us:**
- 📧 Email: {{ config('email-branding.contact.email', 'support@soleilhostel.com') }}
- 📞 Phone: {{ config('email-branding.contact.phone', '+1 (555) 123-4567') }}

---

Thank you for choosing us. We look forward to your stay!

Warm regards,<br>
**{{ config('email-branding.name', 'Soleil Hostel') }} Team**

---

<p style="text-align: center; color: #6C757D; font-style: italic; font-size: 14px;">
    {{ config('email-branding.tagline', 'Your Home Away From Home') }}
</p>
@endcomponent
