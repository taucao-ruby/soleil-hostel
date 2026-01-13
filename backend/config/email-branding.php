<?php

/**
 * Email Branding Configuration
 * 
 * Customize the visual appearance of all notification emails.
 * These values are used by the branded email templates in resources/views/vendor/notifications.
 * 
 * @see docs/backend/guides/EMAIL_NOTIFICATIONS.md
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Brand Name
    |--------------------------------------------------------------------------
    |
    | The brand name displayed in email headers and footers.
    |
    */

    'name' => env('MAIL_BRAND_NAME', 'Soleil Hostel'),

    /*
    |--------------------------------------------------------------------------
    | Brand Tagline
    |--------------------------------------------------------------------------
    |
    | A short tagline displayed in email footers.
    |
    */

    'tagline' => env('MAIL_BRAND_TAGLINE', 'Your Home Away From Home'),

    /*
    |--------------------------------------------------------------------------
    | Brand Colors
    |--------------------------------------------------------------------------
    |
    | Primary and secondary colors for email styling.
    |
    */

    'colors' => [
        'primary' => env('MAIL_COLOR_PRIMARY', '#007BFF'),
        'secondary' => env('MAIL_COLOR_SECONDARY', '#6C757D'),
        'success' => env('MAIL_COLOR_SUCCESS', '#28A745'),
        'warning' => env('MAIL_COLOR_WARNING', '#FFC107'),
        'danger' => env('MAIL_COLOR_DANGER', '#DC3545'),
        'background' => env('MAIL_COLOR_BACKGROUND', '#F8F9FA'),
        'text' => env('MAIL_COLOR_TEXT', '#212529'),
        'muted' => env('MAIL_COLOR_MUTED', '#6C757D'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logo Configuration
    |--------------------------------------------------------------------------
    |
    | Logo settings for email headers. The URL should be an absolute URL
    | for email compatibility across different email clients.
    |
    */

    'logo' => [
        'url' => env('MAIL_LOGO_URL', null), // Falls back to APP_URL/logo.png
        'alt' => env('MAIL_LOGO_ALT', 'Soleil Hostel'),
        'width' => env('MAIL_LOGO_WIDTH', '150'),
        'height' => env('MAIL_LOGO_HEIGHT', 'auto'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact Information
    |--------------------------------------------------------------------------
    |
    | Contact details displayed in email footers.
    |
    */

    'contact' => [
        'email' => env('MAIL_CONTACT_EMAIL', 'support@soleilhostel.com'),
        'phone' => env('MAIL_CONTACT_PHONE', '+1 (555) 123-4567'),
        'address' => env('MAIL_CONTACT_ADDRESS', '123 Sunny Beach Road, Paradise City'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Links
    |--------------------------------------------------------------------------
    |
    | Social media links displayed in email footers.
    | Set to null to hide individual social links.
    |
    */

    'social' => [
        'website' => env('MAIL_SOCIAL_WEBSITE', null), // Falls back to APP_URL
        'facebook' => env('MAIL_SOCIAL_FACEBOOK', null),
        'instagram' => env('MAIL_SOCIAL_INSTAGRAM', null),
        'twitter' => env('MAIL_SOCIAL_TWITTER', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Footer Configuration
    |--------------------------------------------------------------------------
    |
    | Additional footer settings.
    |
    */

    'footer' => [
        'copyright' => env('MAIL_FOOTER_COPYRIGHT', '© ' . date('Y') . ' Soleil Hostel. All rights reserved.'),
        'unsubscribe_text' => env('MAIL_FOOTER_UNSUBSCRIBE', null), // Optional unsubscribe notice
    ],

];
