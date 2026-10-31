<?php

namespace Qoraiche\MailEclipse\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Qoraiche\MailEclipse\MailEclipse;
use ReflectionClass;
use Throwable;

/** Each item has its own failure boundary so the workspace always remains usable. */
class MailableInspector
{
    public function all(): Collection
    {
        $directory = config('maileclipse.mailables_dir');
        if (! is_dir($directory)) {
            return collect();
        }

        $items = collect();
        foreach (File::allFiles($directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $item = [
                'name' => $file->getBasename('.php'), 'namespace' => '',
                'filename' => $file->getFilename(), 'path_name' => $file->getPathname(),
                'modified' => $file->getMTime(), 'viewed' => $file->getATime(),
                'readable' => $file->isReadable(), 'writable' => $file->isWritable(),
                'status' => 'valid', 'error' => null, 'error_class' => null,
                'data' => null, 'markdown' => null, 'subject' => '', 'view_data' => [],
                'view_path' => null, 'text_view_path' => null, 'text_view' => null,
            ];

            try {
                $class = $this->className($file->getContents());
                $item['namespace'] = $class ?? '';
                if ($class === null || ! is_a($class, \Illuminate\Mail\Mailable::class, true)) {
                    continue;
                }
                if ((new ReflectionClass($class))->isAbstract()) {
                    continue;
                }

                $instance = MailEclipse::buildMailable($class);
                $item['data'] = $instance;
                $item['markdown'] = (new ReflectionClass($instance))->getProperty('markdown')->getValue($instance);
                $item['subject'] = $instance->subject ?? $item['name'];
                $view = $item['markdown'] ?? $instance->view;
                if (is_string($view)) {
                    $item['view_path'] = View::make($view)->getPath();
                }
                if ($instance->textView !== null) {
                    $item['text_view'] = $instance->textView;
                    $item['text_view_path'] = View::make($instance->textView)->getPath();
                }

                $item['view_data'] = collect($instance->buildViewData())
                    ->reject(fn ($value, $key) => str_starts_with($key, '__'))
                    ->map(fn ($value, $key) => ['key' => $key, 'value' => $value, 'data' => $this->describe($value)])
                    ->values()->all();

                if (config('maileclipse.validate_templates', true)) {
                    // Rendering also catches missing includes and invalid data access.
                    // Clone so validation doesn't mutate the inspected instance.
                    (clone $instance)->render();
                }
            } catch (Throwable $error) {
                $item['status'] = 'invalid';
                $item['error'] = $error->getMessage();
                $item['error_class'] = class_basename($error);
            }

            $items->push($item);
        }

        return $items;
    }

    private function describe(mixed $value): ?array
    {
        return match (true) {
            $value instanceof Model => ['type' => 'model', 'attributes' => collect($value->getAttributes())],
            $value instanceof \Illuminate\Database\Eloquent\Collection => ['type' => 'elequent-collection', 'attributes' => $value->all()],
            $value instanceof Collection => ['type' => 'collection', 'attributes' => $value->all()],
            default => null,
        };
    }

    private function className(string $source): ?string
    {
        $tokens = token_get_all($source);
        $namespace = '';
        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            if (! is_array($tokens[$i])) continue;
            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($i++; $i < $count && ! in_array($tokens[$i], [';', '{'], true); $i++) {
                    if (is_array($tokens[$i])) $namespace .= $tokens[$i][1];
                }
                $namespace = trim($namespace);
            } elseif ($tokens[$i][0] === T_CLASS) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        return ltrim($namespace.'\\'.$tokens[$j][1], '\\');
                    }
                    break;
                }
            }
        }
        return null;
    }
}
