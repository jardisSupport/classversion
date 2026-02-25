<?php

declare(strict_types=1);

namespace JardisSupport\ClassVersion\Reader;

use InvalidArgumentException;
use JardisPort\ClassVersion\ClassVersionConfigInterface;
use JardisPort\ClassVersion\ClassVersionInterface;

/**
 * Returns the classVersion of a given class from the subdirectory of the given class
 */
class LoadClassFromSubDirectory implements ClassVersionInterface
{
    private ?ClassVersionConfigInterface $versionConfig;

    public function __construct(?ClassVersionConfigInterface $versionConfig = null)
    {
        $this->versionConfig = $versionConfig;
    }

    /**
     * @template T
     * @param class-string<T> $className
     * @param ?string $version
     * @return mixed|T
     * @throws InvalidArgumentException
     */
    public function __invoke(string $className, ?string $version = null): mixed
    {
        $version = trim($version ?? '', " \t\n\r\0\x0B");

        $chain = $this->resolveChain($version);
        $triedClasses = [];

        foreach ($chain as $chainVersion) {
            $class = $this->injectVersion($className, $chainVersion);
            $triedClasses[] = $class;
            if (class_exists($class)) {
                return $class;
            }
        }

        if (class_exists($className)) {
            return $className;
        }

        $tried = empty($triedClasses) ? $className : implode('", "', $triedClasses);
        throw new InvalidArgumentException(sprintf(
            'Given class "%s" not found (also tried versioned "%s")',
            $className,
            $tried
        ));
    }

    protected function injectVersion(string $className, string $version): string
    {
        $pos = str_contains($className, '\\') ? strrpos($className, '\\') + 1 : 0;
        return substr($className, 0, $pos) . $version . '\\' . substr($className, $pos);
    }

    /** @return array<string> */
    private function resolveChain(string $version): array
    {
        if ($version === '') {
            return [];
        }

        if ($this->versionConfig !== null) {
            return $this->versionConfig->fallbackChain($version);
        }

        return [$version];
    }
}
