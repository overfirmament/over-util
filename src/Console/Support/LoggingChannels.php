<?php

namespace Overfirmament\OverUtils\Console\Support;

use RuntimeException;
use Throwable;

final class LoggingChannels
{
    /**
     * @return array<string, string> channel name => PHP source snippet
     */
    public static function snippets(): array
    {
        $path = dirname(__DIR__).'/Stubs/logging-channels.stub';
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("无法读取日志 channel 模板：{$path}");
        }

        preg_match_all(
            '/^\/\/ @channel\s+([A-Za-z0-9_.-]+)\s*\R(.*?)(?=^\/\/ @channel|\z)/ms',
            $contents,
            $matches,
            PREG_SET_ORDER
        );

        $channels = [];

        foreach ($matches as $match) {
            $channels[$match[1]] = rtrim($match[2]);
        }

        if ($channels === []) {
            throw new RuntimeException('日志 channel 模板为空或格式不正确。');
        }

        return $channels;
    }

    /**
     * Materialize the channel arrays from the stub.
     *
     * The stub is the single source of truth: the install command extracts its
     * snippets, and the service provider uses these arrays when a channel is
     * missing from the application's logging config.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function channels(): array
    {
        $channels = [];

        foreach (self::snippets() as $name => $snippet) {
            try {
                $entry = eval("return [{$snippet}];");
            } catch (Throwable $e) {
                throw new RuntimeException("无法解析日志 channel 定义 [{$name}]：{$e->getMessage()}", 0, $e);
            }

            if (! is_array($entry) || ! isset($entry[$name]) || ! is_array($entry[$name])) {
                throw new RuntimeException("日志 channel 定义 [{$name}] 不是有效的数组。");
            }

            /** @var array<string, mixed> $channel */
            $channel = $entry[$name];
            $channels[$name] = $channel;
        }

        return $channels;
    }
}
