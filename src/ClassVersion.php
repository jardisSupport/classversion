<?php

declare(strict_types=1);

namespace JardisSupport\ClassVersion;

use JardisSupport\ClassVersion\Reader\LoadClassFromProxy;
use JardisSupport\ClassVersion\Support\ClassResolutionCache;
use JardisSupport\Contract\ClassVersion\ClassVersionConfigInterface;
use JardisSupport\Contract\ClassVersion\ClassVersionInterface;

/**
 * Returns the classVersion of a given class version
 */
class ClassVersion implements ClassVersionInterface
{
    private ClassVersionConfigInterface $versionConfig;
    private ClassVersionInterface $classFinder;
    private ClassVersionInterface $proxyClassFinder;
    private ?ClassResolutionCache $cache;

    public function __construct(
        ClassVersionConfigInterface $versionConfig,
        ClassVersionInterface $classFinder,
        ?ClassVersionInterface $proxyClassFinder = null,
        ?ClassResolutionCache $cache = null,
    ) {
        $this->versionConfig = $versionConfig;
        $this->classFinder = $classFinder;
        $this->proxyClassFinder = $proxyClassFinder ?? new LoadClassFromProxy($versionConfig);
        $this->cache = $cache;
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @param ?string $version
     * @return mixed|T
     */
    public function __invoke(string $className, ?string $version = null): mixed
    {
        if ($this->cache === null) {
            return $this->resolve($className, $version);
        }

        return $this->cache->remember(
            $className . '|' . ($version ?? ''),
            fn (): mixed => $this->resolve($className, $version),
        );
    }

    protected function version(?string $version = null): string
    {
        return trim($this->versionConfig->version($version) ?? '');
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @return mixed|T
     */
    private function resolve(string $className, ?string $version): mixed
    {
        $instance = ($this->proxyClassFinder)($className, $version);
        if ($instance) {
            return $instance;
        }

        return ($this->classFinder)($className, $this->version($version));
    }
}
