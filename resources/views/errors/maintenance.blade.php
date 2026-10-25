@php
    use App\Settings\MaintenanceSettings;
    use App\Settings\GeneralSettings;

    try {
        $maintenance = app(MaintenanceSettings::class);
        $message = $maintenance->message;
    } catch (Throwable) {
        $message = null;
    }

    try {
        $siteName = app(GeneralSettings::class)->site_name;
    } catch (Throwable) {
        $siteName = config('app.name');
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('general.maintenance_mode') }} - {{ $siteName }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-dvh items-center justify-center bg-zinc-50 px-4 dark:bg-zinc-900">
    <div class="mx-auto max-w-lg space-y-4 text-center">
        <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655-5.653a2.548 2.548 0 0 1-.08-3.286l1.703-1.703a2.548 2.548 0 0 1 3.286.08l5.653 4.655" />
            </svg>
        </div>
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">
            {{ __('general.maintenance_mode') }}
        </h1>
        <p class="text-zinc-600 dark:text-zinc-300">
            {{ filled($message) ? $message : __('general.maintenance_default_message') }}
        </p>
    </div>
</body>
</html>
