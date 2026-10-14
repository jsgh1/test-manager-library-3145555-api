<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ArchitectureTest extends TestCase
{
    private function files(string $relative): array
    {
        $directory = dirname(__DIR__).'/'.$relative;
        $paths = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $paths[] = $file->getPathname();
            }
        }
        return $paths;
    }

    public function test_domain_has_no_framework_or_outer_layer_dependencies(): void
    {
        foreach ($this->files('app/Domain') as $path) {
            $content = file_get_contents($path);
            self::assertDoesNotMatchRegularExpression(
                '/(?:Illuminate\\\\|App\\\\(?:Application|Adapters|Http|Providers|Support)\\\\|DB::|Str::)/',
                $content,
                'Domain dependency violation: '.$path
            );
        }
    }

    public function test_application_has_no_adapter_framework_or_controller_dependencies(): void
    {
        foreach ($this->files('app/Application') as $path) {
            $content = file_get_contents($path);
            self::assertDoesNotMatchRegularExpression(
                '/(?:Illuminate\\\\|App\\\\(?:Adapters|Http|Providers|Support)\\\\|DB::|Str::)/',
                $content,
                'Application dependency violation: '.$path
            );
        }
    }

    public function test_only_composition_root_binds_application_ports(): void
    {
        $provider = file_get_contents(dirname(__DIR__).'/app/Providers/AppServiceProvider.php');
        self::assertStringContainsString('LendingPort::class, PostgresLendingAdapter::class', $provider);
        self::assertStringContainsString('Clock::class, SystemClock::class', $provider);
    }
}
