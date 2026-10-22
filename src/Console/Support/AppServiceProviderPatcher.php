<?php

namespace Overfirmament\OverUtils\Console\Support;

use ParseError;
use RuntimeException;

/**
 * Inserts an opt-in WorkerStopping listener into an application's
 * AppServiceProvider::boot() method.
 *
 * The inserted code is fully commented out. It is only a template so the
 * consuming application can decide whether it wants to close shared RabbitMQ
 * connections when a long-running queue worker stops.
 */
final class AppServiceProviderPatcher
{
    public const MARKER = '// over-util: rabbitmq-worker-stopping';

    /** @var array<int, int|string> */
    private const IGNORED_TOKENS = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    /** @var array<int, string> */
    private const BLOCK_LINES = [
        self::MARKER,
        '// 可选：Horizon/队列 worker 停止时关闭 RabbitMQ 共享连接。',
        '// 确认需要后，取消下面代码的注释即可启用。',
        '// \Illuminate\Support\Facades\Event::listen(',
        '//     \Illuminate\Queue\Events\WorkerStopping::class,',
        '//     static function (\Illuminate\Queue\Events\WorkerStopping $event): void {',
        '//         app(\Overfirmament\OverUtils\ToolBox\RabbitMqConnectionManager::class)->disconnect();',
        '//     }',
        '// );',
    ];

    /**
     * @throws RuntimeException
     */
    public function patch(string $path, bool $dryRun = false): AppServiceProviderPatchResult
    {
        if (! is_file($path)) {
            throw new RuntimeException("AppServiceProvider 不存在：{$path}");
        }

        $code = file_get_contents($path);

        if ($code === false) {
            throw new RuntimeException("无法读取 AppServiceProvider：{$path}");
        }

        if (str_contains($code, self::MARKER)) {
            return new AppServiceProviderPatchResult('skipped');
        }

        $eol = str_contains($code, "\r\n") ? "\r\n" : "\n";
        $tokens = $this->tokenize($code);
        $boot = $this->findBootMethod($tokens);
        $addedBootMethod = false;

        if ($boot !== null) {
            $newCode = $this->insertIntoBoot($code, $tokens, $boot, $eol);
        } else {
            $newCode = $this->appendBootMethod($code, $tokens, $eol);
            $addedBootMethod = true;
        }

        if ($newCode === $code) {
            return new AppServiceProviderPatchResult('skipped');
        }

        try {
            $validatedTokens = token_get_all($newCode, TOKEN_PARSE);

            if ($validatedTokens === []) {
                throw new RuntimeException('修改后的 AppServiceProvider 语法校验返回空结果。');
            }
        } catch (ParseError $e) {
            throw new RuntimeException(
                "修改后的 AppServiceProvider 语法校验失败，未写入文件：{$e->getMessage()}"
            );
        }

        $backupPath = null;

        if (! $dryRun) {
            $backupPath = $path.'.overutil.bak';

            if (! @copy($path, $backupPath)) {
                throw new RuntimeException("无法创建 AppServiceProvider 备份：{$backupPath}");
            }

            $this->atomicWrite($path, $newCode);
        }

        return new AppServiceProviderPatchResult(
            'added',
            $backupPath,
            $addedBootMethod,
        );
    }

    /**
     * @param  array<int, array{id: int|string, text: string, start: int, end: int}>  $tokens
     * @param  array{open: int, close: int}  $boot
     */
    private function insertIntoBoot(string $code, array $tokens, array $boot, string $eol): string
    {
        $closeBraceStart = $tokens[$boot['close']]['start'];
        $closeLineStart = $this->lineStart($code, $closeBraceStart);
        $closeLinePrefix = substr($code, $closeLineStart, $closeBraceStart - $closeLineStart);
        $closeOnOwnLine = trim($closeLinePrefix) === '';
        $lineIndent = $this->leadingWhitespace(substr($code, $closeLineStart));
        $bodyIndent = $lineIndent.'    ';

        if ($closeOnOwnLine) {
            $before = substr($code, 0, $closeLineStart);
            $leadingBlankLine = str_ends_with($before, $eol.$eol) ? '' : $eol;

            return substr_replace(
                $code,
                $leadingBlankLine.$this->block($bodyIndent, $eol).$eol,
                $closeLineStart,
                0
            );
        }

        // Handles compact methods such as `public function boot(): void {}`.
        return substr_replace(
            $code,
            $eol.$this->block($bodyIndent, $eol).$eol.$lineIndent,
            $closeBraceStart,
            0
        );
    }

    /**
     * @param  array<int, array{id: int|string, text: string, start: int, end: int}>  $tokens
     */
    private function appendBootMethod(string $code, array $tokens, string $eol): string
    {
        $class = $this->findClassBody($tokens);

        if ($class === null) {
            throw new RuntimeException('无法定位 AppServiceProvider 类，未写入文件。');
        }

        $closeBraceStart = $tokens[$class['close']]['start'];
        $closeLineStart = $this->lineStart($code, $closeBraceStart);
        $classIndent = $this->leadingWhitespace(substr($code, $closeLineStart));
        $methodIndent = $classIndent.'    ';
        $bodyIndent = $methodIndent.'    ';

        $method = implode($eol, [
            $methodIndent.'public function boot(): void',
            $methodIndent.'{',
            $this->block($bodyIndent, $eol),
            $methodIndent.'}',
        ]);

        $before = substr($code, 0, $closeLineStart);
        $leadingBlankLine = str_ends_with($before, $eol.$eol) ? '' : $eol;

        return substr_replace(
            $code,
            $leadingBlankLine.$method.$eol,
            $closeLineStart,
            0
        );
    }

    /**
     * @param  array<int, array{id: int|string, text: string, start: int, end: int}>  $tokens
     * @return array{open: int, close: int}|null
     */
    private function findBootMethod(array $tokens): ?array
    {
        foreach ($tokens as $index => $token) {
            if ($token['id'] !== T_FUNCTION) {
                continue;
            }

            $nameIndex = $this->nextSignificant($tokens, $index);

            if ($nameIndex === null || $tokens[$nameIndex]['id'] !== T_STRING) {
                continue;
            }

            if (strtolower($tokens[$nameIndex]['text']) !== 'boot') {
                continue;
            }

            $openParen = $this->nextSignificant($tokens, $nameIndex);

            if ($openParen === null || $tokens[$openParen]['text'] !== '(') {
                continue;
            }

            $closeParen = $this->findMatchingBracket($tokens, $openParen, '(', ')');

            if ($closeParen === null) {
                continue;
            }

            $openBrace = null;

            for ($i = $closeParen + 1; $i < count($tokens); $i++) {
                if ($tokens[$i]['text'] === '{') {
                    $openBrace = $i;
                    break;
                }
            }

            if ($openBrace === null) {
                continue;
            }

            $closeBrace = $this->findMatchingBracket($tokens, $openBrace, '{', '}');

            if ($closeBrace === null) {
                continue;
            }

            return ['open' => $openBrace, 'close' => $closeBrace];
        }

        return null;
    }

    /**
     * @param  array<int, array{id: int|string, text: string, start: int, end: int}>  $tokens
     * @return array{open: int, close: int}|null
     */
    private function findClassBody(array $tokens): ?array
    {
        foreach ($tokens as $index => $token) {
            if ($token['id'] !== T_CLASS) {
                continue;
            }

            $nameIndex = $this->nextSignificant($tokens, $index);

            if ($nameIndex === null || $tokens[$nameIndex]['id'] !== T_STRING) {
                continue;
            }

            for ($i = $nameIndex + 1; $i < count($tokens); $i++) {
                if ($tokens[$i]['text'] !== '{') {
                    continue;
                }

                $close = $this->findMatchingBracket($tokens, $i, '{', '}');

                if ($close !== null) {
                    return ['open' => $i, 'close' => $close];
                }

                break;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{id: int|string, text: string, start: int, end: int}>  $tokens
     */
    private function findMatchingBracket(array $tokens, int $openIndex, string $open, string $close): ?int
    {
        $depth = 0;

        for ($i = $openIndex; $i < count($tokens); $i++) {
            if ($tokens[$i]['text'] === $open) {
                $depth++;
            } elseif ($tokens[$i]['text'] === $close) {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function block(string $indent, string $eol): string
    {
        return implode($eol, array_map(
            static fn (string $line): string => $indent.$line,
            self::BLOCK_LINES
        ));
    }

    private function lineStart(string $code, int $offset): int
    {
        $position = strrpos(substr($code, 0, $offset), "\n");

        return $position === false ? 0 : $position + 1;
    }

    private function leadingWhitespace(string $value): string
    {
        preg_match('/^[ \t]*/', $value, $matches);

        return $matches[0] ?? '';
    }

    /**
     * @param  array<int, array{id: int|string, text: string, start: int, end: int}>  $tokens
     */
    private function nextSignificant(array $tokens, int $index): ?int
    {
        for ($i = $index + 1; $i < count($tokens); $i++) {
            if (! in_array($tokens[$i]['id'], self::IGNORED_TOKENS, true)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{id: int|string, text: string, start: int, end: int}>
     */
    private function tokenize(string $code): array
    {
        try {
            $rawTokens = token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $e) {
            throw new RuntimeException("AppServiceProvider 语法错误，无法解析：{$e->getMessage()}");
        }

        $tokens = [];
        $offset = 0;

        foreach ($rawTokens as $rawToken) {
            $text = is_array($rawToken) ? $rawToken[1] : $rawToken;
            $id = is_array($rawToken) ? $rawToken[0] : $rawToken;

            $tokens[] = [
                'id' => $id,
                'text' => $text,
                'start' => $offset,
                'end' => $offset + strlen($text),
            ];

            $offset += strlen($text);
        }

        return $tokens;
    }

    private function atomicWrite(string $path, string $contents): void
    {
        $temporary = tempnam(dirname($path), '.app-service-provider-');

        if ($temporary === false) {
            throw new RuntimeException("无法创建临时文件：{$path}");
        }

        $permissions = fileperms($path);

        if (file_put_contents($temporary, $contents) === false) {
            @unlink($temporary);

            throw new RuntimeException("无法写入临时文件：{$path}");
        }

        if ($permissions !== false) {
            @chmod($temporary, $permissions & 0777);
        }

        if (! rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException("无法替换 AppServiceProvider：{$path}");
        }
    }
}
