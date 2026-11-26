<?php

declare(strict_types=1);

namespace JardisSupport\ClassVersion\Tests\Unit\Reader;

use JardisSupport\ClassVersion\Data\ClassVersionConfig;
use JardisSupport\ClassVersion\Reader\LoadClassFromProxy;
use PHPUnit\Framework\TestCase;

final class LoadClassFromProxyTest extends TestCase
{
    public function testReturnsNullIfNotCached(): void
    {
        $loader = new LoadClassFromProxy();

        $this->assertNull($loader(\stdClass::class));
        $this->assertNull($loader(\stdClass::class, null));
        $this->assertNull($loader(\stdClass::class, ''));
        $this->assertNull($loader(\stdClass::class, 'v1'));
    }

    public function testAddAndGetWithoutVersion(): void
    {
        $loader = new LoadClassFromProxy();

        $proxy = new \stdClass();
        $loader->addProxy(\stdClass::class, $proxy);

        $this->assertSame($proxy, $loader(\stdClass::class));
        $this->assertSame($proxy, $loader(\stdClass::class, null));
        $this->assertSame($proxy, $loader(\stdClass::class, ''));
        $this->assertNull($loader(\ArrayObject::class));
    }

    public function testAddAndGetWithVersion(): void
    {
        $loader = new LoadClassFromProxy();

        $proxyV1 = (object)['v' => 1];
        $proxyV2 = (object)['v' => 2];

        $loader->addProxy(\stdClass::class, $proxyV1, 'v1');
        $loader->addProxy(\stdClass::class, $proxyV2, 'v2');

        $this->assertSame($proxyV1, $loader(\stdClass::class, 'v1'));
        $this->assertSame($proxyV2, $loader(\stdClass::class, 'v2'));
        $this->assertNull($loader(\stdClass::class, 'v3'));
    }

    public function testTrimsClassNameAndVersionOnAddAndGet(): void
    {
        $loader = new LoadClassFromProxy();

        $proxy = new \stdClass();
        $loader->addProxy('  '.\stdClass::class.'  ', $proxy, '  v1  ');

        $this->assertSame($proxy, $loader(\stdClass::class, 'v1'));
        $this->assertSame($proxy, $loader('  '.\stdClass::class.'  ', '  v1  '));
    }

    public function testRemoveProxyRemovesOnlyTargetAndCleansEmptyVersionBucket(): void
    {
        $loader = new LoadClassFromProxy();

        $a = (object)['a' => true];
        $b = (object)['b' => true];
        $c = (object)['c' => true];

        // v1 has two entries, v2 has one
        $loader->addProxy('A', $a, 'v1');
        $loader->addProxy('B', $b, 'v1');
        $loader->addProxy('C', $c, 'v2');

        // remove one from v1, bucket should remain
        $loader->removeProxy('A', 'v1');
        $this->assertNull($loader('A', 'v1'));
        $this->assertSame($b, $loader('B', 'v1'));

        // remove last from v1, bucket should be cleaned
        $loader->removeProxy('B', 'v1');
        $this->assertNull($loader('B', 'v1'));

        // v2 still present
        $this->assertSame($c, $loader('C', 'v2'));

        // remove v2 last entry, bucket should be cleaned
        $loader->removeProxy('C', 'v2');
        $this->assertNull($loader('C', 'v2'));
    }

    public function testRemoveProxyWithNullOrEmptyVersionTargetsDefaultBucket(): void
    {
        $loader = new LoadClassFromProxy();

        $proxy = (object)[];
        $loader->addProxy('X', $proxy, null);

        $this->assertSame($proxy, $loader('X'));
        $loader->removeProxy('X', '');
        $this->assertNull($loader('X'));
    }

    public function testFluentInterfaceOnAddAndRemove(): void
    {
        $loader = new LoadClassFromProxy();

        $resultAdd = $loader->addProxy('X', (object)[], 'v1');
        $resultRemove = $loader->removeProxy('X', 'v1');

        $this->assertSame($loader, $resultAdd);
        $this->assertSame($loader, $resultRemove);
    }

    public function testUsesVersionConfigToResolveVersionAliases(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['alpha', 'version1', 'a1'],
            'v2' => ['beta', 'version2'],
        ]);

        $loader = new LoadClassFromProxy($config);

        $proxyV1 = (object)['version' => 1];
        $proxyV2 = (object)['version' => 2];

        $loader->addProxy(\stdClass::class, $proxyV1, 'version1');
        $loader->addProxy(\stdClass::class, $proxyV2, 'version2');

        $this->assertSame($proxyV1, $loader(\stdClass::class, 'alpha'));
        $this->assertSame($proxyV1, $loader(\stdClass::class, 'version1'));
        $this->assertSame($proxyV1, $loader(\stdClass::class, 'a1'));
        $this->assertSame($proxyV2, $loader(\stdClass::class, 'beta'));
        $this->assertSame($proxyV2, $loader(\stdClass::class, 'version2'));
    }

    public function testAddWithAliasResolvesToCorrectKey(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['alpha', 'version1'],
            'v2' => ['beta', 'version2'],
        ]);

        $loader = new LoadClassFromProxy($config);

        $proxy = (object)['test' => true];

        $loader->addProxy(\stdClass::class, $proxy, 'alpha');

        $this->assertSame($proxy, $loader(\stdClass::class, 'alpha'));
        $this->assertSame($proxy, $loader(\stdClass::class, 'version1'));
        $this->assertEmpty( $loader(\stdClass::class, 'beta'));
    }

    public function testRemoveWithAliasResolvesToCorrectKey(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['alpha', 'version1'],
        ]);

        $loader = new LoadClassFromProxy($config);

        $proxy = (object)['test' => true];
        $loader->addProxy(\stdClass::class, $proxy, 'alpha');

        $this->assertSame($proxy, $loader(\stdClass::class, 'alpha'));

        // Remove using alias
        $loader->removeProxy(\stdClass::class, 'alpha');

        // Should be removed for all aliases
        $this->assertNull($loader(\stdClass::class, 'alpha'));
        $this->assertNull($loader(\stdClass::class, 'version1'));
    }

    public function testVersionConfigWithTrimmingOfAliases(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['  alpha  ', 'version1'],
        ]);

        $loader = new LoadClassFromProxy($config);

        $proxy = (object)['test' => true];
        $loader->addProxy(\stdClass::class, $proxy, '  alpha  ');

        $this->assertSame($proxy, $loader(\stdClass::class, 'alpha'));
        $this->assertSame($proxy, $loader(\stdClass::class, '  version1  '));
    }

    public function testUnknownAliasWithConfigReturnsNull(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['alpha'],
        ]);

        $loader = new LoadClassFromProxy($config);

        $proxy = (object)['test' => true];
        $loader->addProxy(\stdClass::class, $proxy, 'beta');

        $this->assertNull($loader(\stdClass::class, 'unknown'));
    }

    public function testConfigIsOptionalAndDefaultsToDirectVersionHandling(): void
    {
        $loaderWithoutConfig = new LoadClassFromProxy();
        $loaderWithConfig = new LoadClassFromProxy(new ClassVersionConfig([]));

        $proxy1 = (object)['a' => 1];
        $proxy2 = (object)['b' => 2];

        $loaderWithoutConfig->addProxy(\stdClass::class, $proxy1, 'v1');
        $loaderWithConfig->addProxy(\stdClass::class, $proxy2, 'v1');

        $this->assertSame($proxy1, $loaderWithoutConfig(\stdClass::class, 'v1'));
        $this->assertSame($proxy2, $loaderWithConfig(\stdClass::class, 'v1'));
    }

    public function testMultipleProxiesWithDifferentResolvedVersions(): void
    {
        $config = new ClassVersionConfig([
            'v1' => ['alpha', 'a1'],
            'v2' => ['beta', 'b1'],
            'v3' => ['gamma'],
        ]);

        $loader = new LoadClassFromProxy($config);

        $p1 = (object)['n' => 1];
        $p2 = (object)['n' => 2];
        $p3 = (object)['n' => 3];

        $loader->addProxy('A', $p1, 'alpha');
        $loader->addProxy('B', $p2, 'beta');
        $loader->addProxy('C', $p3, 'gamma');

        $this->assertSame($p1, $loader('A', 'a1'));
        $this->assertSame($p2, $loader('B', 'b1'));
        $this->assertSame($p3, $loader('C', 'gamma'));
    }

    /**
     * Contract A11-V1: a stand-in registered without version answers a requested version
     * that has no stand-in of its own (no config).
     */
    public function testVersionlessProxyAnswersRequestedVersionWithoutConfig(): void
    {
        $loader = new LoadClassFromProxy();

        $proxy = (object)['versionless' => true];
        $loader->addProxy(\stdClass::class, $proxy);

        $this->assertSame($proxy, $loader(\stdClass::class, 'v2'));
    }

    /**
     * Contract A11-V1: same with an alias config, requested through an alias.
     */
    public function testVersionlessProxyAnswersRequestedAliasWithConfig(): void
    {
        $config = new ClassVersionConfig([
            'v2' => ['beta', 'version2'],
        ]);
        $loader = new LoadClassFromProxy($config);

        $proxy = (object)['versionless' => true];
        $loader->addProxy(\stdClass::class, $proxy);

        $this->assertSame($proxy, $loader(\stdClass::class, 'beta'));
        $this->assertSame($proxy, $loader(\stdClass::class, 'v2'));
    }

    /**
     * Contract A11-V2: a stand-in under the requested version overrides the versionless one.
     */
    public function testVersionedProxyOverridesVersionlessProxy(): void
    {
        $loader = new LoadClassFromProxy();

        $versionless = (object)['versionless' => true];
        $versioned = (object)['v' => 2];
        $loader->addProxy(\stdClass::class, $versionless);
        $loader->addProxy(\stdClass::class, $versioned, 'v2');

        $this->assertSame($versioned, $loader(\stdClass::class, 'v2'));
        $this->assertSame($versionless, $loader(\stdClass::class));
    }

    /**
     * Contract A11-V1 (end): after removing the versionless stand-in the requested version
     * answers null again.
     */
    public function testRemovingVersionlessProxyEndsTheFallback(): void
    {
        $loader = new LoadClassFromProxy();

        $loader->addProxy(\stdClass::class, (object)[]);
        $this->assertNotNull($loader(\stdClass::class, 'v2'));

        $loader->removeProxy(\stdClass::class);

        $this->assertNull($loader(\stdClass::class, 'v2'));
    }

    /**
     * Contract A11 no chain: the fallback goes to the versionless bucket only,
     * never along the config fallback chain (v3 does not reach a stand-in under v2).
     */
    public function testProxyFallbackFollowsNoVersionChain(): void
    {
        $config = new ClassVersionConfig(
            ['v2' => ['version2'], 'v3' => ['version3']],
            ['v3' => ['v2']]
        );
        $loader = new LoadClassFromProxy($config);

        $loader->addProxy(\stdClass::class, (object)['v' => 2], 'v2');

        $this->assertNull($loader(\stdClass::class, 'v3'));
    }
}
