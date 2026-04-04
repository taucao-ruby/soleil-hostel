@php
    $brand = config('email-branding');
    $colors = $brand['colors'];
    $logoUrl = $brand['logo']['url'] ?? (config('app.url') . '/logo.png');
    $websiteUrl = $brand['social']['website'] ?? config('app.url');
@endphp
<x-mail::message>
{{-- Brand Header with Logo --}}
<div style="text-align: center; margin-bottom: 24px;">
    @if($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $brand['logo']['alt'] }}" style="width: {{ $brand['logo']['width'] }}px; height: auto; max-width: 100%;">
    @else
        <h1 style="color: {{ $colors['primary'] }}; margin: 0; font-size: 28px; font-weight: bold;">{{ $brand['name'] }}</h1>
    @endif
</div>

{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# @lang('Hello!')
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{!! e($line) !!}

@endforeach

{{-- Action Button --}}
@isset($actionText)
@php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
@endphp
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{!! e($line) !!}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Warm regards,')<br>
**{{ $brand['name'] }} Team**
@endif

{{-- Brand Footer --}}
<x-slot:footer>
<div style="text-align: center; padding-top: 20px; border-top: 1px solid #e8e5ef;">
    {{-- Tagline --}}
    <p style="color: {{ $colors['muted'] }}; font-size: 14px; margin-bottom: 16px; font-style: italic;">
        {{ $brand['tagline'] }}
    </p>
    
    {{-- Contact Info --}}
    <p style="color: {{ $colors['muted'] }}; font-size: 12px; margin-bottom: 8px;">
        <a href="mailto:{{ $brand['contact']['email'] }}" style="color: {{ $colors['primary'] }}; text-decoration: none;">{{ $brand['contact']['email'] }}</a>
        @if($brand['contact']['phone'])
            | {{ $brand['contact']['phone'] }}
        @endif
    </p>
    
    @if($brand['contact']['address'])
    <p style="color: {{ $colors['muted'] }}; font-size: 12px; margin-bottom: 12px;">
        {{ $brand['contact']['address'] }}
    </p>
    @endif
    
    {{-- Social Links --}}
    <p style="margin-bottom: 12px;">
        @if($websiteUrl)
            <a href="{{ $websiteUrl }}" style="color: {{ $colors['primary'] }}; text-decoration: none; margin: 0 8px;">Website</a>
        @endif
        @if($brand['social']['facebook'])
            <a href="{{ $brand['social']['facebook'] }}" style="color: {{ $colors['primary'] }}; text-decoration: none; margin: 0 8px;">Facebook</a>
        @endif
        @if($brand['social']['instagram'])
            <a href="{{ $brand['social']['instagram'] }}" style="color: {{ $colors['primary'] }}; text-decoration: none; margin: 0 8px;">Instagram</a>
        @endif
    </p>
    
    {{-- Copyright --}}
    <p style="color: {{ $colors['muted'] }}; font-size: 11px;">
        {{ $brand['footer']['copyright'] }}
    </p>
    
    @if($brand['footer']['unsubscribe_text'])
    <p style="color: {{ $colors['muted'] }}; font-size: 11px;">
        {{ $brand['footer']['unsubscribe_text'] }}
    </p>
    @endif
</div>
</x-slot:footer>

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
@lang(
    "If you're having trouble clicking the \":actionText\" button, copy and paste the URL below\n".
    'into your web browser:',
    [
        'actionText' => $actionText,
    ]
) <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
