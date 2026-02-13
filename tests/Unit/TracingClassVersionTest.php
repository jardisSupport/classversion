<?php

declare(strict_types=1);

namespace JardisSupport\ClassVersion\Tests\Unit;

use InvalidArgumentException;
use JardisSupport\ClassVersion\ClassVersion;
use JardisSupport\ClassVersion\Data\ClassVersionConfig;
use JardisSupport\ClassVersion\Reader\LoadClassFromProxy;
use JardisSupport\ClassVersion\Reader\LoadClassFromSubDirectory;
use JardisSupport\ClassVersion\Tests\Fixtures\VersionClass;
use JardisSupport\ClassVersion\Tests\Fixtures\v1\VersionClass as V1VersionClass;
use JardisSupport\ClassVersion\Tests\Fixtures\v2\VersionClass as V2VersionClass;
use JardisSupport\ClassVersion\TracingClassVersion;
use PHPUnit\Framework\TestCase;

final class TracingClassVersionTest extends TestCase
{
    public function testTracesClassStringResolution(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);
        $inner = new ClassVersion($config, new LoadClassFromSubDirectory());
        $tracing = new TracingClassVersion($inner);

        $result = $tracing(VersionClass::class, 'version1');

        $this->assertSame(V1VersionClass::class, $result);

        $trace = $tracing->getTrace();
        $this->assertCount(1, $trace);
        $this->assertSame(VersionClass::class, $trace[0]['requested']);
        $this->assertSame('version1', $trace[0]['version']);
        $this->assertSame(V1VersionClass::class, $trace[0]['resolved']);
        $this->assertSame('class-string', $trace[0]['type']);
    }

    public function testTracesProxyObjectResolution(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
        ]);
        $proxyFinder = new LoadClassFromProxy($config);
        $proxy = new V1VersionClass();
        $proxyFinder->addProxy(VersionClass::class, $proxy, 'version1');

        $inner = new ClassVersion($config, new LoadClassFromSubDirectory(), $proxyFinder);
        $tracing = new TracingClassVersion($inner);

        $result = $tracing(VersionClass::class, 'version1');

        $this->assertSame($proxy, $result);

        $trace = $tracing->getTrace();
        $this->assertCount(1, $trace);
        $this->assertSame('proxy', $trace[0]['type']);
        $this->assertSame($proxy, $trace[0]['resolved']);
    }

    public function testClearTraceRemovesAllEntries(): void
    {
        $config = new ClassVersionConfig(['v1' => ['version1']]);
        $inner = new ClassVersion($config, new LoadClassFromSubDirectory());
        $tracing = new TracingClassVersion($inner);

        $tracing(VersionClass::class, 'version1');
        $tracing(VersionClass::class);

        $this->assertCount(2, $tracing->getTrace());

        $tracing->clearTrace();

        $this->assertCount(0, $tracing->getTrace());
        $this->assertSame([], $tracing->getTrace());
    }

    public function testGetTracePreservesOrder(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);
        $inner = new ClassVersion($config, new LoadClassFromSubDirectory());
        $tracing = new TracingClassVersion($inner);

        $tracing(VersionClass::class, 'version1');
        $tracing(VersionClass::class, 'version2');
        $tracing(VersionClass::class);

        $trace = $tracing->getTrace();
        $this->assertCount(3, $trace);
        $this->assertSame('version1', $trace[0]['version']);
        $this->assertSame('version2', $trace[1]['version']);
        $this->assertNull($trace[2]['version']);
    }

    public function testResultIsPassedThroughUnchanged(): void
    {
        $config = new ClassVersionConfig(['v1' => ['version1']]);
        $inner = new ClassVersion($config, new LoadClassFromSubDirectory());
        $tracing = new TracingClassVersion($inner);

        $result = $tracing(VersionClass::class, 'version1');

        $this->assertSame(V1VersionClass::class, $result);
    }

    public function testExceptionPropagatesWithNoTraceEntry(): void
    {
        $config = new ClassVersionConfig([]);
        $inner = new ClassVersion($config, new LoadClassFromSubDirectory());
        $tracing = new TracingClassVersion($inner);

        $this->expectException(InvalidArgumentException::class);

        try {
            $tracing('NonExistent\\Class\\Name', 'v1');
        } catch (InvalidArgumentException $e) {
            $this->assertCount(0, $tracing->getTrace());
            throw $e;
        }
    }
}
