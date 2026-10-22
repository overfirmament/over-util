<?php

namespace Overfirmament\OverUtils\Tests;

use Illuminate\Filesystem\Filesystem;
use Overfirmament\OverUtils\Console\Support\AppServiceProviderPatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as BaseTestCase;

class AppServiceProviderPatcherTest extends BaseTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/over-util-provider-'.bin2hex(random_bytes(6));
        (new Filesystem)->makeDirectory($this->directory, 0755, true);
        $this->writeProvider($this->standardProvider());
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function it_injects_the_commented_worker_stopping_block(): void
    {
        $path = $this->directory.'/AppServiceProvider.php';

        $result = (new AppServiceProviderPatcher)->patch($path);

        $this->assertTrue($result->hasChanges());
        $this->assertSame('added', $result->status);
        $this->assertFileExists($path.'.overutil.bak');

        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString(AppServiceProviderPatcher::MARKER, $contents);
        $this->assertStringContainsString(
            'WorkerStopping::class',
            $contents
        );
        $this->assertStringContainsString(
            '//         app(\Overfirmament\OverUtils\ToolBox\RabbitMqConnectionManager::class)->disconnect();',
            $contents
        );

        token_get_all($contents, TOKEN_PARSE);
    }

    #[Test]
    public function it_preserves_existing_boot_content(): void
    {
        $path = $this->directory.'/AppServiceProvider.php';

        file_put_contents($path, str_replace(
            '        //',
            "        config(['marker' => true]);",
            $this->standardProvider()
        ));

        (new AppServiceProviderPatcher)->patch($path);

        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString("config(['marker' => true]);", $contents);
        $this->assertLessThan(
            strpos($contents, AppServiceProviderPatcher::MARKER),
            strpos($contents, "config(['marker' => true]);")
        );
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $path = $this->directory.'/AppServiceProvider.php';

        $first = (new AppServiceProviderPatcher)->patch($path);
        $second = (new AppServiceProviderPatcher)->patch($path);

        $this->assertTrue($first->hasChanges());
        $this->assertFalse($second->hasChanges());
        $this->assertSame(1, substr_count((string) file_get_contents($path), AppServiceProviderPatcher::MARKER));
    }

    #[Test]
    public function it_does_not_write_during_a_dry_run(): void
    {
        $path = $this->directory.'/AppServiceProvider.php';
        $before = (string) file_get_contents($path);

        $result = (new AppServiceProviderPatcher)->patch($path, dryRun: true);

        $this->assertTrue($result->hasChanges());
        $this->assertNull($result->backupPath);
        $this->assertSame($before, file_get_contents($path));
        $this->assertFileDoesNotExist($path.'.overutil.bak');
    }

    #[Test]
    public function it_adds_a_boot_method_when_one_does_not_exist(): void
    {
        $path = $this->directory.'/AppServiceProvider.php';

        file_put_contents($path, <<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }
}
PHP);

        $result = (new AppServiceProviderPatcher)->patch($path);
        $contents = (string) file_get_contents($path);

        $this->assertTrue($result->addedBootMethod);
        $this->assertStringContainsString('public function boot(): void', $contents);
        $this->assertStringContainsString(AppServiceProviderPatcher::MARKER, $contents);

        token_get_all($contents, TOKEN_PARSE);
    }

    #[Test]
    public function it_handles_a_compact_empty_boot_method(): void
    {
        $path = $this->directory.'/AppServiceProvider.php';

        file_put_contents($path, <<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void {}
}
PHP);

        (new AppServiceProviderPatcher)->patch($path);

        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString("{\n", $contents);
        $this->assertStringContainsString(AppServiceProviderPatcher::MARKER, $contents);

        token_get_all($contents, TOKEN_PARSE);
    }

    private function writeProvider(string $contents): void
    {
        file_put_contents($this->directory.'/AppServiceProvider.php', $contents);
    }

    private function standardProvider(): string
    {
        return <<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
PHP;
    }
}
