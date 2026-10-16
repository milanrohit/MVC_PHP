<?php
declare(strict_types=1);

namespace Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/** Tiny DI container with constructor auto-wiring. */
final class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $id, Closure|string $concrete, bool $shared = false): void
    {
        $this->bindings[$id] = [$concrete, $shared];
    }

    public function singleton(string $id, Closure|string $concrete): void
    {
        $this->bind($id, $concrete, true);
    }

    public function make(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        [$concrete, $shared] = $this->bindings[$id] ?? [$id, false];
        $object = $concrete instanceof Closure ? $concrete($this) : $this->build($concrete);

        if ($shared) {
            $this->instances[$id] = $object;
        }
        return $object;
    }

    private function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Cannot resolve [$class].");
        }
        $ref = new ReflectionClass($class);
        if (!$ref->isInstantiable()) {
            throw new RuntimeException("[$class] is not instantiable; bind it in the container.");
        }
        $ctor = $ref->getConstructor();
        if (!$ctor) {
            return new $class();
        }

        $args = [];
        foreach ($ctor->getParameters() as $p) {
            $type = $p->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->make($type->getName());
            } elseif ($p->isDefaultValueAvailable()) {
                $args[] = $p->getDefaultValue();
            } else {
                throw new RuntimeException("Unresolvable parameter \${$p->getName()} in [$class].");
            }
        }
        return $ref->newInstanceArgs($args);
    }
}
