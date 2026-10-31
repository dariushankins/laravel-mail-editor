<?php

namespace Qoraiche\MailEclipse;

use Illuminate\Http\JsonResponse;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Qoraiche\MailEclipse\Utils\Replacer;
use Qoraiche\MailEclipse\Support\MailableResolver;
use ReflectionClass;
use ReflectionProperty;

/**
 * Class MailEclipse.
 */
class MailEclipse
{
    public const VIEW_NAMESPACE = 'maileclipse';

    public const VERSION = '5.0.0-dev';

    /**
     * Default type examples for being passed to reflected classes.
     *
     * @var array TYPES
     */
    public const TYPES = [
        'int' => 31,
        'string' => null,
        'bool' => false,
        'float' => 3.14159,
        'iterable' => null,
        'array' => null,
    ];

    public static $traversed = 0;

    public static ?string $templateIndexError = null;

    /**
     * @return array
     *
     * @throws \ReflectionException
     */
    public static function getMailables()
    {
        return self::mailablesList();
    }

    /**
     * @param $key
     * @param $name
     * @return Collection
     *
     * @throws \ReflectionException
     */
    public static function getMailable($key, $name): Collection
    {
        return collect(self::getMailables())->where($key, $name);
    }

    /**
     * @param $templateSlug
     * @return bool
     */
    public static function deleteTemplate($templateSlug): bool
    {
        $template = self::getTemplates()
            ->where('template_slug', $templateSlug)->first();

        if ($template !== null) {
            self::saveTemplates(self::getTemplates()->reject(function ($value) use ($template) {
                return $value->template_slug === $template->template_slug;
            }));

            $template_view = self::VIEW_NAMESPACE.'::templates.'.$templateSlug;
            $template_plaintext_view = $template_view.'_plain_text';

            if (View::exists($template_view)) {
                unlink(View($template_view)->getPath());

                // remove plain text template version when exists
                if (View::exists($template_plaintext_view)) {
                    unlink(View($template_plaintext_view)->getPath());
                }

                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public static function getTemplatesFile()
    {
        $file = rtrim(config('maileclipse.mailables_dir'), '/\\').'/templates.json';
        if (! file_exists($file)) {
            if (! file_exists(config('maileclipse.mailables_dir'))) {
                if (! mkdir($concurrentDirectory = config('maileclipse.mailables_dir'), 0755, true) && ! is_dir($concurrentDirectory)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                }
            }
            file_put_contents($file, '[]');
        }

        return $file;
    }

    /**
     * Save templates to templates.json file.
     *
     * @param  Collection  $templates
     */
    public static function saveTemplates(Collection $templates): void
    {
        // Never overwrite a damaged index with the empty fallback collection.
        self::getTemplates();
        abort_if(self::$templateIndexError !== null, 422, 'The template index is invalid. Repair templates.json before saving.');
        $records = $templates->map(function ($template) {
            $template = (array) $template;
            unset($template['status'], $template['error']);
            return $template;
        })->values();
        file_put_contents(self::getTemplatesFile(), $records->toJson(), LOCK_EX);
    }

    /**
     * @param $request
     * @return JsonResponse|null
     */
    public static function updateTemplate($request): ?JsonResponse
    {
        $template = self::getTemplates()
            ->where('template_slug', $request->templateslug)->first();

        if ($template !== null) {
            if (! preg_match("/^[a-zA-Z0-9-_\s]+$/", $request->title)) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Template name not valid',
                ]);
            }

            $templateName = Str::camel(preg_replace('/\s+/', '_', $request->title));

            if ($templateName !== $template->template_slug && self::getTemplates()->contains('template_slug', '=', $templateName)) {
                return response()->json([

                    'status' => 'failed',
                    'message' => 'Template name already exists',

                ]);
            }

            // Update
            $oldForm = self::getTemplates()->reject(function ($value) use ($template) {
                return $value->template_slug === $template->template_slug;
            });
            $newForm = array_merge($oldForm->toArray(), [array_merge((array) $template, [
                'template_slug' => $templateName,
                'template_name' => $request->title,
                'template_description' => $request->description,
            ])]);

            self::saveTemplates(collect($newForm));

            $template_view = self::VIEW_NAMESPACE.'::templates.'.$request->templateslug;
            $template_plaintext_view = $template_view.'_plain_text';

            if (View::exists($template_view)) {
                $viewPath = View($template_view)->getPath();

                rename($viewPath, dirname($viewPath)."/{$templateName}.blade.php");

                if (View::exists($template_plaintext_view)) {
                    $textViewPath = View($template_plaintext_view)->getPath();

                    rename($textViewPath, dirname($viewPath)."/{$templateName}_plain_text.blade.php");
                }
            }

            return response()->json([
                'status' => 'ok',
                'message' => 'Updated Successfully',
                'template_url' => route('viewTemplate', ['templatename' => $templateName]),
            ]);
        }
    }

    /**
     * @param $templateSlug
     * @return Collection|null
     */
    public static function getTemplate($templateSlug): ?Collection
    {
        $template = self::getTemplates()->where('template_slug', $templateSlug)->first();

        if ($template !== null) {
            $template_view = self::VIEW_NAMESPACE.'::templates.'.$template->template_slug;
            $template_plaintext_view = $template_view.'_plain_text';

            if (View::exists($template_view)) {
                $viewPath = View($template_view)->getPath();
                $textViewPath = View::exists($template_plaintext_view) ? View($template_plaintext_view)->getPath() : null;

                /** @var Collection $templateData */
                $templateData = collect([
                    'template' => Replacer::toEditor(file_get_contents($viewPath)),
                    'plain_text' => View::exists($template_plaintext_view) ? file_get_contents($textViewPath) : '',
                    'slug' => $template->template_slug,
                    'name' => $template->template_name,
                    'description' => $template->template_description,
                    'template_type' => $template->template_type,
                    'template_view_name' => $template->template_view_name,
                    'template_skeleton' => $template->template_skeleton,
                    'status' => $template->status,
                    'error' => $template->error,
                ]);

                return $templateData;
            }

            return collect([
                'name' => $template->template_name, 'slug' => $template->template_slug,
                'missing' => true, 'status' => 'invalid', 'error' => $template->error,
                'expected_path' => resource_path('views/vendor/maileclipse/templates/'.$template->template_slug.'.blade.php'),
            ]);
        }

        return null;
    }

    /**
     * Get templates collection.
     *
     * @return Collection
     */
    public static function getTemplates(): Collection
    {
        self::$templateIndexError = null;
        try {
            $records = json_decode(file_get_contents(self::getTemplatesFile()), false, 512, JSON_THROW_ON_ERROR);
            if (! is_array($records)) {
                throw new \RuntimeException('The template index must be a JSON array.');
            }
        } catch (\Throwable $error) {
            self::$templateIndexError = $error->getMessage();
            return collect();
        }

        return collect($records)->map(function ($record) {
            if (! is_object($record)) $record = (object) [];
            $record->status = 'valid';
            $record->error = null;
            foreach (['template_slug', 'template_name', 'template_description', 'template_type', 'template_view_name', 'template_skeleton'] as $field) {
                if (! isset($record->{$field}) || ! is_string($record->{$field})) {
                    $record->{$field} = '';
                    $record->status = 'invalid';
                    $record->error = 'The template index entry has missing or invalid fields.';
                }
            }
            try {
                $path = View::make(self::VIEW_NAMESPACE.'::templates.'.$record->template_slug)->getPath();
                $error = app(\Qoraiche\MailEclipse\Support\TemplateValidator::class)->validate(file_get_contents($path));
                if ($error !== null) { $record->status = 'invalid'; $record->error = $error; }
            } catch (\Throwable $error) {
                $record->status = 'invalid';
                $record->error = $error->getMessage();
            }
            return $record;
        });
    }

    /**
     * @param $request
     * @return JsonResponse
     */
    public static function createTemplate($request): JsonResponse
    {
        $request->validate([
            'template_name' => 'required|string|max:128',
            'template_description' => 'nullable|string|max:2000',
            'template_type' => 'required|in:html,markdown',
            'template_view_name' => 'required|string|max:128',
            'template_skeleton' => 'required|string|max:128',
            'content' => 'required|string',
            'plain_text' => 'nullable|string',
        ]);
        if (! preg_match("/^[a-zA-Z0-9-_\s]+$/", $request->template_name)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Template name not valid',

            ]);
        }

        $error = app(\Qoraiche\MailEclipse\Support\TemplateValidator::class)->validate(Replacer::toBlade($request->content));
        abort_if($error !== null, 422, 'Invalid template: '.$error);
        $view = self::VIEW_NAMESPACE.'::templates.'.$request->template_name;
        $templateName = Str::camel(preg_replace('/\s+/', '_', $request->template_name));

        if (! view()->exists($view) && ! self::getTemplates()->contains('template_slug', '=', $templateName)) {
            self::saveTemplates(self::getTemplates()
                ->push([
                    'template_name' => $request->template_name,
                    'template_slug' => $templateName,
                    'template_description' => $request->template_description,
                    'template_type' => $request->template_type,
                    'template_view_name' => $request->template_view_name,
                    'template_skeleton' => $request->template_skeleton,
                ]));

            $dir = resource_path('views/vendor/'.self::VIEW_NAMESPACE.'/templates');

            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            file_put_contents($dir."/{$templateName}.blade.php", Replacer::toBlade($request->content));

            file_put_contents($dir."/{$templateName}_plain_text.blade.php", $request->plain_text ?? '');

            return response()->json([
                'status' => 'ok',
                'message' => 'Template created<br> <small>Reloading...<small>',
                'template_url' => route('viewTemplate', ['templatename' => $templateName]),
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Template not created',

        ]);
    }

    protected static function markdownedTemplate($viewPath)
    {
        $viewContent = file_get_contents($viewPath);

        return Replacer::toEditor($viewContent);
    }

    /**
     * Markdowned template view.
     */
    public static function markdownedTemplateToView($save = true, $content = '', $viewPath = '', $template = false)
    {
        if ($template && View::exists(self::VIEW_NAMESPACE.'::templates.'.$viewPath)) {
            $viewPath = View(self::VIEW_NAMESPACE.'::templates.'.$viewPath)->getPath();
        }

        $replaced = Replacer::toBlade($content);

        if (! $save) {
            return $replaced;
        }

        $error = app(\Qoraiche\MailEclipse\Support\TemplateValidator::class)->validate($replaced);
        abort_if($error !== null, 422, 'Invalid template: '.$error);
        $resolved = realpath($viewPath);
        $root = realpath(resource_path('views'));
        abort_unless($resolved && $root && str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) && str_ends_with($resolved, '.blade.php'), 422, 'Only application Blade views can be edited.');

        return file_put_contents($resolved, $replaced, LOCK_EX) !== false;
    }

    /**
     * @param $simpleview
     * @param $content
     * @param $viewName
     * @param  bool  $template
     * @param  null  $namespace
     * @return bool|string|void
     *
     * @throws \ReflectionException
     */
    public static function previewMarkdownViewContent($simpleview, $content, $viewName, $template = false, $namespace = null)
    {
        $previewtoset = self::markdownedTemplateToView(false, $content);
        $dir = storage_path('framework/maileclipse');
        File::ensureDirectoryExists($dir);
        View::addNamespace('maileclipse-draft', $dir);
        $viewName = bin2hex(random_bytes(16));

        if (file_exists($dir)) {
            file_put_contents($dir."/{$viewName}.blade.php", $previewtoset);

            $instance = $template ? null : self::handleMailableViewDataArgs($namespace);

            try {
                return self::renderPreview($simpleview, 'maileclipse-draft::'.$viewName, $template, $instance);
            } finally {
                $path = $dir.'/'.$viewName.'.blade.php';
                @unlink(app('blade.compiler')->getCompiledPath($path));
                @unlink($path);
            }
        }

        return false;
    }

    /**
     * @param $instance
     * @param $view
     * @return string|void
     */
    public static function previewMarkdownHtml($instance, $view)
    {
        return self::renderPreview(true, $view, false, $instance);
    }

    /**
     * @param $mailableName
     * @return array|bool
     */
    public static function getMailableTemplateData($mailableName)
    {
        $mailable = self::getMailable('name', $mailableName);

        if ($mailable->isEmpty()) {
            return false;
        }

        $templateData = collect($mailable->first())->only(['markdown', 'view_path', 'text_view_path', 'text_view', 'view_data', 'data', 'namespace', 'status', 'error'])->all();

        $templateExists = $templateData['view_path'] !== null;
        $textTemplateExists = $templateData['text_view_path'] !== null;

        if ($templateExists) {
            $viewPathParams = collect($templateData)->union([

                'text_template' => $textTemplateExists ? file_get_contents($templateData['text_view_path']) : null,
                'template' => file_get_contents($templateData['view_path']),
                'markdowned_template' => self::markdownedTemplate($templateData['view_path']),
                'template_name' => $templateData['markdown'] ?? $templateData['data']->view,
                'is_markdown' => $templateData['markdown'] !== null,
            ])->all();

            return $viewPathParams;
        }

        return array_merge($templateData, ['text_template' => null]);
    }

    /**
     * @param  null  $request
     * @return JsonResponse
     */
    public static function generateMailable($request = null): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:128', 'markdown' => 'nullable|string|max:255']);
        $name = self::generateClassName($request->input('name'));
        $defaultDirectory = 'Mail';

        $mailableDir = config('maileclipse.mailables_dir');
        $customPath = substr($mailableDir, strpos($mailableDir, $defaultDirectory) + strlen($defaultDirectory) + 1);

        if ($name === false) {
            return response()->json([
                'status' => 'error',
                'message' => 'Wrong name format.',
            ]);
        }

        if (! $request->has('force') && ! self::getMailable('name', $name)->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This mailable name already exists. names should be unique! to override it, enable "force" option.',
            ]);
        }

        if (strtolower($name) === 'mailable') {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot use "mailable" as a mailable name',
            ]);
        }

        $name = $customPath ? $customPath.'/'.$name : $name;

        $params = collect([
            'name' => $name,
        ]);

        if ($request->input('markdown')) {
            $params->put('--markdown', $request->markdown);
        }

        if ($request->has('force')) {
            $params->put('--force', true);
        }

        $exitCode = Artisan::call('make:mail', $params->all());

        if ($exitCode === 0) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Mailable Created<br> <small>Reloading...<small>',
            ]);
        }

        return response()->json([

            'status' => 'error',
            'message' => 'mailable not created successfully',

        ]);
    }

    /**
     * Get Mailables list.
     *
     * @return array
     *
     * @throws \ReflectionException
     */
    protected static function mailablesList()
    {
        return app(\Qoraiche\MailEclipse\Support\MailableInspector::class)->all();
    }

    /**
     * Handle Mailable Constructor arguments and pass the fake ones.
     *
     * @param $mailable
     * @return object|void
     *
     * @throws \ReflectionException
     */
    public static function handleMailableViewDataArgs($mailable)
    {
        return app(MailableResolver::class)->resolve($mailable);
    }

    /**
     * @param $mailable
     * @param $mailable_data
     * @return array|Collection
     *
     * @throws \ReflectionException
     */
    private static function getMailableViewData($mailable, $mailable_data)
    {
        $traitProperties = [];

        $data = new ReflectionClass($mailable);

        foreach ($data->getTraits() as $trait) {
            foreach ($trait->getProperties(ReflectionProperty::IS_PUBLIC) as $traitProperty) {
                $traitProperties[] = $traitProperty->name;
            }
        }

        $properties = $data->getProperties(ReflectionProperty::IS_PUBLIC);
        $allProps = [];

        foreach ($properties as $prop) {
            if ($prop->class == $data->getName() || $prop->class == get_parent_class($data->getName()) &&
                    get_parent_class($data->getName()) != 'Illuminate\Mail\Mailable' && ! $prop->isStatic()) {
                $allProps[] = $prop->name;
            }
        }

        $obj = $mailable_data;

        if ($obj === null) {
            $obj = [];

            return collect($obj);
        }

        $classProps = array_diff($allProps, $traitProperties);

        $withFuncData = collect($obj->buildViewData())->keys();

        $mailableData = collect($classProps)->merge($withFuncData);

        $values = $obj->buildViewData();
        $data = $mailableData->unique()->map(function ($parameter) use ($values) {
            return [
                'key' => $parameter,
                'value' => $values[$parameter] ?? null,
                'data' => self::viewDataInspect($values[$parameter] ?? null),
            ];
        });

        return $data->all();
    }

    /**
     * @param $param
     * @return array
     */
    protected static function viewDataInspect($param): ?array
    {
        if ($param instanceof \Illuminate\Database\Eloquent\Model) {
            return [
                'type' => 'model',
                'attributes' => collect($param->getAttributes()),
            ];
        }

        if ($param instanceof \Illuminate\Database\Eloquent\Collection) {
            return [
                'type' => 'elequent-collection',
                'attributes' => $param->all(),
            ];
        }

        if ($param instanceof \Illuminate\Support\Collection) {
            return [
                'type' => 'collection',
                'attributes' => $param->all(),
            ];
        }

        return null;
    }

    /**
     * @param $mailable
     * @return bool
     */
    protected static function mailable_exists($mailable): bool
    {
        if (! is_a($mailable, \Illuminate\Mail\Mailable::class, true)) {
            return false;
        }

        return true;
    }

    /**
     * @param $mailable
     * @return mixed|void
     *
     * @throws \ReflectionException
     */
    protected static function getMarkdownViewName($mailable)
    {
        return $mailable === null ? null : (new ReflectionClass($mailable))
            ->getProperty('markdown')->getValue($mailable);
    }

    public static function buildMailable($instance, $type = 'call')
    {
        if ($type !== 'call') {
            return app()->make($instance);
        }

        $mailable = self::handleMailableViewDataArgs($instance);

        if (method_exists($mailable, 'build')) {
            app()->call([$mailable, 'build']);
        }

        if (method_exists($mailable, 'envelope')) {
            $envelope = app()->call([$mailable, 'envelope']);
            if ($envelope->subject !== null) {
                $mailable->subject($envelope->subject);
            }
        }

        if (method_exists($mailable, 'content')) {
            $content = app()->call([$mailable, 'content']);
            foreach (['view', 'html', 'text', 'markdown', 'htmlString'] as $property) {
                if ($content->{$property} !== null) {
                    $method = match ($property) {
                        'html' => 'view',
                        'htmlString' => 'html',
                        default => $property,
                    };
                    $mailable->{$method}($content->{$property});
                }
            }
            $mailable->with($content->with);
        }

        return $mailable;
    }

    /**
     * @param $simpleview
     * @param $view
     * @param  bool  $template
     * @param  null  $instance
     * @return string|void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \ReflectionException
     */
    public static function renderPreview($simpleview, $view, $template = false, $instance = null)
    {
        if (! View::exists($view)) {
            return;
        }

        if (! $template) {
            $obj = self::buildMailable($instance);
            $viewData = $obj->buildViewData();
            $_data = array_merge($instance->buildViewData(), $viewData);

        } else {
            $_data = [];
        }

        $_view = $view;

        try {
            if ($simpleview) {
                return view($_view, $_data)->render();
            }

            $_md = self::buildMailable(Markdown::class, 'make');

            return (string) $_md->render($_view, $_data);
        } catch (\Throwable $e) {
            $error = '<div class="alert alert-warning">
	    	<h5 class="alert-heading">Error:</h5>
            <p>'.e($e->getMessage()).'</p>
	    	</div>';

            if ($template) {
                $error .= '<div class="alert alert-info">
				<h5 class="alert-heading">Notice:</h5>
				<p>You can\'t add variables within a template editor because they are undefined until you bind the template with a mailable that actually has data.</p>
	    	</div>';
            }

            return $error;
        }
    }

    /**
     * Class name has to satisfy those rules.
     *
     * https://www.php.net/manual/en/language.oop5.basic.php#language.oop5.basic.class
     * https://www.php.net/manual/en/reserved.keywords.php
     *
     * @param $input
     * @return string|false class name or false on failure
     */
    public static function generateClassName($input)
    {
        $suffix = 'Mail';

        if (strtolower($input) === strtolower($suffix)) {
            return false;
        }

        // Avoid MailMail as a class name suffix
        $suffix = substr_compare($input, 'mail', -4, 4, true) === 0
            ? ''
            : $suffix;

        /**
         * - Suffix is needed to avoid usage of reserved word.
         * - Str::slug will remove all forbidden characters.
         */
        $name = Str::studly(Str::slug($input, '_')).$suffix;

        /**
         * Removal of reserved keywords.
         */
        if (! preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $name) ||
            substr_compare($name, $suffix, -strlen($suffix), strlen($suffix), true) !== 0
        ) {
            return false;
        }

        return $name;
    }

    /**
     * @param  string  $name
     * @return bool|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     *
     * @throws \ReflectionException
     */
    public static function renderMailable(string $name)
    {
        $mailable = self::getMailable('name', $name)->first();

        abort_if($mailable === null, 404, 'Mailable not found.');
        if ($mailable['status'] === 'invalid') {
            return view(self::VIEW_NAMESPACE.'::previewerror', ['errorMessage' => $mailable['error']]);
        }

        $mailableInstance = self::resolveMailableInstance($mailable);

        $view = $mailable['markdown'] ?? $mailable['data']->view;

        if ($view === null || view()->exists($view)) {
            return $mailableInstance->render();
        }

        return view(self::VIEW_NAMESPACE.'::previewerror', ['errorMessage' => 'No template associated with this mailable.']);
    }

    /**
     * @param  string  $name
     * @param  string  $recipient
     */
    public static function sendTest(string $name, string $recipient): void
    {
        $mailable = self::getMailable('name', $name)->first();

        abort_if($mailable === null, 404, 'Mailable not found.');
        abort_if($mailable['status'] === 'invalid', 422, $mailable['error']);
        $mailableInstance = self::resolveMailableInstance($mailable);

        $mailableInstance = self::setMailableSendTestRecipient($mailableInstance, $recipient);

        // Laravel applies alwaysTo after build/envelope callbacks. Clone so the host
        // application's mailer is never left redirecting subsequent real emails.
        $mailer = clone Mail::mailer($mailableInstance->mailer ?? config('mail.default'));
        $mailer->alwaysTo($recipient);
        $mailer->send($mailableInstance);
    }

    /**
     * @param $mailable
     * @param  string  $email
     * @return mixed
     */
    public static function setMailableSendTestRecipient($mailable, string $email)
    {
        $mailable->to = [];
        $mailable->cc = [];
        $mailable->bcc = [];
        $mailable->to($email);
        // Runs after build(), envelope() and the mailable's own Symfony callbacks.
        $mailable->withSymfonyMessage(static function ($message) use ($email) {
            $message->to($email);
            $message->cc();
            $message->bcc();
        });

        return $mailable;
    }

    /**
     * @param $mailable
     * @return object|void
     *
     * @throws \ReflectionException
     */
    private static function resolveMailableInstance($mailable)
    {
        abort_if($mailable === null, 404, 'Mailable not found.');

        return self::handleMailableViewDataArgs($mailable['namespace']);
    }
}
