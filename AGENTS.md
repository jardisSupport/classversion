# jardissupport/classversion

Versioned classes via Namespace-Injection and/or Proxy-Registry. Entry point: `$classVersion(Class::class, $version)` via `__invoke` — Composite of `LoadClassFromProxy` (wins) and a configurable class finder (`LoadClassFromSubDirectory` or `LoadClassFromExtensions`, with fallback chain), configured through `ClassVersionConfig`.

## Source layout

- `src/ClassVersion.php` — orchestrator (implements `ClassVersionInterface`).
- `src/Data/` — `ClassVersionConfig`.
- `src/Reader/` — resolvers that implement `ClassVersionInterface`: `LoadClassFromSubDirectory`, `LoadClassFromExtensions`, `LoadClassFromProxy`.
- `src/Support/` — helpers that do **not** implement `ClassVersionInterface` and never take ClassVersion's place: `ClassResolutionCache`, `TracingClassVersion`.

## Usage essentials

- **Loader order fixed:** `ClassVersion::__invoke` checks `LoadClassFromProxy` first (returns `object|null`), then falls back to the configured class finder (returns `class-string`). Return type is `mixed` — Proxy returns object, class finders return class name for `new $class()` instantiation.
- **Two class finders, pick one per `ClassVersion` instance:**
  - `LoadClassFromSubDirectory` — injects version **before the class name**: `Acme\Domain\User` + `v2` → `Acme\Domain\v2\User`.
  - `LoadClassFromExtensions(depth, segmentNames, ?config)` — inserts one or more segments at position `depth` from the left; versioned subdir goes after each segment. `segmentNames: array<string>` (default `['Extensions']`); `''` is a legal entry meaning "no subdir inserted, probe the root directly". With `depth:3, segmentNames:['Extensions']`, `Acme\BC\Agg\Command\Handler\Foo` → `Acme\BC\Agg\Extensions\v2\Command\Handler\Foo` → baseline `Acme\BC\Agg\Extensions\Command\Handler\Foo` → generator base. Multi-segment example `segmentNames: ['', 'Platform']` walks **versions-first across all segments** before falling back to baselines: `…\v2\…` → `…\Platform\v2\…` → `…\…` (dev baseline) → `…\Platform\…` (platform baseline) → generator base. Classes shorter than `depth+1` skip override lookup. Pure string math, zero array allocations on the happy path.
- **Fallback chain in `ClassVersionConfig`** explicitly as `['v3' => ['v2', 'v1']]` — no recursive resolution, the order is the lookup path. **The base class (without version) is the implicit final fallback and is NOT in the `fallbackChain()` array.** Alias resolution (`'current'` → `'v2'`) happens before chain lookup.
- **Label validation in constructor:** Keys/values must be non-empty strings, trimming + dedup applied, otherwise `InvalidArgumentException`. `version($label)` returns the key (or passthrough for unknown), `version(null)` → `''`. Labels are case-sensitive.
- **`LoadClassFromProxy` fluent:** `addProxy(Logger::class, new FileLogger(), 'prod')->addProxy(...)`, `removeProxy(Logger::class, 'prod')` cleans up empty buckets. Data structure: `$cachedProxy[$version][$className] = $object`. Without config, proxy only trims `$version`, no alias resolving.
- **`ClassResolutionCache` (optional helper):** passed as `new ClassVersion($config, $finder, $proxy, cache: new ClassResolutionCache())`. Memoizes hits **and** misses per `(className, version)` key. Exception is cached and re-thrown without re-running the inner resolver. API: `remember(string $key, callable $producer): mixed`, `clear(): void`. **Never replaces `ClassVersion`** — consumer type stays `ClassVersion`.
- **`TracingClassVersion` Decorator for debug:** `$tracing->getTrace()` returns a list of `['requested', 'version', 'resolved', 'type' => 'class-string'|'proxy']`. Exceptions propagate **without** a trace entry. Layer rule: **Application Layer yes — Domain Layer never imports `ClassVersion`.**

## Full reference

https://docs.jardis.io/en/support/classversion
