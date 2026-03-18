<?php

declare(strict_types=1);

namespace JardisSupport\ClassVersion\Tests\Unit\Reader;

use InvalidArgumentException;
use JardisSupport\ClassVersion\Data\ClassVersionConfig;
use JardisSupport\ClassVersion\Reader\LoadClassFromSubDirectory;
use JardisSupport\ClassVersion\Tests\Fixtures\v1\VersionClass as V1VersionClass;
use JardisSupport\ClassVersion\Tests\Fixtures\v2\VersionClass as V2VersionClass;
use JardisSupport\ClassVersion\Tests\Fixtures\VersionClass;
use PHPUnit\Framework\TestCase;

class LoadClassFromSubDirectoryTest extends TestCase
{
    private LoadClassFromSubDirectory $loader;

    protected function setUp(): void
    {
        $this->loader = new LoadClassFromSubDirectory();
    }

    public function testInvokeReturnsBaseClassNameWhenNoVersionIsProvided(): void
    {
        $className = VersionClass::class;

        $result = ($this->loader)($className);

        $this->assertSame($className, $result);
    }

    public function testInvokeReturnsBaseClassNameWhenVersionIsNull(): void
    {
        $className = VersionClass::class;

        $result = ($this->loader)($className, null);

        $this->assertSame($className, $result);
    }

    public function testInvokeReturnsBaseClassNameWhenVersionIsEmptyString(): void
    {
        $className = VersionClass::class;

        $result = ($this->loader)($className, '');

        $this->assertSame($className, $result);
    }

    public function testInvokeReturnsBaseClassNameWhenVersionIsWhitespace(): void
    {
        $className = VersionClass::class;

        $result = ($this->loader)($className, '   ');

        $this->assertSame($className, $result);
    }

    public function testInvokeReturnsVersionedClassWhenV1VersionExists(): void
    {
        $className = VersionClass::class;
        $version = 'v1';

        $result = ($this->loader)($className, $version);

        $this->assertSame(V1VersionClass::class, $result);
    }

    public function testInvokeReturnsVersionedClassWhenV2VersionExists(): void
    {
        $className = VersionClass::class;
        $version = 'v2';

        $result = ($this->loader)($className, $version);

        $this->assertSame(V2VersionClass::class, $result);
    }

    public function testInvokeTrimsVersionStringAndFindsClass(): void
    {
        $className = VersionClass::class;
        $version = '  v1  ';

        $result = ($this->loader)($className, $version);

        $this->assertSame(V1VersionClass::class, $result);
    }

    public function testInvokeFallsBackToBaseClassWhenVersionedClassDoesNotExist(): void
    {
        $className = VersionClass::class;
        $version = 'v99';

        $result = ($this->loader)($className, $version);

        $this->assertSame($className, $result);
    }

    public function testInvokeThrowsExceptionWhenClassDoesNotExist(): void
    {
        $className = 'NonExistent\\Class\\Name';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Given class "NonExistent\\Class\\Name" not found');

        ($this->loader)($className);
    }

    public function testInvokeThrowsExceptionWithVersionInformationWhenBothClassesDoNotExist(): void
    {
        $className = 'NonExistent\\Class\\Name';
        $version = 'v1';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Given class "NonExistent\\Class\\Name" not found (also tried versioned "NonExistent\\Class\\v1\\Name")'
        );

        ($this->loader)($className, $version);
    }

    public function testInvokeBuildsVersionedClassNameCorrectlyWithNamespace(): void
    {
        $className = VersionClass::class;
        $version = 'v1';

        $result = ($this->loader)($className, $version);

        $expectedClass = 'JardisSupport\\ClassVersion\\Tests\\Fixtures\\v1\\VersionClass';
        $this->assertSame($expectedClass, $result);
    }

    public function testInvokeCorrectlyInsertsVersionIntoNamespace(): void
    {
        // Test mit Basis-Klasse und v2
        $className = VersionClass::class;
        $version = 'v2';

        $result = ($this->loader)($className, $version);

        $this->assertSame(V2VersionClass::class, $result);
        $this->assertSame('JardisSupport\\ClassVersion\\Tests\\Fixtures\\v2\\VersionClass', $result);
    }

    public function testInvokeHandlesClassWithoutNamespace(): void
    {
        $className = 'SimpleClassName';
        $version = 'v1';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('also tried versioned "v1\\SimpleClassName"');

        ($this->loader)($className, $version);
    }

    public function testInvokeWithMultipleNamespaceLevels(): void
    {
        $className = 'Level1\\Level2\\Level3\\ClassName';
        $version = 'v5';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('also tried versioned "Level1\\Level2\\Level3\\v5\\ClassName"');

        ($this->loader)($className, $version);
    }

    public function testInvokePrioritizesVersionedClassOverBaseClass(): void
    {
        $className = VersionClass::class;
        $version = 'v1';

        $result = ($this->loader)($className, $version);

        $this->assertSame(V1VersionClass::class, $result);
        $this->assertNotSame(VersionClass::class, $result);
    }

    public function testFallbackChainFindsV2Directly(): void
    {
        $config = new ClassVersionConfig(
            ['v1' => ['version1'], 'v2' => ['version2']],
            ['v2' => ['v1']]
        );
        $loader = new LoadClassFromSubDirectory($config);

        $result = ($loader)(VersionClass::class, 'v2');

        $this->assertSame(V2VersionClass::class, $result);
    }

    public function testFallbackChainFallsBackToV2WhenV3NotFound(): void
    {
        $config = new ClassVersionConfig(
            ['v1' => ['version1'], 'v2' => ['version2'], 'v3' => ['version3']],
            ['v3' => ['v2']]
        );
        $loader = new LoadClassFromSubDirectory($config);

        $result = ($loader)(VersionClass::class, 'v3');

        $this->assertSame(V2VersionClass::class, $result);
    }

    public function testFallbackChainFallsBackToBaseClassWithoutChain(): void
    {
        $config = new ClassVersionConfig(
            ['v1' => ['version1'], 'v99' => ['version99']]
        );
        $loader = new LoadClassFromSubDirectory($config);

        $result = ($loader)(VersionClass::class, 'v99');

        $this->assertSame(VersionClass::class, $result);
    }

    public function testFallbackChainThrowsExceptionWithChainInfoWhenNothingExists(): void
    {
        $config = new ClassVersionConfig(
            ['v3' => ['version3']],
            ['v3' => ['v2']]
        );
        $loader = new LoadClassFromSubDirectory($config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('also tried versioned');

        ($loader)('NonExistent\\Foo\\Bar', 'v3');
    }
}
