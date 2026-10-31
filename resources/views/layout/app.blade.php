<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · MailEclipse</title>
    <link rel="stylesheet" href="{{ asset('vendor/maileclipse/css/maileclipse-app.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/maileclipse/css/fontawesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/maileclipse/css/notie.min.css') }}">
    <script src="{{ asset('vendor/maileclipse/js/maileclipse-app.js') }}"></script>
    @if (request()->routeIs('newTemplate', 'editMailable', 'viewTemplate'))
        <link rel="stylesheet" href="{{ asset('vendor/maileclipse/css/maileclipse-editor.css') }}">
        <script src="{{ asset('vendor/maileclipse/js/maileclipse-editor.js') }}"></script>
        <script src="{{ asset('vendor/maileclipse/js/tinymce/tinymce.min.js') }}"></script>
    @endif
</head>
<body>
    <a class="me-skip" href="#main-content">{{ __('Skip to content') }}</a>
    @include('maileclipse::layout.header')
    <main id="main-content" class="me-workspace">
        <div class="me-hero">
            <div>
                <div class="me-eyebrow">{{ __('Your email workspace') }}</div>
                <h1>@yield('title')</h1>
                <p>@yield('description', __('Create, refine, and preview the emails your customers receive.'))</p>
            </div>
            @if (request()->routeIs('mailableList', 'templateList'))
                <div class="me-search">
                    <label for="workspace-search" class="visually-hidden">{{ __('Search this list') }}</label>
                    <input type="search" class="form-control" id="workspace-search" placeholder="{{ __('Search by name or content…') }}" data-list-search>
                </div>
            @endif
        </div>
        <div class="row me-content-row">@yield('content')</div>
        <p class="me-no-results" data-search-empty hidden role="status">{{ __('No matches. Try a different search.') }}</p>
        @include('maileclipse::layout.footer')
    </main>
</body>
</html>
