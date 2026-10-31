<?php

namespace Qoraiche\MailEclipse\Support;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;

/** Resolve preview arguments without inserting records or opening transactions. */
class MailableResolver
{
    public function resolve(string|object $mailable): object
    {
        if (is_object($mailable)) {
            return $mailable;
        }

        $reflection = new ReflectionClass($mailable);
        $values = config('maileclipse.preview_data.'.$mailable, []);
        $arguments = [];

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->isVariadic()) {
                continue;
            }

            $name = $parameter->getName();
            $arguments[] = array_key_exists($name, $values)
                ? $values[$name]
                : $this->argument($parameter);
        }

        return $reflection->newInstanceArgs($arguments);
    }

    private function argument(ReflectionParameter $parameter): mixed
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        return $this->example($parameter->getType(), $parameter->getName());
    }

    private function example(?ReflectionType $type, string $name): mixed
    {
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $candidate) {
                try {
                    return $this->example($candidate, $name);
                } catch (RuntimeException) {
                    // Try the next declared type. Intersection types require explicit data.
                }
            }
        }

        if ($type instanceof ReflectionNamedType) {
            if ($type->isBuiltin()) {
                return match ($type->getName()) {
                    'int' => 31,
                    'float' => 3.14159,
                    'bool', 'false' => false,
                    'true' => true,
                    'array', 'iterable' => [],
                    'object' => new \stdClass,
                    'callable' => static fn () => null,
                    'string', 'mixed' => 'Example '.$name,
                    default => null,
                };
            }

            $class = $type->getName();
            if (is_a($class, Model::class, true)) {
                // make() uses modern HasFactory factories and leaves models unsaved.
                return config('maileclipse.factory', true) && method_exists($class, 'factory')
                    ? $class::factory()->make()
                    : new $class;
            }

            if (enum_exists($class)) {
                return $class::cases()[0] ?? throw new RuntimeException('Enum has no cases: '.$class);
            }

            if (app()->bound($class) || (class_exists($class) && (new ReflectionClass($class))->isInstantiable())) {
                return app()->make($class);
            }
        }

        throw new RuntimeException(sprintf(
            'Cannot generate preview data for $%s (%s). Set maileclipse.preview_data for this mailable.',
            $name,
            $type instanceof ReflectionIntersectionType ? (string) $type : ($type?->__toString() ?? 'untyped')
        ));
    }
}
