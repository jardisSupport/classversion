<?php

declare(strict_types=1);

namespace JardisSupport\ClassVersion\Data;

use InvalidArgumentException;
use JardisPort\ClassVersion\ClassVersionConfigInterface;

/**
 * ClassVersionConfig is responsible for managing version configurations
 * using an associative array, where keys are version group identifiers and
 * values are arrays of version labels.
 */
class ClassVersionConfig implements ClassVersionConfigInterface
{
    /** @var array<string, array<string|mixed>> */
    private array $version;

    /** @var array<string, array<string>> */
    private array $fallbacks;

    /**
     * @param array<string, array<string|mixed>> $version
     * @param array<string, array<string>> $fallbacks
     * @throws InvalidArgumentException
     */
    public function __construct(array $version = [], array $fallbacks = [])
    {
        $this->version = $this->validate($version);
        $this->fallbacks = $this->validateFallbacks($fallbacks);
    }

    public function version(?string $version = null): ?string
    {
        $version = trim($version ?? '', " \t\n\r\0\x0B");

        foreach ($this->version as $versionConfigKey => $versions) {
            if (in_array($version, $versions, true)) {
                return $versionConfigKey;
            }
        }

        return $version;
    }

    /** @return array<string> */
    public function fallbackChain(?string $version = null): array
    {
        $resolved = trim($version ?? '', " \t\n\r\0\x0B");
        if ($resolved === '') {
            return [];
        }

        $resolved = $this->version($version) ?? '';
        if ($resolved === '') {
            return [];
        }

        $chain = [$resolved];
        if (isset($this->fallbacks[$resolved])) {
            array_push($chain, ...$this->fallbacks[$resolved]);
        }

        return $chain;
    }

    /**
     * @param array<string, array<string>> $fallbacks
     * @return array<string, array<string>>
     * @throws InvalidArgumentException
     */
    protected function validateFallbacks(array $fallbacks): array
    {
        foreach ($fallbacks as $key => $chain) {
            /** @phpstan-ignore-next-line */
            if (!is_string($key)) {
                throw new InvalidArgumentException(
                    'Fallback keys must be strings'
                );
            }

            /** @phpstan-ignore-next-line */
            if (!is_array($chain)) {
                throw new InvalidArgumentException(
                    sprintf('Fallback chain for key "%s" must be an array', $key)
                );
            }

            foreach ($chain as $i => $value) {
                /** @phpstan-ignore-next-line */
                if (!is_string($value)) {
                    throw new InvalidArgumentException(
                        sprintf('Fallback values must be strings (key "%s", index %d)', $key, $i)
                    );
                }
            }
        }

        return $fallbacks;
    }

    /**
     * @param array<string, array<string|mixed>> $versions
     * @return array<string, array<string|mixed>>
     * @throws InvalidArgumentException
     */
    protected function validate(array $versions): array
    {
        if (!empty($versions)) {
            foreach ($versions as $key => $versionList) {
                /** @phpstan-ignore-next-line */
                if (!is_string($key) || !is_array($versionList)) {
                    throw new InvalidArgumentException(
                        'Parameter must be an assoc array (key as string and value as array)'
                    );
                }

                foreach ($versionList as $i => $label) {
                    if (!is_string($label)) {
                        throw new InvalidArgumentException(
                            sprintf('Version labels must be strings (key "%s", index %d)', $key, $i)
                        );
                    }
                    $trimmed = trim($label);
                    if ($trimmed === '') {
                        throw new InvalidArgumentException(
                            sprintf('Version labels must be non-empty (key "%s", index %d)', $key, $i)
                        );
                    }
                    $versionList[$i] = $trimmed;
                }

                $versions[$key] = array_values(array_unique($versionList));
            }
        }

        return $versions;
    }
}
