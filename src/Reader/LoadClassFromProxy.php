<?php

declare(strict_types=1);

namespace JardisSupport\ClassVersion\Reader;

use JardisPort\ClassVersion\ClassVersionConfigInterface;
use JardisPort\ClassVersion\ClassVersionInterface;

/**
 * Returns a proxy object for a given classname (string) and version
 */
class LoadClassFromProxy implements ClassVersionInterface
{
    /** @var array<string, array<string, object>> */
    private array $cachedProxy = [];
    private ?ClassVersionConfigInterface $versionConfig;

    public function __construct(?ClassVersionConfigInterface $versionConfig = null)
    {
        $this->versionConfig = $versionConfig;
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @param ?string $version
     * @return T|mixed
     */
    public function __invoke(string $className, ?string $version = null): mixed
    {
        $className = trim($className);
        $version = $this->version($version);

        if (!isset($this->cachedProxy[$version])) {
            return null;
        }

        if (!isset($this->cachedProxy[$version][$className])) {
            return null;
        }

        return $this->cachedProxy[$version][$className];
    }

    public function addProxy(string $className, object $proxy, ?string $version = null): self
    {
        $version = $this->version($version);
        $className = trim($className);
        $this->cachedProxy[$version][$className] = $proxy;

        return $this;
    }

    public function removeProxy(string $className, ?string $version = null): self
    {
        $version = $this->version($version);
        $className = trim($className);

        if (isset($this->cachedProxy[$version][$className])) {
            unset($this->cachedProxy[$version][$className]);
            if (empty($this->cachedProxy[$version])) {
                unset($this->cachedProxy[$version]);
            }
        }

        return $this;
    }

    protected function version(?string $version = null): string
    {
        return $this->versionConfig
            ? $this->versionConfig->version($version) ?? ''
            : trim($version ?? '', " \t\n\r\0\x0B");
    }
}
