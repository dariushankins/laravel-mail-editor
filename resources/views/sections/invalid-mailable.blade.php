@extends('maileclipse::layout.app')
@section('title', $resource['name'])
@section('description', 'This mailable needs attention. Your other emails are still available.')
@section('content')
<div class="col-12">
    <div class="card">
        <div class="card-header"><h5>{{ __('Preview check failed') }}</h5></div>
        <div class="card-body">
            <div class="alert alert-danger" role="alert">
                <strong>{{ __('This item is marked as bad') }}</strong>
                <p class="mb-0">{{ __('It could not be built or rendered with the configured preview data. Check its Blade syntax, view references, and constructor data.') }}</p>
            </div>
            <dl>
                <dt>{{ __('Mailable') }}</dt><dd class="text-break">{{ $resource['namespace'] ?: $resource['filename'] }}</dd>
                <dt>{{ __('Source') }}</dt><dd class="text-break">{{ $resource['path_name'] }}</dd>
                <dt>{{ __('Error') }} · {{ $resource['error_class'] }}</dt><dd><pre class="me-validation-error">{{ $resource['error'] }}</pre></dd>
            </dl>
            <a class="btn btn-secondary" href="{{ route('mailableList') }}">{{ __('Back to mailables') }}</a>
            @if($resource['view_path'])
                <a class="btn btn-primary" href="{{ route('editMailable', ['name' => $resource['name']]) }}">{{ __('Repair template') }}</a>
            @endif
        </div>
    </div>
</div>
@endsection
