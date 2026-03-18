<?php

declare(strict_types=1);

namespace JardisSupport\ClassVersion\Tests\Unit;

use JardisSupport\ClassVersion\ClassVersion;
use JardisSupport\ClassVersion\Data\ClassVersionConfig;
use JardisSupport\ClassVersion\Reader\LoadClassFromProxy;
use JardisSupport\ClassVersion\Reader\LoadClassFromSubDirectory;
use JardisSupport\ClassVersion\Tests\Fixtures\VersionClass;
use JardisSupport\ClassVersion\Tests\Fixtures\v1\VersionClass as V1VersionClass;
use JardisSupport\ClassVersion\Tests\Fixtures\v2\VersionClass as V2VersionClass;
use PHPUnit\Framework\TestCase;

class ClassVersionTest extends TestCase
{
    public function testLoadDefaultVersionClass(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $classVersion = new ClassVersion($config, $classFinder);

        $result = $classVersion(VersionClass::class);

        $this->assertSame(VersionClass::class, $result);
    }

    public function testLoadVersion1Class(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $classVersion = new ClassVersion($config, $classFinder);

        $result = $classVersion(VersionClass::class, 'version1');

        $this->assertSame(V1VersionClass::class, $result);
        $this->assertSame('JardisSupport\ClassVersion\Tests\Fixtures\v1\VersionClass', $result);
    }

    public function testLoadVersion2Class(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $classVersion = new ClassVersion($config, $classFinder);

        $result = $classVersion(VersionClass::class, 'version2');

        $this->assertSame(V2VersionClass::class, $result);
        $this->assertSame('JardisSupport\ClassVersion\Tests\Fixtures\v2\VersionClass', $result);
    }

    public function testLoadClassWithDefaultVersion(): void
    {
        $config = new ClassVersionConfig(
            [
                'v1' => ['version1'],
                'v2' => ['version2'],
            ]
        );

        $classFinder = new LoadClassFromSubDirectory();
        $classVersion = new ClassVersion($config, $classFinder);

        $result = $classVersion(VersionClass::class);

        $this->assertSame(VersionClass::class, $result);
    }

    public function testLoadClassWithVersionTrimming(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $classVersion = new ClassVersion($config, $classFinder);

        $result = $classVersion(VersionClass::class, '  version1  ');

        $this->assertSame(V1VersionClass::class, $result);
    }

    public function testLoadClassWithCustomProxyFinder(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $proxyFinder = new LoadClassFromProxy();
        $classVersion = new ClassVersion($config, $classFinder, $proxyFinder);

        $result = $classVersion(VersionClass::class, 'version1');

        $this->assertSame(V1VersionClass::class, $result);
    }

    public function testLoadClassFallsBackToClassFinderWhenProxyReturnsNull(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $proxyFinder = new LoadClassFromProxy();
        $classVersion = new ClassVersion($config, $classFinder, $proxyFinder);

        $result = $classVersion(VersionClass::class, 'version2');

        $this->assertSame(V2VersionClass::class, $result);
    }

    public function testLoadNonExistentVersionReturnsDefaultClass(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $classVersion = new ClassVersion($config, $classFinder);

        $result = $classVersion(VersionClass::class, 'nonexistent');

        $this->assertSame(VersionClass::class, $result);
    }

    public function testMultipleVersionMappingsForSameSubDirectory(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1', 'v1', 'ver1'],
            'v2' => ['version2', 'v2', 'ver2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $classVersion = new ClassVersion($config, $classFinder);

        $result1 = $classVersion(VersionClass::class, 'version1');
        $result2 = $classVersion(VersionClass::class, 'v1');
        $result3 = $classVersion(VersionClass::class, 'ver1');

        $this->assertSame(V1VersionClass::class, $result1);
        $this->assertSame(V1VersionClass::class, $result2);
        $this->assertSame(V1VersionClass::class, $result3);
    }

    public function testSwitchBetweenDifferentVersions(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $classVersion = new ClassVersion($config, $classFinder);

        $result1 = $classVersion(VersionClass::class, 'version1');
        $this->assertSame(V1VersionClass::class, $result1);

        $result2 = $classVersion(VersionClass::class, 'version2');
        $this->assertSame(V2VersionClass::class, $result2);

        $result3 = $classVersion(VersionClass::class, 'version1');
        $this->assertSame(V1VersionClass::class, $result3);

        $result4 = $classVersion(VersionClass::class);
        $this->assertSame(VersionClass::class, $result4);
    }

    public function testLoadWithEmptyVersionString(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $classVersion = new ClassVersion($config, $classFinder);

        $result = $classVersion(VersionClass::class, '');

        $this->assertSame(VersionClass::class, $result);
    }

    public function testConstructorWithDefaultProxyClassFinder(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $classVersion = new ClassVersion($config, $classFinder);

        $result = $classVersion(VersionClass::class, 'version1');

        $this->assertSame(V1VersionClass::class, $result);
    }

    public function testVersionWithWhitespaceIsTrimmed(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $classVersion = new ClassVersion($config, $classFinder);

        $result = $classVersion(VersionClass::class, "\t version1 \n");

        $this->assertSame(V1VersionClass::class, $result);
    }

    public function testReturnsFullyQualifiedClassNames(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $classVersion = new ClassVersion($config, $classFinder);

        $result1 = $classVersion(VersionClass::class, 'version1');
        $result2 = $classVersion(VersionClass::class, 'version2');
        $result3 = $classVersion(VersionClass::class);

        $this->assertTrue(class_exists($result1));
        $this->assertTrue(class_exists($result2));
        $this->assertTrue(class_exists($result3));

        $this->assertSame('JardisSupport\ClassVersion\Tests\Fixtures\v1\VersionClass', $result1);
        $this->assertSame('JardisSupport\ClassVersion\Tests\Fixtures\v2\VersionClass', $result2);
        $this->assertSame('JardisSupport\ClassVersion\Tests\Fixtures\VersionClass', $result3);
    }

    public function testVersionConfigurationMappingWorks(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1', 'api-v1'],
            'v2' => ['version2', 'api-v2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $classVersion = new ClassVersion($config, $classFinder);

        $result1 = $classVersion(VersionClass::class, 'version1');
        $result2 = $classVersion(VersionClass::class, 'api-v1');

        $this->assertSame($result1, $result2);
        $this->assertSame(V1VersionClass::class, $result1);
    }

    public function testHandlesNullVersionFromConfig(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $classVersion = new ClassVersion($config, $classFinder);

        $result = $classVersion(VersionClass::class, null);

        $this->assertSame(VersionClass::class, $result);
    }

    public function testClassVersionWorksWithRealWorldScenario(): void
    {
        $config = new ClassVersionConfig(
            [
                'v1' => ['v1', 'version1', 'api-v1'],
                'v2' => ['v2', 'version2', 'api-v2'],
            ]
        );

        $classFinder = new LoadClassFromSubDirectory();
        $classVersion = new ClassVersion($config, $classFinder);

        $defaultVersion = $classVersion(VersionClass::class);
        $this->assertSame(VersionClass::class, $defaultVersion);

        $legacyVersion = $classVersion(VersionClass::class, 'api-v1');
        $this->assertSame(V1VersionClass::class, $legacyVersion);

        $currentVersion = $classVersion(VersionClass::class, 'version2');
        $this->assertSame(V2VersionClass::class, $currentVersion);
    }

    public function testProxyFinderReturnsInstanceDirectly(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $proxyFinder = new LoadClassFromProxy($config);

        $proxy = new V1VersionClass();
        $proxyFinder->addProxy(VersionClass::class, $proxy, 'version1');

        $classVersion = new ClassVersion($config, $classFinder, $proxyFinder);

        $result = $classVersion(VersionClass::class, 'version1');

        $this->assertSame($proxy, $result);
    }

    public function testProxyFinderWithMultipleProxies(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);

        $classFinder = new LoadClassFromSubDirectory();
        $proxyFinder = new LoadClassFromProxy($config);

        $proxyV1 = new V1VersionClass();
        $proxyV2 = new V2VersionClass();
        $proxyFinder->addProxy(VersionClass::class, $proxyV1, 'v1');
        $proxyFinder->addProxy(VersionClass::class, $proxyV2, 'v2');

        $classVersion = new ClassVersion($config, $classFinder, $proxyFinder);

        $result1 = $classVersion(VersionClass::class, 'version1');
        $result2 = $classVersion(VersionClass::class, 'version2');

        $this->assertSame($proxyV1, $result1);
        $this->assertSame($proxyV2, $result2);
    }

    public function testFallbackChainEndToEnd(): void
    {
        $config = new ClassVersionConfig(
            [
                'v1' => ['version1'],
                'v2' => ['version2'],
                'v3' => ['version3'],
            ],
            ['v3' => ['v2', 'v1']]
        );

        $classFinder = new LoadClassFromSubDirectory($config);
        $classVersion = new ClassVersion($config, $classFinder);

        $result = $classVersion(VersionClass::class, 'version3');

        $this->assertSame(V2VersionClass::class, $result);
    }

    public function testProxyTakesPrecedenceOverFallbackChain(): void
    {
        $config = new ClassVersionConfig(
            [
                'v1' => ['version1'],
                'v2' => ['version2'],
                'v3' => ['version3'],
            ],
            ['v3' => ['v2', 'v1']]
        );

        $classFinder = new LoadClassFromSubDirectory($config);
        $proxyFinder = new LoadClassFromProxy($config);

        $proxy = new V1VersionClass();
        $proxyFinder->addProxy(VersionClass::class, $proxy, 'version3');

        $classVersion = new ClassVersion($config, $classFinder, $proxyFinder);

        $result = $classVersion(VersionClass::class, 'version3');

        $this->assertSame($proxy, $result);
    }

    public function testFallbackChainFallsThroughToBaseClass(): void
    {
        $config = new ClassVersionConfig(
            [
                'v1' => ['version1'],
                'v2' => ['version2'],
                'v98' => ['version98'],
                'v99' => ['version99'],
            ],
            ['v99' => ['v98']]
        );

        $classFinder = new LoadClassFromSubDirectory($config);
        $classVersion = new ClassVersion($config, $classFinder);

        $result = $classVersion(VersionClass::class, 'version99');

        $this->assertSame(VersionClass::class, $result);
    }
}
