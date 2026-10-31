<header class="me-topbar">
    <div class="me-header">
        <a class="me-brand" href="{{ route('mailableList') }}" aria-label="MailEclipse home">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/></svg>
            <span><strong>MailEclipse</strong><small>EMAIL STUDIO</small></span>
        </a>
        <nav class="me-nav" aria-label="{{ __('Main navigation') }}">
            <a href="{{ route('mailableList') }}" @class(['active' => request()->routeIs('mailableList', 'viewMailable', 'editMailable')]) @if(request()->routeIs('mailableList', 'viewMailable', 'editMailable')) aria-current="page" @endif>{{ __('Mailables') }}</a>
            <a href="{{ route('templateList') }}" @class(['active' => request()->routeIs('templateList', 'selectNewTemplate', 'newTemplate', 'viewTemplate')]) @if(request()->routeIs('templateList', 'selectNewTemplate', 'newTemplate', 'viewTemplate')) aria-current="page" @endif>{{ __('Templates') }}</a>
        </nav>
        <div class="me-header-tools">
            <label for="theme-select" class="visually-hidden">{{ __('Appearance') }}</label>
            <select id="theme-select" class="form-select me-theme" data-theme-select>
                <option value="system">{{ __('System') }}</option>
                <option value="light">{{ __('Light') }}</option>
                <option value="dark">{{ __('Dark') }}</option>
            </select>
        </div>
    </div>
</header>
