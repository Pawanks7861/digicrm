<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        @php
            $faviconHref = null;
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('billing_settings')) {
                    $brandRow = \App\Models\BillingSetting::query()->first();
                    if ($brandRow?->favicon_path) {
                        $faviconHref = app(\App\Services\CompanyBrandingService::class)->publicUrl($brandRow->favicon_path, $brandRow->updated_at);
                    }
                }
            } catch (\Throwable) {
                $faviconHref = null;
            }
        @endphp
        <link rel="icon" href="{{ $faviconHref ?: asset('favicon.ico') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
