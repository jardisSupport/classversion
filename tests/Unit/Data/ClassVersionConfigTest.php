<?php

declare(strict_types=1);

namespace JardisSupport\ClassVersion\Tests\Unit\Data;

use InvalidArgumentException;
use JardisSupport\ClassVersion\Data\ClassVersionConfig;
use PHPUnit\Framework\TestCase;

final class ClassVersionConfigTest extends TestCase
{
    public function testVersionReturnsConfigKeyForKnownLabel(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['alpha', 'a1', ' ALPHA '],
            'v2' => ['beta'],
        ]);

        $this->assertEquals('v1', $config->version('alpha'));
        $this->assertEquals('v1', $config->version('a1'));
        $this->assertEquals('v1', $config->version('ALPHA'));
        $this->assertEquals('v2', $config->version('beta'));
    }

    public function testVersionReturnsNullForUnknownLabelOrNull(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['alpha'],
        ]);

        $this->assertEmpty($config->version(null));
        $this->assertEmpty($config->version(''));
        $this->assertSame('unknown', $config->version('unknown'));
        $this->assertEmpty($config->version('   '));
    }

    public function testConstructorNormalizesAndDeduplicatesLabels(): void
    {
        $config = new ClassVersionConfig([
            'v1' => [' alpha ', 'alpha', 'ALPHA', 'alpha ', '  ALPHA  '],
        ]);

        $this->assertEquals('v1', $config->version('alpha'));
        $this->assertEquals('v1', $config->version('ALPHA'));
        $this->assertEquals('v1', $config->version(' ALPHA '));
    }

    public function testInvalidTopLevelShapeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClassVersionConfig([
            123 => ['ok'],
        ]);
    }

    public function testInvalidLabelTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line */
        new ClassVersionConfig([
            'v1' => ['alpha', 5],
        ]);
    }

    public function testEmptyLabelThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClassVersionConfig([
            'v1' => ['alpha', '   '],
        ]);
    }

    public function testFallbackChainWithoutFallbacksReturnsSingleVersion(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['version1'],
            'v2' => ['version2'],
        ]);

        $this->assertSame(['v1'], $config->fallbackChain('version1'));
        $this->assertSame(['v2'], $config->fallbackChain('version2'));
    }

    public function testFallbackChainWithFallbacksReturnsFullChain(): void
    {
        $config = new ClassVersionConfig(
            ['v1' => ['version1'], 'v2' => ['version2']],
            ['v2' => ['v1']]
        );

        $this->assertSame(['v2', 'v1'], $config->fallbackChain('version2'));
    }

    public function testFallbackChainWithNullReturnsEmptyArray(): void
    {
        $config = new ClassVersionConfig(
            ['v1' => ['version1']],
            ['v1' => ['v0']]
        );

        $this->assertSame([], $config->fallbackChain(null));
    }

    public function testFallbackChainWithEmptyStringReturnsEmptyArray(): void
    {
        $config = new ClassVersionConfig(
            ['v1' => ['version1']],
            ['v1' => ['v0']]
        );

        $this->assertSame([], $config->fallbackChain(''));
    }

    public function testFallbackChainResolvesAliasBeforeLookup(): void
    {
        $config = new ClassVersionConfig(
            ['v2' => ['current', 'version2']],
            ['v2' => ['v1']]
        );

        $this->assertSame(['v2', 'v1'], $config->fallbackChain('current'));
    }

    public function testFallbackChainWithUnknownVersionReturnsSingleEntry(): void
    {
        $config = new ClassVersionConfig(
            ['v1' => ['version1']],
            ['v1' => ['v0']]
        );

        $this->assertSame(['unknown'], $config->fallbackChain('unknown'));
    }

    public function testInvalidFallbackKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line */
        new ClassVersionConfig([], [123 => ['v1']]);
    }

    public function testInvalidFallbackValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line */
        new ClassVersionConfig([], ['v2' => [123]]);
    }
}
