<div class="alert alert-danger mb-4" role="alert">
    <strong>{{ __('This template is marked as bad') }}</strong>
    <p>{{ __('The workspace is still available. Correct the template below, or check the preview data if the source is valid. It will be checked again after saving.') }}</p>
    <details><summary>{{ __('View error details') }}</summary><pre class="me-validation-error mt-2">{{ $validationError }}</pre></details>
</div>
