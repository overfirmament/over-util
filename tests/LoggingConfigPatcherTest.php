<?php

namespace Overfirmament\OverUtils\Tests;

use Illuminate\Filesystem\Filesystem;
use Overfirmament\OverUtils\Console\Support\LoggingChannels;
use Overfirmament\OverUtils\Console\Support\LoggingConfigPatcher;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class LoggingConfigPatcherTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/over-util-'.bin2hex(random_bytes(6));
        (new Filesystem)->makeDirectory($this->directory, 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function it_adds_missing_channels_and_keeps_existing_ones(): void
    {
        $path = $this->writeLoggingConfig();

        $result = (new LoggingConfigPatcher)->patch(
            $path,
            LoggingChannels::snippets()
        );

        $this->assertTrue($result->hasChanges());
        $this->assertSame(['http_out', 'http_in', 'rabbit_mq'], $result->added);
        $this->assertSame([], $result->replaced);

        $config = require $path;

        $this->assertArrayHasKey('http_out', $config['channels']);
        $this->assertArrayHasKey('http_in', $config['channels']);
        $this->assertArrayHasKey('rabbit_mq', $config['channels']);
        $this->assertArrayHasKey('single', $config['channels']);
        $this->assertSame('single', $config['channels']['single']['driver']);
        $this->assertFileExists($path.'.overutil.bak');
    }

    #[Test]
    public function it_skips_existing_channels_by_default(): void
    {
        $path = $this->writeLoggingConfig("
            'http_out' => [
                'driver' => 'single',
                'path' => storage_path('logs/custom.log'),
            ],
        ");

        $result = (new LoggingConfigPatcher)->patch(
            $path,
            LoggingChannels::snippets()
        );

        $this->assertContains('http_out', $result->skipped);
        $this->assertSame(['http_in', 'rabbit_mq'], $result->added);

        $config = require $path;
        $this->assertSame('single', $config['channels']['http_out']['driver']);
        $this->assertStringEndsWith('logs/custom.log', $config['channels']['http_out']['path']);
    }

    #[Test]
    public function it_replaces_existing_channels_when_forced(): void
    {
        $path = $this->writeLoggingConfig("
            'http_out' => [
                'driver' => 'single',
                'path' => storage_path('logs/custom.log'),
            ],
        ");

        $result = (new LoggingConfigPatcher)->patch(
            $path,
            LoggingChannels::snippets(),
            force: true
        );

        $this->assertContains('http_out', $result->replaced);
        $this->assertSame(1, substr_count((string) file_get_contents($path), "'http_out'"));

        $config = require $path;
        $this->assertSame('daily', $config['channels']['http_out']['driver']);
        $this->assertStringEndsWith('logs/http/out_info.log', $config['channels']['http_out']['path']);
    }

    #[Test]
    public function it_can_force_the_last_entry_while_adding_missing_channels(): void
    {
        $path = $this->directory.'/logging.php';

        file_put_contents($path, <<<'PHP'
<?php

return [
    'channels' => [
        'http_out' => [
            'driver' => 'single',
            'path' => storage_path('logs/custom.log'),
        ],
    ],
];
PHP);

        $result = (new LoggingConfigPatcher)->patch(
            $path,
            LoggingChannels::snippets(),
            force: true
        );

        $this->assertContains('http_out', $result->replaced);
        $this->assertSame(['http_in', 'rabbit_mq'], $result->added);
        $this->assertStringNotContainsString('],,', (string) file_get_contents($path));

        $config = require $path;

        $this->assertSame('daily', $config['channels']['http_out']['driver']);
        $this->assertArrayHasKey('http_in', $config['channels']);
        $this->assertArrayHasKey('rabbit_mq', $config['channels']);
    }

    #[Test]
    public function it_does_not_write_anything_on_dry_run(): void
    {
        $path = $this->writeLoggingConfig();
        $before = (string) file_get_contents($path);

        $result = (new LoggingConfigPatcher)->patch(
            $path,
            LoggingChannels::snippets(),
            dryRun: true
        );

        $this->assertTrue($result->hasChanges());
        $this->assertSame($before, file_get_contents($path));
        $this->assertFileDoesNotExist($path.'.overutil.bak');
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $path = $this->writeLoggingConfig();

        (new LoggingConfigPatcher)->patch($path, LoggingChannels::snippets());
        $result = (new LoggingConfigPatcher)->patch($path, LoggingChannels::snippets());

        $this->assertFalse($result->hasChanges());
        $this->assertSame([], $result->added);
        $this->assertSame([], $result->replaced);
    }

    #[Test]
    public function it_can_patch_an_empty_channels_array(): void
    {
        $path = $this->directory.'/logging.php';

        file_put_contents($path, "<?php\n\nreturn ['channels' => []];\n");

        $result = (new LoggingConfigPatcher)->patch(
            $path,
            LoggingChannels::snippets()
        );

        $this->assertSame(['http_out', 'http_in', 'rabbit_mq'], $result->added);

        $config = require $path;

        $this->assertArrayHasKey('http_out', $config['channels']);
        $this->assertArrayHasKey('http_in', $config['channels']);
        $this->assertArrayHasKey('rabbit_mq', $config['channels']);
    }

    #[Test]
    public function it_fails_when_channels_array_is_missing(): void
    {
        $path = $this->directory.'/logging.php';
        file_put_contents($path, "<?php\nreturn ['default' => 'stack'];\n");

        $this->expectException(RuntimeException::class);

        (new LoggingConfigPatcher)->patch($path, LoggingChannels::snippets());
    }

    private function writeLoggingConfig(string $extraChannels = ''): string
    {
        $path = $this->directory.'/logging.php';
        $extraChannels = trim($extraChannels) === '' ? '' : rtrim($extraChannels)."\n";

        file_put_contents($path, <<<PHP
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
{$extraChannels}    ],
];
PHP);

        return $path;
    }
}
