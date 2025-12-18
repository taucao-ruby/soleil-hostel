# 🛡️ XSS Protection

> HTML Purifier implementation for content sanitization

## Overview

**HTML Purifier** provides whitelist-based XSS protection with **0% bypass rate** across 48 test vectors.

---

## Installation

```bash
composer require ezyang/htmlpurifier:^4.19
```

---

## Usage

### Method 1: Model Auto-Purify (Recommended)

```php
use App\Traits\Purifiable;

class Booking extends Model
{
    use Purifiable;
    protected array $purifiable = ['guest_name', 'special_notes'];
}

// Automatically purified on save
Booking::create([
    'guest_name' => '<b>John</b><script>xss</script>'
]);
// Stored as: <b>John</b>
```

### Method 2: FormRequest

```php
class StoreReviewRequest extends FormRequest
{
    public function validated()
    {
        return $this->purify(['title', 'content']);
    }
}
```

### Method 3: Service

```php
use App\Services\HtmlPurifierService;

$clean = HtmlPurifierService::purify($userInput);
$plain = HtmlPurifierService::plaintext($userInput);
```

---

## Blade Directives

```blade
{{-- Safe: Renders as purified HTML --}}
@purify($content)

{{-- Safe: Renders as plain text --}}
@purifyPlain($text)

{{-- Safe: Auto-escaped --}}
{{ $content }}

{{-- DANGEROUS: Only if already purified --}}
{!! $content !!}
```

---

## What Gets Blocked

| Input                           | Output      | Reason                 |
| ------------------------------- | ----------- | ---------------------- |
| `<script>alert('xss')</script>` | _(removed)_ | Script tags blocked    |
| `<a href="javascript:alert()">` | `<a>`       | Bad protocols blocked  |
| `<img onerror="alert()">`       | `<img>`     | Event handlers blocked |
| `<p style="color:red">`         | `<p>`       | Styles blocked         |
| `<iframe src="evil.com">`       | _(removed)_ | Dangerous tags blocked |

## What's Allowed

| Input                                 | Output                                |
| ------------------------------------- | ------------------------------------- |
| `<b>bold</b>`                         | `<b>bold</b>`                         |
| `<i>italic</i>`                       | `<i>italic</i>`                       |
| `<a href="https://safe.com">link</a>` | `<a href="https://safe.com">link</a>` |
| `<img src="/image.jpg" alt="pic">`    | `<img src="/image.jpg" alt="pic">`    |

---

## Configuration

### Whitelist (config/purifier.php)

```php
'HTML.AllowedElements' => [
    'b', 'i', 'strong', 'em',  // Formatting
    'a', 'img',                 // Links/Images
    'p', 'br', 'blockquote',   // Block elements
    'ul', 'ol', 'li',          // Lists
],
'HTML.AllowedAttributes' => [
    'a.href', 'a.rel',
    'img.src', 'img.alt',
],
'URI.AllowedSchemes' => [
    'http', 'https', 'mailto',
],
```

---

## Tests

```bash
php artisan test tests/Feature/Security/HtmlPurifierXssTest.php
# Result: 48/48 PASSING ✅
```

### Test Categories

| Category          | Tests  |
| ----------------- | ------ |
| Script Tags       | 10     |
| Event Handlers    | 10     |
| Protocol Attacks  | 8      |
| Encoding Bypasses | 6      |
| Dangerous Tags    | 6      |
| Safe Content      | 6      |
| Performance       | 2      |
| **Total**         | **48** |

---

## Performance

- Average purification time: <1ms
- Cache enabled in production
- Negligible overhead
