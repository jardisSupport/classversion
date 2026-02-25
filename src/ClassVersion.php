<?php

declare(strict_types=1);

namespace JardisSupport\ClassVersion;

use JardisSupport\ClassVersion\Reader\LoadClassFromProxy;
use JardisPort\ClassVersion\ClassVersionConfigInterface;
use JardisPort\ClassVersion\ClassVersionInterface;

/**
 * Returns the classVersion of a given class version
 */
class ClassVersion implements ClassVersionInterface
{
    private ClassVersionConfigInterface $versionConfig;
    private ClassVersionInterface $classFinder;
    private ClassVersionInterface $proxyClassFinder;

    public function __construct(
        ClassVersionConfigInterface $versionConfig,
        ClassVersionInterface $classFinder,
        ?ClassVersionInterface $proxyClassFinder = null,
    ) {
        $this->versionConfig = $versionConfig;
        $this->classFinder = $classFinder;
        $this->proxyClassFinder = $proxyClassFinder ?? new LoadClassFromProxy($versionConfig);
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @param ?string $version
     * @return mixed|T
     */
    public function __invoke(string $className, ?string $version = null): mixed
    {
        $instance = ($this->proxyClassFinder)($className, $version);
        if ($instance) {
            return $instance;
        }

        $version = $this->version($version);

        return ($this->classFinder)($className, $version);
    }

    protected function version(?string $version = null): string
    {
        return trim($this->versionConfig->version($version) ?? '');
    }
}
