<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Email preview unavailable') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/maileclipse/css/maileclipse-app.min.css') }}">
</head>
<body>
    <main class="container py-4">
        <div class="alert alert-danger" role="alert">
            <strong>{{ __('This template needs attention') }}</strong>
            <p class="me-validation-error mb-0">{{ $errorMessage ?? (isset($exception) ? $exception->getMessage() : __('The preview could not be rendered.')) }}</p>
        </div>
    </main>
</body>
</html>
