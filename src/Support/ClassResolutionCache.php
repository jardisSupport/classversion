<?php

declare(strict_types=1);

namespace JardisSupport\ClassVersion\Support;

use Throwable;

/**
 * Memoizes class-resolution results and exceptions.
 *
 * This is a helper, not a replacement for ClassVersion. It has no invoke
 * semantics and does not implement ClassVersionInterface — it just stores
 * (key → result) and (key → exception) pairs and offers a remember() helper
 * so callers can wrap a producer in a single call.
 *
 * Purpose: eliminate repeated `class_exists()` + `stat()` syscalls for
 * negative lookups (Extensions overrides that do not exist), which is the
 * common case in generated code.
 */
class ClassResolutionCache
{
    /** @var array<string, mixed> */
    private array $hits = [];

    /** @var array<string, Throwable> */
    private array $misses = [];

    /**
     * Returns the cached result for $key, or runs $producer once and caches
     * its return value (or thrown exception) under $key.
     *
     * Subsequent calls with the same key skip the producer entirely:
     * hits return the cached value, misses re-throw the cached exception.
     *
     * @template T
     * @param callable(): T $producer
     * @return T
     */
    public function remember(string $key, callable $producer): mixed
    {
        if (array_key_exists($key, $this->hits)) {
            return $this->hits[$key];
        }

        if (isset($this->misses[$key])) {
            throw $this->misses[$key];
        }

        try {
            $result = $producer();
            $this->hits[$key] = $result;
            return $result;
        } catch (Throwable $e) {
            $this->misses[$key] = $e;
            throw $e;
        }
    }

    public function clear(): void
    {
        $this->hits = [];
        $this->misses = [];
    }
}
