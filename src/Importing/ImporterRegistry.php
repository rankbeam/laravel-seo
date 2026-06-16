<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Importing;

use Illuminate\Contracts\Container\Container;
use Rankbeam\Seo\Importing\Contracts\Importer;

/**
 * Maps CLI source keys (e.g. "ralphjsmit") to {@see Importer} implementations.
 *
 * Importers are registered by class and resolved from the container on demand,
 * so they can depend on services. A new source (e.g. the WordPress importer)
 * adds one extra {@see self::register()} call in the service provider — no
 * command edit.
 */
class ImporterRegistry
{
    /**
     * @var array<string, class-string<Importer>>
     */
    protected array $importers = [];

    public function __construct(
        protected Container $container,
    ) {}

    /**
     * Register an importer class under a source key.
     *
     * @param  class-string<Importer>  $importer
     */
    public function register(string $key, string $importer): void
    {
        $this->importers[$key] = $importer;
    }

    public function has(string $key): bool
    {
        return isset($this->importers[$key]);
    }

    /**
     * Resolve the importer for a key, or null if none is registered.
     */
    public function get(string $key): ?Importer
    {
        if (! isset($this->importers[$key])) {
            return null;
        }

        return $this->container->make($this->importers[$key]);
    }

    /**
     * The registered source keys, sorted for stable display.
     *
     * @return array<int, string>
     */
    public function keys(): array
    {
        $keys = array_keys($this->importers);
        sort($keys);

        return $keys;
    }
}
