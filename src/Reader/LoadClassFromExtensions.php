<?php

declare(strict_types=1);

namespace JardisSupport\ClassVersion\Reader;

use InvalidArgumentException;
use JardisSupport\Contract\ClassVersion\ClassVersionConfigInterface;
use JardisSupport\Contract\ClassVersion\ClassVersionInterface;

/**
 * Resolves a class against one or more override-style subdirectories inserted
 * at a fixed namespace depth.
 *
 * The reader is layout-agnostic: the caller supplies `$depth` (how many namespace
 * segments from the left make up the "root" above the injected segment) and
 * `$segmentNames` (which segments to probe, e.g. `['Extensions']`,
 * `['', 'Platform']`, `['Overrides', 'Customizations']`). No implicit defaults
 * for the layout — the caller owns the convention.
 *
 * Lookup order for className = "{root}\{rest}":
 *   1. For each version in resolveChain($version) (highest priority first):
 *        For each segment in $segmentNames (in given order):
 *          {root}\{segment}\{version}\{rest}
 *   2. For each segment in $segmentNames (in given order, versionless baseline):
 *          {root}\{segment}\{rest}
 *   3. {className}                                   (generator base fallback)
 *
 * The empty string `''` is a legal segment value and means "no segment inserted":
 *   - segment='', version=''     -> {className}
 *   - segment='', version='v2'   -> {root}\v2\{rest}
 *
 * Including `''` in $segmentNames lifts the generator-base-fallback into the
 * versioned layer (versions can match without an intermediate segment); it does
 * NOT remove the final fallback to `$className` itself, which always remains
 * the last resort to preserve BC and shallow-class behaviour.
 *
 * Classes with fewer than $depth+1 namespace segments skip steps 1-2 —
 * there is no "rest" below the root to override.
 *
 * Performance: all string math, zero array allocations on the happy path
 * (see injectSegment()).
 */
class LoadClassFromExtensions implements ClassVersionInterface
{
    /** @var array<string> */
    private readonly array $segmentNames;

    /**
     * @param array<string> $segmentNames One or more segment names to probe in order.
     *                                    Empty string means "no segment inserted".
     */
    public function __construct(
        private readonly int $depth,
        array $segmentNames = ['Extensions'],
        private readonly ?ClassVersionConfigInterface $versionConfig = null,
    ) {
        $this->segmentNames = $segmentNames;
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
        $offset = $this->rootBoundary($className);
        $triedClasses = [];

        if ($offset !== null) {
            foreach ($this->resolveChain($version) as $chainVersion) {
                foreach ($this->segmentNames as $segmentName) {
                    $candidate = $this->injectSegment($className, $offset, $segmentName, $chainVersion);
                    $triedClasses[] = $candidate;
                    if (class_exists($candidate)) {
                        return $candidate;
                    }
                }
            }

            foreach ($this->segmentNames as $segmentName) {
                $baseline = $this->injectSegment($className, $offset, $segmentName, '');
                $triedClasses[] = $baseline;
                if (class_exists($baseline)) {
                    return $baseline;
                }
            }
        }

        if (class_exists($className)) {
            return $className;
        }

        if (empty($triedClasses)) {
            throw new InvalidArgumentException(sprintf(
                'Given class "%s" not found',
                $className
            ));
        }

        throw new InvalidArgumentException(sprintf(
            'Given class "%s" not found (also tried extensions "%s")',
            $className,
            implode('", "', $triedClasses)
        ));
    }

    /**
     * Returns the byte offset of the $depth-th backslash in $className,
     * or null if the class has fewer than $depth+1 segments.
     */
    private function rootBoundary(string $className): ?int
    {
        $offset = -1;
        for ($i = 0; $i < $this->depth; $i++) {
            $offset = strpos($className, '\\', $offset + 1);
            if ($offset === false) {
                return null;
            }
        }
        return $offset;
    }

    /**
     * Inserts a segment (and optionally a version) at $offset. Pure string math.
     *
     * - segment='Platform', version=''    -> {prefix}\Platform{rest}
     * - segment='Platform', version='v2'  -> {prefix}\Platform\v2{rest}
     * - segment='',         version=''    -> {prefix}{rest} (= unchanged className)
     * - segment='',         version='v2'  -> {prefix}\v2{rest}
     */
    private function injectSegment(string $className, int $offset, string $segmentName, string $version): string
    {
        $prefix = substr($className, 0, $offset);
        $rest = substr($className, $offset);

        if ($segmentName === '' && $version === '') {
            return $prefix . $rest;
        }
        if ($segmentName === '') {
            return $prefix . '\\' . $version . $rest;
        }
        if ($version === '') {
            return $prefix . '\\' . $segmentName . $rest;
        }
        return $prefix . '\\' . $segmentName . '\\' . $version . $rest;
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
