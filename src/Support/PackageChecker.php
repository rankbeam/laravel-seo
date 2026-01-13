<?php

namespace Fibonoir\LaravelSEO\Support;

use Illuminate\Support\Facades\File;

class PackageChecker
{
    /**
     * Check if a Composer package is installed.
     */
    public static function isPackageInstalled(string $package): bool
    {
        $composerLock = base_path('composer.lock');

        if (!File::exists($composerLock)) {
            return false;
        }

        $lock = json_decode(File::get($composerLock), true);
        $packages = array_merge(
            $lock['packages'] ?? [],
            $lock['packages-dev'] ?? []
        );

        return collect($packages)->contains('name', $package);
    }

    /**
     * Get the version of an installed package.
     */
    public static function getPackageVersion(string $package): ?string
    {
        $composerLock = base_path('composer.lock');

        if (!File::exists($composerLock)) {
            return null;
        }

        $lock = json_decode(File::get($composerLock), true);
        $packages = array_merge(
            $lock['packages'] ?? [],
            $lock['packages-dev'] ?? []
        );

        $found = collect($packages)->firstWhere('name', $package);

        return $found['version'] ?? null;
    }

    /**
     * Check if spatie/laravel-sitemap is available.
     */
    public static function hasSitemapPackage(): bool
    {
        return self::isPackageInstalled('spatie/laravel-sitemap');
    }

    /**
     * Check if spatie/browsershot is available.
     */
    public static function hasBrowsershotPackage(): bool
    {
        return self::isPackageInstalled('spatie/browsershot');
    }

    /**
     * Check if google/apiclient is available.
     */
    public static function hasGoogleApiClient(): bool
    {
        return self::isPackageInstalled('google/apiclient');
    }

    /**
     * Check if Filament is installed.
     */
    public static function hasFilament(): bool
    {
        return self::isPackageInstalled('filament/filament');
    }

    /**
     * Check if Livewire is installed.
     */
    public static function hasLivewire(): bool
    {
        return self::isPackageInstalled('livewire/livewire');
    }

    /**
     * Check if Inertia is installed.
     */
    public static function hasInertia(): bool
    {
        return self::isPackageInstalled('inertiajs/inertia-laravel');
    }
}
