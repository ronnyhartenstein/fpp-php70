<?php

declare(strict_types=1);

namespace Fpp\Builder;

use Fpp\Constructor;
use Fpp\Definition;
use Fpp\DefinitionCollection;
use Fpp\Deriving;

const buildStaticConstructorBody = '\Fpp\Builder\buildStaticConstructorBody';

function buildStaticConstructorBody(Definition $definition, ?Constructor $constructor, DefinitionCollection $collection, string $placeHolder): string
{
    foreach ($definition->derivings() as $deriving) {
        if ($deriving->equals(new Deriving\AggregateChanged())) {
            $inclFirstArgument = false;
        }

        if ($deriving->equals(new Deriving\Command())
            || $deriving->equals(new Deriving\DomainEvent())
            || $deriving->equals(new Deriving\Query())
        ) {
            $inclFirstArgument = true;
        }
    }

    if (! isset($inclFirstArgument)) {
        return $placeHolder;
    }

    $start = 'return new self(';
    if ($inclFirstArgument) {
        $start .= "[\n                ";
    }
    $code = '';

    $addArgument = function (int $key, string $name, string $value) use ($inclFirstArgument): string {
        if (false === $inclFirstArgument && 0 === $key) {
            return "$value, [\n";
        }

        return "                '{$name}' => {$value},\n";
    };

    foreach ($constructor->arguments() as $key => $argument) {
        if ($argument->isScalartypeHint() || null === $argument->type()) {
            $code .= $addArgument($key, $argument->name(), "\${$argument->name()}");
            continue;
        }

        $position = strrpos($argument->type(), '\\');

        if (false !== $position) {
            $namespace = substr($argument->type(), 0, $position);
            $name = substr($argument->type(), $position + 1);
        } else {
            $namespace = '';
            $name = $argument->type();
        }

        if ($collection->hasDefinition($namespace, $name)) {
            $definition = $collection->definition($namespace, $name);
        } elseif ($collection->hasConstructorDefinition($argument->type())) {
            $definition = $collection->constructorDefinition($argument->type());
        } else {
            $code .= $addArgument($key, $argument->name(), "\${$argument->name()}");
            continue;
        }

        foreach ($definition->derivings() as $deriving) {
            switch ((string) $deriving) {
                case Deriving\ToArray::VALUE:
                    $value = $argument->nullable()
                        ? "null === \${$argument->name()} ? null : \${$argument->name()}->toArray()"
                        : "\${$argument->name()}->toArray()";
                    $code .= $addArgument($key, $argument->name(), $value);
                    continue 3;
                case Deriving\ToScalar::VALUE:
                    $value = $argument->nullable()
                        ? "null === \${$argument->name()} ? null : \${$argument->name()}->toScalar()"
                        : "\${$argument->name()}->toScalar()";
                    $code .= $addArgument($key, $argument->name(), $value);
                    continue 3;
                case Deriving\Enum::VALUE:
                case Deriving\ToString::VALUE:
                case Deriving\Uuid::VALUE:
                    $value = $argument->nullable()
                        ? "null === \${$argument->name()} ? null : \${$argument->name()}->toString()"
                        : "\${$argument->name()}->toString()";
                    $code .= $addArgument($key, $argument->name(), $value);
                    continue 3;
            }
        }

        $code .= $addArgument($key, $argument->name(), "\${$argument->name()}");
    }

    return $start . ltrim($code) . '            ]);';
}
