# Jardis ClassVersion

![Build Status](https://github.com/jardissupport/classversion/actions/workflows/ci.yml/badge.svg)
[![License: PolyForm NC](https://img.shields.io/badge/License-PolyForm%20NC-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg)](https://phpstan.org/)
[![PSR-4](https://img.shields.io/badge/autoload-PSR--4-blue.svg)](https://www.php-fig.org/psr/psr-4/)
[![PSR-12](https://img.shields.io/badge/code%20style-PSR--12-blue.svg)](https://www.php-fig.org/psr/psr-12/)
[![Coverage](https://img.shields.io/badge/coverage->95%25-brightgreen)](https://github.com/jardissupport/classversion)

> Part of the **[Jardis Ecosystem](https://jardis.io)** — A modular DDD framework for PHP

A flexible class version loader for PHP applications. Jardis ClassVersion enables loading different versions of classes from subdirectories — perfect for **API versioning**, **feature flags**, **legacy support**, and **A/B testing** in domain-driven design architectures.

---

## Features

- **Dynamic Class Loading** — Load different versions of a class from corresponding subdirectories
- **Fallback Chain** — Define version fallbacks for incremental migrations (v3 → v2 → v1) without duplicating unchanged classes
- **Proxy Support** — Register and use proxy objects for classes instead of loading from subdirectories
- **Debug Tracing** — Trace every class resolution for debugging with `TracingClassVersion`
- **Flexible Configuration** — Map version labels to subdirectory structures with aliases
- **Type-Safe** — Full PHP 8.2+ type safety with generics support
- **PSR-4 Compatible** — Follows PSR-4 autoloading standards

---

## Installation

```bash
composer require jardissupport/classversion
```

## Quick Start

```php
use JardisSupport\ClassVersion\ClassVersion;
use JardisSupport\ClassVersion\Data\ClassVersionConfig;
use JardisSupport\ClassVersion\Reader\LoadClassFromSubDirectory;

// Map subdirectories to version labels
$config = new ClassVersionConfig([
    'v1' => ['version1', 'api-v1', 'legacy'],
    'v2' => ['version2', 'api-v2', 'current'],
]);

$classFinder = new LoadClassFromSubDirectory();
$classVersion = new ClassVersion($config, $classFinder);

// Load default version
$default = $classVersion(UserService::class);

// Load from v1 subdirectory (using any mapped label)
$legacy = $classVersion(UserService::class, 'legacy');

// Load from v2 subdirectory
$current = $classVersion(UserService::class, 'api-v2');
```

## Fallback Chain

For incremental migrations where not every class changes between versions:

```php
use JardisSupport\ClassVersion\Data\ClassVersionConfig;
use JardisSupport\ClassVersion\Reader\LoadClassFromSubDirectory;
use JardisSupport\ClassVersion\ClassVersion;

$config = new ClassVersionConfig(
    [
        'v1' => ['version1'],
        'v2' => ['version2'],
        'v3' => ['version3'],
    ],
    // v3 falls back to v2, then v1
    ['v3' => ['v2', 'v1']]
);

// Pass config to LoadClassFromSubDirectory to enable fallback chain
$classFinder = new LoadClassFromSubDirectory($config);
$classVersion = new ClassVersion($config, $classFinder);

// If UserService exists in v3/ → uses v3
// If not, tries v2/ → uses v2
// If not, tries v1/ → uses v1
// If none found → uses base class
$service = $classVersion(UserService::class, 'version3');
```

## Debug Tracing

Wrap any `ClassVersionInterface` with `TracingClassVersion` for debugging:

```php
use JardisSupport\ClassVersion\TracingClassVersion;

$tracing = new TracingClassVersion($classVersion);

$result = $tracing(UserService::class, 'version1');

$trace = $tracing->getTrace();
// [
//     [
//         'requested' => 'App\UserService',
//         'version'   => 'version1',
//         'resolved'  => 'App\v1\UserService',
//         'type'      => 'class-string',
//     ],
// ]

$tracing->clearTrace(); // Reset trace log
```

## Documentation

Full documentation, examples and API reference:

**→ [jardis.io/docs/support/classversion](https://jardis.io/docs/support/classversion)**

## Jardis Ecosystem

This package is part of the Jardis Ecosystem — a collection of modular, high-quality PHP packages designed for Domain-Driven Design.

| Category | Packages |
|----------|----------|
| **Core** | Kernel, Entity, Workflow |
| **Support** | DotEnv, Cache, Logger, Messaging, DbConnection, DbQuery, DbSchema, Validation, Factory, ClassVersion |
| **Generic** | Auth |
| **Tools** | Builder, Migration, Faker |

**→ [Explore all packages](https://jardis.io/docs)**

## License

This package is licensed under the [PolyForm Noncommercial License 1.0.0](LICENSE).

For commercial use, see [COMMERCIAL.md](COMMERCIAL.md).

---

**[Jardis Ecosystem](https://jardis.io)** by [Headgent Development](https://headgent.com)
