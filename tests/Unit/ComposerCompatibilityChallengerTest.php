<?php

namespace Tests\Unit;

use Composer\Semver\Semver;
use PHPUnit\Framework\TestCase;

class ComposerCompatibilityChallengerTest extends TestCase
{
    /**
     * Path to root composer.json.
     */
    protected string $composerJsonPath;

    /**
     * Path to root composer.lock.
     */
    protected string $composerLockPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->composerJsonPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'composer.json';
        $this->composerLockPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'composer.lock';
    }

    /**
     * Validate composer.json constraints and config.platform configuration.
     */
    public function test_composer_json_schema_and_critical_constraints(): void
    {
        $this->assertFileExists($this->composerJsonPath);
        $content = json_decode((string) file_get_contents($this->composerJsonPath), true);
        $this->assertIsArray($content);

        // Root PHP requirement must be ^8.3
        $this->assertArrayHasKey('require', $content);
        $this->assertSame('^8.3', $content['require']['php'] ?? null);

        // Flysystem AWS S3 must use semver range (^3.0), not exact constraint ("3.0")
        $this->assertSame('^3.0', $content['require']['league/flysystem-aws-s3-v3'] ?? null);

        // Platform configuration must pin PHP to 8.3.33
        $this->assertArrayHasKey('config', $content);
        $this->assertArrayHasKey('platform', $content['config']);
        $this->assertSame('8.3.33', $content['config']['platform']['php'] ?? null);
    }

    /**
     * Verify all locked dependencies are strictly compatible with PHP 8.3.33.
     */
    public function test_all_locked_packages_are_compatible_with_php_8_3(): void
    {
        $this->assertFileExists($this->composerLockPath);
        $lock = json_decode((string) file_get_contents($this->composerLockPath), true);
        $this->assertIsArray($lock);

        /** @var array<int, array<string, mixed>> $packages */
        $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
        $this->assertNotEmpty($packages);

        $incompatible = [];

        foreach ($packages as $pkg) {
            $name = (string) ($pkg['name'] ?? 'unknown');
            $phpConstraint = $pkg['require']['php'] ?? null;

            if ($phpConstraint !== null) {
                if (! Semver::satisfies('8.3.33', (string) $phpConstraint)) {
                    $incompatible[] = sprintf('%s (locked: %s, requires: %s)', $name, $pkg['version'] ?? '?', $phpConstraint);
                }
            }
        }

        $this->assertEmpty(
            $incompatible,
            'Found packages in composer.lock incompatible with PHP 8.3.33: '.implode('; ', $incompatible)
        );
    }

    /**
     * Verify all locked dependencies are strictly compatible with PHP 8.4 (8.4.0 and 8.4.1).
     */
    public function test_all_locked_packages_are_compatible_with_php_8_4(): void
    {
        $this->assertFileExists($this->composerLockPath);
        $lock = json_decode((string) file_get_contents($this->composerLockPath), true);
        $this->assertIsArray($lock);

        /** @var array<int, array<string, mixed>> $packages */
        $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
        $this->assertNotEmpty($packages);

        $incompatible840 = [];
        $incompatible841 = [];

        foreach ($packages as $pkg) {
            $name = (string) ($pkg['name'] ?? 'unknown');
            $phpConstraint = $pkg['require']['php'] ?? null;

            if ($phpConstraint !== null) {
                if (! Semver::satisfies('8.4.0', (string) $phpConstraint)) {
                    $incompatible840[] = sprintf('%s (locked: %s, requires: %s)', $name, $pkg['version'] ?? '?', $phpConstraint);
                }
                if (! Semver::satisfies('8.4.1', (string) $phpConstraint)) {
                    $incompatible841[] = sprintf('%s (locked: %s, requires: %s)', $name, $pkg['version'] ?? '?', $phpConstraint);
                }
            }
        }

        $this->assertEmpty(
            $incompatible840,
            'Found packages in composer.lock incompatible with PHP 8.4.0: '.implode('; ', $incompatible840)
        );

        $this->assertEmpty(
            $incompatible841,
            'Found packages in composer.lock incompatible with PHP 8.4.1: '.implode('; ', $incompatible841)
        );
    }

    /**
     * Verify that symfony/filesystem is locked to a version compatible with both PHP 8.3 and 8.4.
     */
    public function test_symfony_filesystem_version_allows_both_8_3_and_8_4(): void
    {
        $lock = json_decode((string) file_get_contents($this->composerLockPath), true);
        $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);

        $fsPackage = null;
        foreach ($packages as $pkg) {
            if (($pkg['name'] ?? null) === 'symfony/filesystem') {
                $fsPackage = $pkg;
                break;
            }
        }

        $this->assertNotNull($fsPackage, 'symfony/filesystem was not found in composer.lock');
        $version = (string) ($fsPackage['version'] ?? '');
        $phpConstraint = (string) ($fsPackage['require']['php'] ?? '');

        // It should NOT be v8.1.x which demanded php >=8.4.1
        $this->assertStringStartsNotWith('v8.', $version, 'symfony/filesystem should not be v8.x requiring >=8.4.1');

        // It must satisfy both PHP 8.3.33 and PHP 8.4.1
        $this->assertTrue(Semver::satisfies('8.3.33', $phpConstraint), "symfony/filesystem ($version) does not satisfy PHP 8.3.33");
        $this->assertTrue(Semver::satisfies('8.4.1', $phpConstraint), "symfony/filesystem ($version) does not satisfy PHP 8.4.1");
    }

    /**
     * Verify composer validate --strict exits with code 0.
     */
    public function test_composer_validate_strict(): void
    {
        $output = [];
        $exitCode = 0;
        exec('composer validate --strict 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, 'composer validate --strict failed: '.implode(PHP_EOL, $output));
    }

    /**
     * Verify composer check-platform-reqs --lock exits with code 0.
     */
    public function test_composer_check_platform_reqs_lock(): void
    {
        $output = [];
        $exitCode = 0;
        exec('composer check-platform-reqs --lock 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, 'composer check-platform-reqs --lock failed: '.implode(PHP_EOL, $output));
    }

    /**
     * Verify composer install --dry-run exits with code 0.
     */
    public function test_composer_install_dry_run(): void
    {
        $output = [];
        $exitCode = 0;
        exec('composer install --dry-run 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, 'composer install --dry-run failed: '.implode(PHP_EOL, $output));
    }
}
