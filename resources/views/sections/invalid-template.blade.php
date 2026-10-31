@extends('maileclipse::layout.app')
@section('title', $template['name'])
@section('description', 'This saved template needs attention. Your other templates are still available.')
@section('content')
<div class="col-12"><div class="card"><div class="card-body">
    <div class="alert alert-danger" role="alert"><strong>{{ __('Template check failed') }}</strong><p class="mb-0 me-validation-error">{{ $template['error'] }}</p></div>
    <p>{{ __('The source file is missing or unreadable. Restore it at the path below, then reload this page.') }}</p>
    <code class="text-break">{{ $template['expected_path'] }}</code>
    <div class="mt-4"><a class="btn btn-secondary" href="{{ route('templateList') }}">{{ __('Back to templates') }}</a></div>
</div></div></div>
@endsection
