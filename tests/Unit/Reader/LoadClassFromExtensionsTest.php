<?php

declare(strict_types=1);

namespace JardisSupport\ClassVersion\Tests\Unit\Reader;

use FakeDomain\Bc\Agg\Command\Handler\BaselineHandler;
use FakeDomain\Bc\Agg\Command\Handler\GeneratorOnlyHandler;
use FakeDomain\Bc\Agg\Command\Handler\VersionedHandler;
use FakeDomain\FlatClass;
use InvalidArgumentException;
use JardisSupport\ClassVersion\Data\ClassVersionConfig;
use JardisSupport\ClassVersion\Reader\LoadClassFromExtensions;
use PHPUnit\Framework\TestCase;

class LoadClassFromExtensionsTest extends TestCase
{
    private LoadClassFromExtensions $loader;

    protected function setUp(): void
    {
        $this->loader = new LoadClassFromExtensions(depth: 3, segmentNames: ['Extensions']);
    }

    // =========================================================================
    // Generator base (no overrides)
    // =========================================================================

    public function testReturnsGeneratorBaseWhenNoExtensionsExist(): void
    {
        $result = ($this->loader)(GeneratorOnlyHandler::class);

        $this->assertSame(GeneratorOnlyHandler::class, $result);
    }

    public function testReturnsGeneratorBaseWhenVersionIsNull(): void
    {
        $result = ($this->loader)(GeneratorOnlyHandler::class, null);

        $this->assertSame(GeneratorOnlyHandler::class, $result);
    }

    public function testReturnsGeneratorBaseWhenVersionIsEmpty(): void
    {
        $result = ($this->loader)(GeneratorOnlyHandler::class, '');

        $this->assertSame(GeneratorOnlyHandler::class, $result);
    }

    public function testTrimsWhitespaceVersion(): void
    {
        $result = ($this->loader)(GeneratorOnlyHandler::class, '   ');

        $this->assertSame(GeneratorOnlyHandler::class, $result);
    }

    // =========================================================================
    // Baseline (versionless Extensions override)
    // =========================================================================

    public function testReturnsBaselineExtensionWhenNoVersionGiven(): void
    {
        $result = ($this->loader)(BaselineHandler::class);

        $this->assertSame('FakeDomain\\Bc\\Agg\\Extensions\\Command\\Handler\\BaselineHandler', $result);
    }

    public function testBaselineWinsOverGeneratorBase(): void
    {
        $result = ($this->loader)(BaselineHandler::class);

        $this->assertNotSame(BaselineHandler::class, $result);
    }

    public function testBaselineIsResolvedEvenWhenVersionIsEmpty(): void
    {
        $result = ($this->loader)(BaselineHandler::class, '');

        $this->assertSame('FakeDomain\\Bc\\Agg\\Extensions\\Command\\Handler\\BaselineHandler', $result);
    }

    // =========================================================================
    // Versioned override
    // =========================================================================

    public function testVersionedOverrideWinsOverBaselineWhenVersionActive(): void
    {
        $result = ($this->loader)(VersionedHandler::class, 'v1');

        $this->assertSame('FakeDomain\\Bc\\Agg\\Extensions\\v1\\Command\\Handler\\VersionedHandler', $result);
    }

    public function testV2OverrideResolvesIndependentlyOfV1(): void
    {
        $result = ($this->loader)(VersionedHandler::class, 'v2');

        $this->assertSame('FakeDomain\\Bc\\Agg\\Extensions\\v2\\Command\\Handler\\VersionedHandler', $result);
    }

    public function testVersionedFallsBackToBaselineWhenVersionNotPresent(): void
    {
        $result = ($this->loader)(BaselineHandler::class, 'v1');

        $this->assertSame(
            'FakeDomain\\Bc\\Agg\\Extensions\\Command\\Handler\\BaselineHandler',
            $result
        );
    }

    public function testVersionedFallsBackToGeneratorWhenNoExtensionsExist(): void
    {
        $result = ($this->loader)(GeneratorOnlyHandler::class, 'v1');

        $this->assertSame(GeneratorOnlyHandler::class, $result);
    }

    // =========================================================================
    // Fallback chain via ClassVersionConfig
    // =========================================================================

    public function testFallbackChainResolvesV2Directly(): void
    {
        $config = new ClassVersionConfig(
            ['v1' => ['version1'], 'v2' => ['version2']],
            ['v2' => ['v1']]
        );
        $loader = new LoadClassFromExtensions(depth: 3, segmentNames: ['Extensions'], versionConfig: $config);

        $result = $loader(VersionedHandler::class, 'v2');

        $this->assertSame('FakeDomain\\Bc\\Agg\\Extensions\\v2\\Command\\Handler\\VersionedHandler', $result);
    }

    public function testFallbackChainFallsBackFromV3ToV2(): void
    {
        $config = new ClassVersionConfig(
            ['v1' => ['version1'], 'v2' => ['version2'], 'v3' => ['version3']],
            ['v3' => ['v2', 'v1']]
        );
        $loader = new LoadClassFromExtensions(depth: 3, segmentNames: ['Extensions'], versionConfig: $config);

        $result = $loader(VersionedHandler::class, 'v3');

        $this->assertSame('FakeDomain\\Bc\\Agg\\Extensions\\v2\\Command\\Handler\\VersionedHandler', $result);
    }

    public function testFallbackChainFallsThroughToBaselineWhenNoVersionMatches(): void
    {
        $config = new ClassVersionConfig(
            ['v99' => ['version99']]
        );
        $loader = new LoadClassFromExtensions(depth: 3, segmentNames: ['Extensions'], versionConfig: $config);

        $result = $loader(BaselineHandler::class, 'v99');

        $this->assertSame(
            'FakeDomain\\Bc\\Agg\\Extensions\\Command\\Handler\\BaselineHandler',
            $result
        );
    }

    public function testFallbackChainFallsThroughToGeneratorWhenBaselineMissing(): void
    {
        $config = new ClassVersionConfig(
            ['v99' => ['version99']]
        );
        $loader = new LoadClassFromExtensions(depth: 3, segmentNames: ['Extensions'], versionConfig: $config);

        $result = $loader(GeneratorOnlyHandler::class, 'v99');

        $this->assertSame(GeneratorOnlyHandler::class, $result);
    }

    // =========================================================================
    // Edge cases: shallow namespaces
    // =========================================================================

    public function testSkipsExtensionsLookupForClassesWithFewerThanFourSegments(): void
    {
        $result = ($this->loader)(FlatClass::class, 'v1');

        $this->assertSame(FlatClass::class, $result);
    }

    public function testSkipsExtensionsLookupWithoutVersionForFlatClass(): void
    {
        $result = ($this->loader)(FlatClass::class);

        $this->assertSame(FlatClass::class, $result);
    }

    // =========================================================================
    // Generic parametrisation: different depth + different segment name
    // =========================================================================

    public function testCustomDepthAndSegmentName(): void
    {
        $loader = new LoadClassFromExtensions(depth: 2, segmentNames: ['Overrides']);

        $result = $loader('FakeDomain\\Alt\\Command\\Handler\\FooHandler');

        $this->assertSame(
            'FakeDomain\\Alt\\Overrides\\Command\\Handler\\FooHandler',
            $result
        );
    }

    public function testCustomDepthSkipsInjectionForTooShallowClass(): void
    {
        $loader = new LoadClassFromExtensions(depth: 5, segmentNames: ['Overrides']);

        $result = $loader('FakeDomain\\Alt\\Command\\Handler\\FooHandler');

        $this->assertSame('FakeDomain\\Alt\\Command\\Handler\\FooHandler', $result);
    }

    // =========================================================================
    // Error paths
    // =========================================================================

    public function testThrowsForNonExistentClassWithoutVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Given class "NonExistent\\Foo\\Bar\\Baz" not found');

        ($this->loader)('NonExistent\\Foo\\Bar\\Baz');
    }

    public function testThrowsForNonExistentClassWithVersionIncludingCandidates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'also tried extensions "NonExistent\\Foo\\Bar\\Extensions\\v1\\Baz"'
        );

        ($this->loader)('NonExistent\\Foo\\Bar\\Baz', 'v1');
    }

    public function testThrowsForShallowNonExistentClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Given class "NonExistent\\Shallow" not found');

        ($this->loader)('NonExistent\\Shallow', 'v1');
    }

    // =========================================================================
    // Multi-segment Lookup (A2 Versions-First)
    // =========================================================================

    public function testEmptySegmentBaselineWinsOverNamedSegmentBaseline(): void
    {
        $loader = new LoadClassFromExtensions(depth: 3, segmentNames: ['', 'Platform']);

        $result = $loader('FakeDomain\\Bc\\Agg\\Command\\Handler\\A2DevAndPlatformBaseline');

        $this->assertSame(
            'FakeDomain\\Bc\\Agg\\Command\\Handler\\A2DevAndPlatformBaseline',
            $result
        );
    }

    public function testNamedSegmentBaselineResolvesWhenEmptySegmentMissing(): void
    {
        $loader = new LoadClassFromExtensions(depth: 3, segmentNames: ['', 'Platform']);

        $result = $loader('FakeDomain\\Bc\\Agg\\Command\\Handler\\A2PlatformBaselineOnly');

        $this->assertSame(
            'FakeDomain\\Bc\\Agg\\Platform\\Command\\Handler\\A2PlatformBaselineOnly',
            $result
        );
    }

    public function testEmptySegmentVersionedResolves(): void
    {
        $loader = new LoadClassFromExtensions(depth: 3, segmentNames: ['', 'Platform']);

        $result = $loader('FakeDomain\\Bc\\Agg\\Command\\Handler\\A2DevV2Only', 'v2');

        $this->assertSame(
            'FakeDomain\\Bc\\Agg\\v2\\Command\\Handler\\A2DevV2Only',
            $result
        );
    }

    public function testNamedSegmentVersionedResolvesWhenEmptyVersionedMissing(): void
    {
        $loader = new LoadClassFromExtensions(depth: 3, segmentNames: ['', 'Platform']);

        $result = $loader('FakeDomain\\Bc\\Agg\\Command\\Handler\\A2PlatformV2Only', 'v2');

        $this->assertSame(
            'FakeDomain\\Bc\\Agg\\Platform\\v2\\Command\\Handler\\A2PlatformV2Only',
            $result
        );
    }

    /**
     * A2-Kernel-Assertion: Versioned wins over Baseline ACROSS segments.
     *
     * A1 (Layer-First) would resolve Dev-Baseline first; A2 (Versions-First)
     * resolves Platform v2 because the versioned chain is exhausted across
     * ALL segments before falling through to baselines.
     */
    public function testVersionsFirstAcrossLayers(): void
    {
        $loader = new LoadClassFromExtensions(depth: 3, segmentNames: ['', 'Platform']);

        $result = $loader('FakeDomain\\Bc\\Agg\\Command\\Handler\\A2DevBaselineAndPlatformV2', 'v2');

        $this->assertSame(
            'FakeDomain\\Bc\\Agg\\Platform\\v2\\Command\\Handler\\A2DevBaselineAndPlatformV2',
            $result
        );
    }

    public function testFallbackChainAcrossLayers(): void
    {
        $config = new ClassVersionConfig(
            ['v1' => ['version1'], 'v2' => ['version2'], 'v3' => ['version3']],
            ['v3' => ['v2', 'v1']]
        );
        $loader = new LoadClassFromExtensions(
            depth: 3,
            segmentNames: ['', 'Platform'],
            versionConfig: $config,
        );

        $result = $loader('FakeDomain\\Bc\\Agg\\Command\\Handler\\A2PlatformV2Only', 'v3');

        $this->assertSame(
            'FakeDomain\\Bc\\Agg\\Platform\\v2\\Command\\Handler\\A2PlatformV2Only',
            $result
        );
    }

    public function testThreeSegmentsResolveAcrossAllLayers(): void
    {
        $loader = new LoadClassFromExtensions(
            depth: 3,
            segmentNames: ['', 'Layer1', 'Layer2'],
        );

        $result = $loader('FakeDomain\\Bc\\Agg\\Command\\Handler\\A2MultiLayer');

        $this->assertSame(
            'FakeDomain\\Bc\\Agg\\Layer2\\Command\\Handler\\A2MultiLayer',
            $result
        );
    }

    public function testEmptySegmentsArrayFallsThroughToGeneratorBase(): void
    {
        $loader = new LoadClassFromExtensions(depth: 3, segmentNames: []);

        $result = $loader(BaselineHandler::class);

        $this->assertSame(BaselineHandler::class, $result);
    }

    public function testEmptySegmentsArrayThrowsForUnknownClass(): void
    {
        $loader = new LoadClassFromExtensions(depth: 3, segmentNames: []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Given class "NonExistent\\Foo\\Bar\\Baz" not found');

        $loader('NonExistent\\Foo\\Bar\\Baz', 'v1');
    }

    public function testSingleEmptySegmentTriesVersionedAtRoot(): void
    {
        $loader = new LoadClassFromExtensions(depth: 3, segmentNames: ['']);

        $result = $loader('FakeDomain\\Bc\\Agg\\Command\\Handler\\A2DevV2Only', 'v2');

        $this->assertSame(
            'FakeDomain\\Bc\\Agg\\v2\\Command\\Handler\\A2DevV2Only',
            $result
        );
    }

    public function testSingleEmptySegmentBaselineEqualsGeneratorBase(): void
    {
        $loader = new LoadClassFromExtensions(depth: 3, segmentNames: ['']);

        $result = $loader(GeneratorOnlyHandler::class);

        $this->assertSame(GeneratorOnlyHandler::class, $result);
    }

    public function testErrorMessageListsAllProbedSegments(): void
    {
        $loader = new LoadClassFromExtensions(depth: 3, segmentNames: ['', 'Platform']);

        try {
            $loader('NonExistent\\Foo\\Bar\\Baz', 'v2');
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString('NonExistent\\Foo\\Bar\\v2\\Baz', $message);
            $this->assertStringContainsString('NonExistent\\Foo\\Bar\\Platform\\v2\\Baz', $message);
            $this->assertStringContainsString('NonExistent\\Foo\\Bar\\Baz', $message);
            $this->assertStringContainsString('NonExistent\\Foo\\Bar\\Platform\\Baz', $message);
        }
    }

    // =========================================================================
    // Strict order semantics (gap 1)
    // =========================================================================

    /**
     * Two non-empty segments — first segment in the list wins when both
     * contain a hit. Proves that segmentNames order is priority order, not
     * just a "set of layers to consider".
     */
    public function testFirstSegmentWinsWhenBothExist(): void
    {
        $loader = new LoadClassFromExtensions(
            depth: 3,
            segmentNames: ['Extensions', 'Platform'],
        );

        $result = $loader('FakeDomain\\Bc\\Agg\\Command\\Handler\\A2OrderPriority');

        $this->assertSame(
            'FakeDomain\\Bc\\Agg\\Extensions\\Command\\Handler\\A2OrderPriority',
            $result
        );
    }

    /**
     * Reverse the order — now Platform must win. Same fixture, only the
     * segmentNames order changes.
     */
    public function testReversedOrderFlipsTheWinner(): void
    {
        $loader = new LoadClassFromExtensions(
            depth: 3,
            segmentNames: ['Platform', 'Extensions'],
        );

        $result = $loader('FakeDomain\\Bc\\Agg\\Command\\Handler\\A2OrderPriority');

        $this->assertSame(
            'FakeDomain\\Bc\\Agg\\Platform\\Command\\Handler\\A2OrderPriority',
            $result
        );
    }

    // =========================================================================
    // Full lookup matrix: fallback chain × multi-segment (gap 2)
    // =========================================================================

    /**
     * Fixture A2FullMatrix exists at four positions:
     *   - Bc\Agg\v1\…                  (dev v1)
     *   - Bc\Agg\Platform\v2\…         (platform v2)
     *   - Bc\Agg\…                     (dev baseline)
     *   - Bc\Agg\Platform\…            (platform baseline)
     *
     * Asking for 'v3' with chain v3→v2→v1 and segmentNames ['', 'Platform']
     * walks:
     *   1. Bc\Agg\v3\…                — miss
     *   2. Bc\Agg\Platform\v3\…       — miss
     *   3. Bc\Agg\v2\…                — miss
     *   4. Bc\Agg\Platform\v2\…       — HIT  ← versions-first wins over dev-v1
     *
     * Critical assertion: Platform v2 wins over Dev v1, which proves the
     * version chain is exhausted across ALL segments before moving down
     * the chain. A1 (Layer-First) would resolve dev-v1 here.
     */
    public function testVersionsFirstAcrossLayersInFullMatrix(): void
    {
        $config = new ClassVersionConfig(
            ['v1' => ['version1'], 'v2' => ['version2'], 'v3' => ['version3']],
            ['v3' => ['v2', 'v1']]
        );
        $loader = new LoadClassFromExtensions(
            depth: 3,
            segmentNames: ['', 'Platform'],
            versionConfig: $config,
        );

        $result = $loader('FakeDomain\\Bc\\Agg\\Command\\Handler\\A2FullMatrix', 'v3');

        $this->assertSame(
            'FakeDomain\\Bc\\Agg\\Platform\\v2\\Command\\Handler\\A2FullMatrix',
            $result
        );
    }

    /**
     * Same matrix, ask for 'v1' directly — must hit dev-v1 (highest priority
     * for v1: empty segment first), not Platform v2 or Platform baseline.
     */
    public function testV1HitsDevSegmentDirectlyInFullMatrix(): void
    {
        $loader = new LoadClassFromExtensions(
            depth: 3,
            segmentNames: ['', 'Platform'],
        );

        $result = $loader('FakeDomain\\Bc\\Agg\\Command\\Handler\\A2FullMatrix', 'v1');

        $this->assertSame(
            'FakeDomain\\Bc\\Agg\\v1\\Command\\Handler\\A2FullMatrix',
            $result
        );
    }

    /**
     * Same matrix, ask without version — dev-baseline wins (segment '' before
     * 'Platform' in baseline loop).
     */
    public function testBaselineHitsDevSegmentInFullMatrix(): void
    {
        $loader = new LoadClassFromExtensions(
            depth: 3,
            segmentNames: ['', 'Platform'],
        );

        $result = $loader('FakeDomain\\Bc\\Agg\\Command\\Handler\\A2FullMatrix');

        $this->assertSame(
            'FakeDomain\\Bc\\Agg\\Command\\Handler\\A2FullMatrix',
            $result
        );
    }

    // =========================================================================
    // Exact error-message snapshot (gap 3)
    // =========================================================================

    /**
     * Locks the exact format AND order of probed candidates in the failure
     * message — guards CI legibility and makes the lookup order observable
     * without instrumentation.
     */
    public function testErrorMessageExactSnapshotForMultiSegment(): void
    {
        $loader = new LoadClassFromExtensions(
            depth: 3,
            segmentNames: ['', 'Platform'],
        );

        $expected = 'Given class "NonExistent\\Foo\\Bar\\Baz" not found '
            . '(also tried extensions '
            . '"NonExistent\\Foo\\Bar\\v2\\Baz", '
            . '"NonExistent\\Foo\\Bar\\Platform\\v2\\Baz", '
            . '"NonExistent\\Foo\\Bar\\Baz", '
            . '"NonExistent\\Foo\\Bar\\Platform\\Baz")';

        try {
            $loader('NonExistent\\Foo\\Bar\\Baz', 'v2');
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertSame($expected, $e->getMessage());
        }
    }
}
