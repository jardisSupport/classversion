# Jardis ClassVersion

![Build Status](https://github.com/jardisSupport/classversion/actions/workflows/ci.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)
[![Coverage](https://img.shields.io/badge/Coverage-96.69%25-brightgreen.svg)](https://github.com/jardisSupport/classversion)

> Part of **[Jardis](https://jardis.io)** — the Domain-Driven Design platform for PHP. You model your domain; Jardis generates the production-ready hexagonal code (DTOs, Command/Query handlers, repositories, persistence). This package is part of the open-source foundation that generated code runs on.

Runtime class versioning for PHP via namespace injection — the mechanism that lets Jardis-generated hexagonal code grow through versions without breaking call sites. Load different implementations of the same class by version label, configure fallback chains so a missing version silently degrades to the previous one, register proxy instances for hot-swapping at test or runtime, and deploy new logic without touching existing code.

---

## Features

- **SubDirectory Resolution** — injects a version label into the namespace to locate versioned class implementations
- **Extensions Resolution** — inserts a fixed `Extensions/` segment at a configurable namespace depth to pick up baseline and versioned overrides side by side
- **Proxy Registry** — pre-register object instances via `LoadClassFromProxy` that are returned directly, bypassing class loading
- **Resolution Cache** — optional `ClassResolutionCache` memoizes hits **and** misses, eliminating repeated `class_exists()` / `stat()` syscalls on hot paths
- **Fallback Chains** — define ordered fallback sequences in `ClassVersionConfig` so resolution degrades gracefully across versions
- **Version Groups + Aliases** — map multiple labels to one canonical version key
- **Tracing Decorator** — wrap any resolver in `TracingClassVersion` to record every resolution for debugging
- **Zero-coupling** — works with any PSR-4 autoloader, no framework dependency required

---

## Installation

```bash
composer require jardissupport/classversion
```

## Quick Start

```php
use JardisSupport\ClassVersion\Data\ClassVersionConfig;
use JardisSupport\ClassVersion\Reader\LoadClassFromSubDirectory;
use JardisSupport\ClassVersion\ClassVersion;

// Map version labels to canonical subdirectory names
$config = new ClassVersionConfig(
    version: ['V2' => ['v2', '2.0'], 'V1' => ['v1', '1.0']],
);

$resolver = new ClassVersion(
    $config,
    new LoadClassFromSubDirectory($config),
);

// Resolves App\Service\V2\Calculator (namespace injection)
$className = $resolver(App\Service\Calculator::class, 'v2');

$instance = new $className();
```

## Advanced Usage

```php
use JardisSupport\ClassVersion\Data\ClassVersionConfig;
use JardisSupport\ClassVersion\Reader\LoadClassFromSubDirectory;
use JardisSupport\ClassVersion\Reader\LoadClassFromProxy;
use JardisSupport\ClassVersion\ClassVersion;
use JardisSupport\ClassVersion\Support\ClassResolutionCache;
use JardisSupport\ClassVersion\Support\TracingClassVersion;

// Fallback chain: if V2 namespace is missing, try V1 before the base class
$config = new ClassVersionConfig(
    version: ['V2' => ['v2', '2.0'], 'V1' => ['v1', '1.0']],
    fallbacks: ['V2' => ['V1']],
);

// Proxy registry: return a pre-built instance for a specific class + version
$proxy = new LoadClassFromProxy($config);
$proxy->addProxy(App\Service\Calculator::class, new MyTestCalculator(), 'v2');

// Optional: resolution cache memoizes hits and misses — same (class, version)
// key never hits the autoloader twice.
$resolver = new ClassVersion(
    $config,
    new LoadClassFromSubDirectory($config),
    $proxy,
    cache: new ClassResolutionCache(),
);

// Wrap with tracing decorator to record all resolutions
$tracing = new TracingClassVersion($resolver);

// Returns the pre-registered proxy instance directly
$result = $tracing(App\Service\Calculator::class, 'v2');

// Resolves App\Service\V2\Formatter — falls back to V1 if the V2 namespace is absent
$className = $tracing(App\Service\Formatter::class, 'v2');
$instance = new $className();

// Inspect the resolution log
foreach ($tracing->getTrace() as $entry) {
    echo $entry['requested'] . ' [' . ($entry['version'] ?? 'default') . ']'
        . ' → ' . $entry['type'] . PHP_EOL;
}

$tracing->clearTrace();
```

## Extensions Resolution

`LoadClassFromExtensions` is a second, parametrised resolver for projects that
keep developer-owned overrides in a dedicated directory. Configure two pieces:
`depth` (how many namespace segments from the left make up the "root") and
`segmentNames` (one or more directory names to probe in order, e.g.
`['Extensions']`, `['', 'Platform']`, `['Overrides', 'Customizations']`). The
empty string `''` is a legal segment value meaning "no subdir inserted" — use
it to keep the override layer at the aggregate root.

```php
use JardisSupport\ClassVersion\Reader\LoadClassFromExtensions;

$config = new ClassVersionConfig(
    version: ['v2' => ['v2'], 'v1' => ['v1']],
);

$resolver = new ClassVersion(
    $config,
    new LoadClassFromExtensions(depth: 3, segmentNames: ['Extensions'], versionConfig: $config),
);

// Lookup order for App\Order\Order\Command\Handler\CreateOrder:
//   1. App\Order\Order\Extensions\{v2-chain}\Command\Handler\CreateOrder  (if version set)
//   2. App\Order\Order\Extensions\Command\Handler\CreateOrder             (baseline override)
//   3. App\Order\Order\Command\Handler\CreateOrder                        (generator base)
$className = $resolver(App\Order\Order\Command\Handler\CreateOrder::class, 'v2');
```

### Multi-segment lookup (versions-first across layers)

`segmentNames` accepts more than one segment to probe parallel override
layers in priority order. The reader walks the **version chain first, all
segments per version**, before falling back to versionless baselines:

```php
$resolver = new LoadClassFromExtensions(
    depth: 3,
    segmentNames: ['', 'Platform'],
    versionConfig: $config,
);

// Lookup order for App\Order\Order\Command\Handler\CreateOrder, version 'v2':
//   1. App\Order\Order\v2\Command\Handler\CreateOrder           (dev override v2)
//   2. App\Order\Order\Platform\v2\Command\Handler\CreateOrder  (platform v2)
//   3. App\Order\Order\Command\Handler\CreateOrder              (dev baseline)
//   4. App\Order\Order\Platform\Command\Handler\CreateOrder     (platform baseline)
//   5. App\Order\Order\Command\Handler\CreateOrder              (generator-base fallback)
```

A versioned hit in any segment wins over a versionless hit in any segment —
that is the "versions-first" semantics. Including `''` in `segmentNames`
makes the version chain probe the aggregate root directly without an
intermediate segment.

Classes with fewer than `depth + 1` namespace segments skip the override
lookup and resolve against the generator base directly. No configuration
defaults — the caller decides the layout convention explicitly.

## Documentation

Full documentation, guides, and API reference:

**[docs.jardis.io/en/support/classversion](https://docs.jardis.io/en/support/classversion)**

## License

This package is licensed under the [MIT License](LICENSE.md).

---

**[Jardis](https://jardis.io)** · [Documentation](https://docs.jardis.io) · [Headgent](https://headgent.com)

<!-- BEGIN jardis/dev-skills README block — do not edit by hand -->
## AI-Assisted Development

This package ships with a skill for Claude Code, Cursor, Continue, and Aider. Install it in your consuming project:

```bash
composer require --dev jardis/dev-skills
```

More details: <https://docs.jardis.io/en/skills>
<!-- END jardis/dev-skills README block -->
