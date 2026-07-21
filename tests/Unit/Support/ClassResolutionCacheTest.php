<?php

declare(strict_types=1);

namespace JardisSupport\ClassVersion\Tests\Unit\Support;

use InvalidArgumentException;
use JardisSupport\ClassVersion\Support\ClassResolutionCache;
use PHPUnit\Framework\TestCase;
use stdClass;

class ClassResolutionCacheTest extends TestCase
{
    public function testRemembersFirstProducerResult(): void
    {
        $cache = new ClassResolutionCache();
        $calls = 0;
        $producer = function () use (&$calls) {
            $calls++;
            return 'resolved';
        };

        $first = $cache->remember('key', $producer);
        $second = $cache->remember('key', $producer);

        $this->assertSame('resolved', $first);
        $this->assertSame('resolved', $second);
        $this->assertSame(1, $calls);
    }

    public function testDistinctKeysRunProducerIndependently(): void
    {
        $cache = new ClassResolutionCache();
        $calls = 0;
        $producer = function () use (&$calls) {
            return ++$calls;
        };

        $a = $cache->remember('a', $producer);
        $b = $cache->remember('b', $producer);

        $this->assertSame(1, $a);
        $this->assertSame(2, $b);
    }

    public function testCachesNullResult(): void
    {
        $cache = new ClassResolutionCache();
        $calls = 0;
        $producer = function () use (&$calls) {
            $calls++;
            return null;
        };

        $cache->remember('key', $producer);
        $cache->remember('key', $producer);

        $this->assertSame(1, $calls, 'null must be cached as a hit, not treated as miss');
    }

    public function testCachesObjectResult(): void
    {
        $cache = new ClassResolutionCache();
        $proxy = new stdClass();

        $first = $cache->remember('key', fn () => $proxy);
        $second = $cache->remember('key', fn () => new stdClass());

        $this->assertSame($proxy, $first);
        $this->assertSame($proxy, $second, 'second producer must not run');
    }

    public function testRemembersAndRethrowsException(): void
    {
        $cache = new ClassResolutionCache();
        $exception = new InvalidArgumentException('not found');
        $calls = 0;

        $producer = function () use (&$calls, $exception) {
            $calls++;
            throw $exception;
        };

        try {
            $cache->remember('key', $producer);
            $this->fail('expected exception');
        } catch (InvalidArgumentException $e) {
            $this->assertSame($exception, $e);
        }

        try {
            $cache->remember('key', $producer);
            $this->fail('expected exception on second call');
        } catch (InvalidArgumentException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertSame(1, $calls, 'producer must run only once even on the error path');
    }

    public function testClearResetsBothHitsAndMisses(): void
    {
        $cache = new ClassResolutionCache();
        $calls = 0;

        $hitProducer = function () use (&$calls) {
            $calls++;
            return 'ok';
        };
        $missProducer = function () use (&$calls) {
            $calls++;
            throw new InvalidArgumentException('missing');
        };

        $cache->remember('hit', $hitProducer);
        try {
            $cache->remember('miss', $missProducer);
        } catch (InvalidArgumentException) {
            // expected
        }

        $cache->clear();

        $cache->remember('hit', $hitProducer);
        try {
            $cache->remember('miss', $missProducer);
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->assertSame(4, $calls, 'clear() must re-run both hit and miss producers');
    }
}
