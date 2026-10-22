<?php

namespace Overfirmament\OverUtils\Tests;

use Illuminate\Filesystem\Filesystem;
use Overfirmament\OverUtils\Console\Support\AppServiceProviderPatcher;
use PHPUnit\Framework\Attributes\Test;

class InstallCommandTest extends TestCase
{
    private string $configPath;

    private string $appPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configPath = sys_get_temp_dir().'/over-util-install-'.bin2hex(random_bytes(6));
        $this->appPath = sys_get_temp_dir().'/over-util-app-'.bin2hex(random_bytes(6));
        (new Filesystem)->makeDirectory($this->configPath, 0755, true);
        (new Filesystem)->makeDirectory($this->appPath.'/Providers', 0755, true);

        file_put_contents($this->appPath.'/Providers/AppServiceProvider.php', <<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //
    }
}
PHP);

        file_put_contents($this->configPath.'/logging.php', <<<'PHP'
<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
        ],
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
        ],
    ],
];
PHP);

        $this->app->useConfigPath($this->configPath);
        $this->app->useAppPath($this->appPath);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->configPath);
        (new Filesystem)->deleteDirectory($this->appPath);

        parent::tearDown();
    }

    #[Test]
    public function it_can_run_as_a_dry_run(): void
    {
        $before = (string) file_get_contents($this->configPath.'/logging.php');
        $providerPath = $this->appPath.'/Providers/AppServiceProvider.php';
        $providerBefore = (string) file_get_contents($providerPath);

        $this->artisan('overutil:install', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertExitCode(0);

        $this->assertSame($before, file_get_contents($this->configPath.'/logging.php'));
        $this->assertSame($providerBefore, file_get_contents($providerPath));
        $this->assertFileDoesNotExist($this->configPath.'/overutil.php');
        $this->assertFileDoesNotExist($providerPath.'.overutil.bak');
    }

    #[Test]
    public function it_skips_existing_config_unless_forced(): void
    {
        file_put_contents(
            $this->configPath.'/overutil.php',
            "<?php\n\nreturn ['marker' => true];\n"
        );

        $this->artisan('overutil:install')->assertExitCode(0);

        $this->assertTrue((require $this->configPath.'/overutil.php')['marker']);

        $this->artisan('overutil:install', ['--force' => true])->assertExitCode(0);

        $this->assertArrayNotHasKey('marker', require $this->configPath.'/overutil.php');
    }

    #[Test]
    public function it_publishes_config_and_patches_logging_channels(): void
    {
        $this->artisan('overutil:install')
            ->assertExitCode(0);

        $this->assertFileExists($this->configPath.'/overutil.php');
        $this->assertFileExists($this->configPath.'/alarm.php');
        $this->assertFileExists($this->configPath.'/rabbitmq.php');

        $config = require $this->configPath.'/logging.php';

        $this->assertArrayHasKey('http_out', $config['channels']);
        $this->assertArrayHasKey('http_in', $config['channels']);
        $this->assertArrayHasKey('rabbit_mq', $config['channels']);

        $providerPath = $this->appPath.'/Providers/AppServiceProvider.php';
        $this->assertStringContainsString(
            AppServiceProviderPatcher::MARKER,
            (string) file_get_contents($providerPath)
        );
    }

    #[Test]
    public function it_does_not_duplicate_the_provider_block_when_run_twice(): void
    {
        $this->artisan('overutil:install')->assertExitCode(0);
        $this->artisan('overutil:install')->assertExitCode(0);

        $providerPath = $this->appPath.'/Providers/AppServiceProvider.php';
        $contents = (string) file_get_contents($providerPath);

        $this->assertSame(1, substr_count($contents, AppServiceProviderPatcher::MARKER));
    }
}
