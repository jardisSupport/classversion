# Jardis ClassVersion

![Build Status](https://github.com/jardisSupport/classversion/actions/workflows/ci.yml/badge.svg)
[![License: PolyForm Shield](https://img.shields.io/badge/License-PolyForm%20Shield-blue.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)
[![Coverage](https://img.shields.io/badge/Coverage-96.69%25-brightgreen.svg)](https://github.com/jardisSupport/classversion)

> Part of the **[Jardis Business Platform](https://jardis.io)** — Enterprise-grade PHP components for Domain-Driven Design

Runtime class versioning via namespace injection. Load different implementations of the same class by version label — without changing call sites. Configure fallback chains so a missing version silently degrades to the previous one. Register proxy instances for hot-swapping at test or runtime. Deploy new logic versions without breaking existing code.

---

## Features

- **SubDirectory Resolution** — injects a version label into the namespace to locate versioned class implementations
- **Proxy Cache** — pre-register object instances via `LoadClassFromProxy` that are returned directly, bypassing class loading
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
use JardisSupport\ClassVersion\TracingClassVersion;

// Fallback chain: if V2 namespace is missing, try V1 before the base class
$config = new ClassVersionConfig(
    version: ['V2' => ['v2', '2.0'], 'V1' => ['v1', '1.0']],
    fallbacks: ['V2' => ['V1']],
);

// Proxy cache: return a pre-built instance for a specific class + version
$proxy = new LoadClassFromProxy($config);
$proxy->addProxy(App\Service\Calculator::class, new MyTestCalculator(), 'v2');

$resolver = new ClassVersion(
    $config,
    new LoadClassFromSubDirectory($config),
    $proxy,
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

## Documentation

Full documentation, guides, and API reference:

**[jardis.io/docs/support/classversion](https://jardis.io/docs/support/classversion)**

## License

This package is licensed under the [PolyForm Shield License 1.0.0](LICENSE.md). Free for all use except building competing frameworks or developer tooling.

---

**[Jardis](https://jardis.io)** · [Documentation](https://jardis.io/docs) · [Headgent](https://headgent.com)
