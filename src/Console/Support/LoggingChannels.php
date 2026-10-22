<?php

namespace Overfirmament\OverUtils\Console\Support;

use RuntimeException;

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
}
