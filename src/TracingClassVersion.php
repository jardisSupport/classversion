<?php

declare(strict_types=1);

namespace JardisSupport\ClassVersion;

use JardisPort\ClassVersion\ClassVersionInterface;

/**
 * Decorator that records every ClassVersion resolution for debugging
 */
class TracingClassVersion implements ClassVersionInterface
{
    private ClassVersionInterface $inner;

    /** @var list<array{requested: string, version: ?string, resolved: mixed, type: string}> */
    private array $trace = [];

    public function __construct(ClassVersionInterface $inner)
    {
        $this->inner = $inner;
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @param ?string $version
     * @return mixed|T
     */
    public function __invoke(string $className, ?string $version = null): mixed
    {
        $result = ($this->inner)($className, $version);

        $this->trace[] = [
            'requested' => $className,
            'version' => $version,
            'resolved' => $result,
            'type' => is_object($result) ? 'proxy' : 'class-string',
        ];

        return $result;
    }

    /** @return list<array{requested: string, version: ?string, resolved: mixed, type: string}> */
    public function getTrace(): array
    {
        return $this->trace;
    }

    public function clearTrace(): void
    {
        $this->trace = [];
    }
}
