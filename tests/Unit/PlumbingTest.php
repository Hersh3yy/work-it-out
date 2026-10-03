<?php

declare(strict_types=1);

test('unit tests have the container', function (): void {
    expect(config('app.name'))->toBe('Feetness')
        ->and(app()->environment())->toBe('testing');
});
