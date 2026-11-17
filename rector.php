<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use RectorLaravel\Rector\Class_\BackoffPropertyToBackoffAttributeRector;
use RectorLaravel\Rector\Class_\MaxExceptionsPropertyToMaxExceptionsAttributeRector;
use RectorLaravel\Rector\Class_\TriesPropertyToTriesAttributeRector;
use RectorLaravel\Rector\Class_\UniqueForPropertyToUniqueForAttributeRector;
use RectorLaravel\Rector\ClassMethod\MigrateToSimplifiedAttributeRector;

/*
| Keeps the code on the newest PHP syntax the composer.json constraint allows
| (PHP 8.5) and on current Laravel idioms. CI runs it with --dry-run, so code
| written the old way fails the build; `make rector` applies it.
*/
return RectorConfig::configure()
    ->withPaths([__DIR__.'/app', __DIR__.'/config', __DIR__.'/database', __DIR__.'/routes', __DIR__.'/tests'])
    ->withPhpSets()
    ->withComposerBased(laravel: true)
    ->withSkip([
        // The jobs read $this->tries and $this->maxExceptions themselves; as attributes those properties vanish.
        TriesPropertyToTriesAttributeRector::class,
        MaxExceptionsPropertyToMaxExceptionsAttributeRector::class,
        BackoffPropertyToBackoffAttributeRector::class,
        UniqueForPropertyToUniqueForAttributeRector::class,
        // The Attribute-style accessor hides total_volume_kg from Larastan.
        MigrateToSimplifiedAttributeRector::class,
    ]);
