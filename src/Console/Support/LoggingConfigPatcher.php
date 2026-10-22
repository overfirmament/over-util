<?php

namespace Overfirmament\OverUtils\Console\Support;

use ParseError;
use RuntimeException;

/**
 * Safely inserts or replaces entries in the `channels` array of a Laravel
 * `config/logging.php` file without executing or rewriting the whole file.
 */
final class LoggingConfigPatcher
{
    /** @var array<int, int|string> */
    private const IGNORED_TOKENS = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    /**
     * @param  array<string, string>  $channels  channel name => PHP source snippet
     */
    public function patch(
        string $path,
        array $channels,
        bool $force = false,
        bool $dryRun = false
    ): LoggingPatchResult {
        if (! is_file($path)) {
            throw new RuntimeException("配置文件不存在：{$path}");
        }

        if ($channels === []) {
            return new LoggingPatchResult;
        }

        $code = file_get_contents($path);

        if ($code === false) {
            throw new RuntimeException("无法读取配置文件：{$path}");
        }

        $eol = str_contains($code, "\r\n") ? "\r\n" : "\n";
        $layout = $this->findChannelsArray($this->tokenize($code), $code);

        $added = [];
        $replaced = [];
        $skipped = [];
        $warnings = [];
        $edits = [];
        $missingSnippets = [];

        foreach ($channels as $name => $snippet) {
            if (! isset($layout['entries'][$name])) {
                $added[] = $name;
                $missingSnippets[$name] = $snippet;

                continue;
            }

            if (! $force) {
                $skipped[] = $name;

                continue;
            }

            $entry = $layout['entries'][$name];

            if (! $entry['isArray']) {
                $skipped[] = $name;
                $warnings[] = "channel [{$name}] 的值不是数组字面量，已跳过覆盖。";

                continue;
            }

            $replaced[] = $name;
            $edits[] = [
                'offset' => $entry['keyStart'],
                'end' => $entry['valueEnd'],
                'text' => $this->replacementSnippet($snippet, $layout['entryIndent'], $eol),
            ];
        }

        if ($missingSnippets !== []) {
            $indented = [];

            foreach ($missingSnippets as $snippet) {
                $indented[] = $this->indentSnippet($snippet, $layout['entryIndent'], $eol);
            }

            $snippetText = implode($eol, $indented);

            if ($layout['closeOnOwnLine']) {
                $insertOffset = $layout['closeLineStart'];
                $insertText = $snippetText.$eol;
            } else {
                $insertOffset = $layout['closeOffset'];
                $insertText = $eol.$snippetText.$eol.$layout['closeIndent'];
            }

            if ($layout['needComma']) {
                $edits[] = [
                    'offset' => $layout['lastSignificantEnd'],
                    'end' => $layout['lastSignificantEnd'],
                    'text' => ',',
                ];
            }

            $edits[] = [
                'offset' => $insertOffset,
                'end' => $insertOffset,
                'text' => $insertText,
            ];
        }

        $newCode = $this->applyEdits($code, $edits);

        if ($newCode === $code) {
            return new LoggingPatchResult(
                added: [],
                replaced: [],
                skipped: array_values(array_unique(array_merge($skipped, array_keys($channels)))),
                warnings: $warnings,
            );
        }

        try {
            $validatedTokens = token_get_all($newCode, TOKEN_PARSE);

            if ($validatedTokens === []) {
                throw new RuntimeException('修改后的 logging.php 语法校验返回空结果。');
            }
        } catch (ParseError $e) {
            throw new RuntimeException(
                "修改后的 logging.php 语法校验失败，未写入文件：{$e->getMessage()}"
            );
        }

        $backupPath = null;

        if (! $dryRun) {
            $backupPath = $path.'.overutil.bak';

            if (! @copy($path, $backupPath)) {
                throw new RuntimeException("无法创建配置备份：{$backupPath}");
            }

            $this->atomicWrite($path, $newCode);
        }

        return new LoggingPatchResult(
            added: $added,
            replaced: $replaced,
            skipped: $skipped,
            warnings: $warnings,
            backupPath: $backupPath,
            changed: true,
        );
    }

    /**
     * @param  array<int, array{id: int|string, text: string, start: int, end: int}>  $tokens
     * @return array{
     *     entries: array<string, array{keyStart: int, valueStart: int, valueEnd: int, isArray: bool}>,
     *     closeOffset: int,
     *     closeLineStart: int,
     *     closeOnOwnLine: bool,
     *     closeIndent: string,
     *     entryIndent: string,
     *     needComma: bool,
     *     lastSignificantEnd: int
     * }
     */
    private function findChannelsArray(array $tokens, string $code): array
    {
        $openIndex = null;

        foreach ($tokens as $index => $token) {
            if ($token['id'] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            if ($this->stringValue($token['text']) !== 'channels') {
                continue;
            }

            $arrowIndex = $this->nextSignificant($tokens, $index);

            if ($arrowIndex === null || $tokens[$arrowIndex]['id'] !== T_DOUBLE_ARROW) {
                continue;
            }

            $valueIndex = $this->nextSignificant($tokens, $arrowIndex);

            if ($valueIndex === null) {
                continue;
            }

            if ($tokens[$valueIndex]['text'] === '[') {
                $openIndex = $valueIndex;
                break;
            }

            if ($tokens[$valueIndex]['id'] === T_ARRAY) {
                $parenIndex = $this->nextSignificant($tokens, $valueIndex);

                if ($parenIndex !== null && $tokens[$parenIndex]['text'] === '(') {
                    $openIndex = $parenIndex;
                    break;
                }
            }
        }

        if ($openIndex === null) {
            throw new RuntimeException('在 logging.php 中未找到 channels 数组，无法自动写入日志配置。');
        }

        $closeIndex = $this->findMatchingBracket($tokens, $openIndex);
        $entries = $this->findEntries($tokens, $openIndex, $closeIndex);

        $closeToken = $tokens[$closeIndex];
        $closeLineStart = strrpos(substr($code, 0, $closeToken['start']), "\n");
        $closeLineStart = $closeLineStart === false ? 0 : $closeLineStart + 1;
        $closePrefix = substr($code, $closeLineStart, $closeToken['start'] - $closeLineStart);
        $closeOnOwnLine = trim($closePrefix) === '';
        $closeIndent = $closeOnOwnLine
            ? $closePrefix
            : $this->leadingWhitespace($closePrefix);

        $entryIndent = null;

        if ($entries !== []) {
            $first = reset($entries);
            $keyLineStart = strrpos(substr($code, 0, $first['keyStart']), "\n");
            $keyLineStart = $keyLineStart === false ? 0 : $keyLineStart + 1;
            $keyPrefix = substr($code, $keyLineStart, $first['keyStart'] - $keyLineStart);

            if (trim($keyPrefix) === '') {
                $entryIndent = $keyPrefix;
            }
        }

        if ($entryIndent === null) {
            $entryIndent = $closeIndent.(str_contains($closeIndent, "\t") ? "\t" : '    ');
        }

        $lastIndex = $this->previousSignificant($tokens, $closeIndex);
        $lastToken = $lastIndex === null ? null : $tokens[$lastIndex];
        $lastText = $lastToken['text'] ?? '';
        $needComma = $entries !== [] && $lastText !== ',';

        return [
            'entries' => $entries,
            'closeOffset' => $closeToken['start'],
            'closeLineStart' => $closeLineStart,
            'closeOnOwnLine' => $closeOnOwnLine,
            'closeIndent' => $closeIndent,
            'entryIndent' => $entryIndent,
            'needComma' => $needComma,
            'lastSignificantEnd' => $lastToken['end'] ?? $closeToken['start'],
        ];
    }

    /**
     * @param  array<int, array{id: int|string, text: string, start: int, end: int}>  $tokens
     */
    private function findMatchingBracket(array $tokens, int $openIndex): int
    {
        $pairs = ['[' => ']', '(' => ')', '{' => '}'];
        $opening = $tokens[$openIndex]['text'];

        if (! isset($pairs[$opening])) {
            throw new RuntimeException('channels 配置结构无法识别。');
        }

        $stack = [$pairs[$opening]];

        for ($i = $openIndex + 1; $i < count($tokens); $i++) {
            $text = $tokens[$i]['text'];

            if (isset($pairs[$text])) {
                $stack[] = $pairs[$text];

                continue;
            }

            if (in_array($text, [']', ')', '}'], true)) {
                $expected = array_pop($stack);

                if ($expected !== $text) {
                    throw new RuntimeException('channels 数组括号不匹配，已中止修改。');
                }

                if ($stack === []) {
                    return $i;
                }
            }
        }

        throw new RuntimeException('channels 数组未正常结束，已中止修改。');
    }

    /**
     * @param  array<int, array{id: int|string, text: string, start: int, end: int}>  $tokens
     * @return array<string, array{keyStart: int, valueStart: int, valueEnd: int, isArray: bool}>
     */
    private function findEntries(array $tokens, int $openIndex, int $closeIndex): array
    {
        $entries = [];
        $depth = 0;
        $i = $openIndex + 1;

        while ($i < $closeIndex) {
            $token = $tokens[$i];
            $text = $token['text'];

            if (in_array($text, ['[', '(', '{'], true)) {
                $depth++;
                $i++;

                continue;
            }

            if (in_array($text, [']', ')', '}'], true)) {
                $depth--;
                $i++;

                continue;
            }

            if ($depth === 0 && $token['id'] === T_CONSTANT_ENCAPSED_STRING) {
                $arrowIndex = $this->nextSignificant($tokens, $i);

                if ($arrowIndex !== null && $tokens[$arrowIndex]['id'] === T_DOUBLE_ARROW) {
                    $valueIndex = $this->nextSignificant($tokens, $arrowIndex);

                    if ($valueIndex !== null) {
                        $valueEndIndex = $this->findValueEnd($tokens, $valueIndex, $closeIndex);
                        $firstValueText = $tokens[$valueIndex]['text'];
                        $isArray = $firstValueText === '['
                            || $tokens[$valueIndex]['id'] === T_ARRAY;

                        $entries[$this->stringValue($token['text'])] = [
                            'keyStart' => $token['start'],
                            'valueStart' => $tokens[$valueIndex]['start'],
                            'valueEnd' => $tokens[$valueEndIndex]['end'],
                            'isArray' => $isArray,
                        ];

                        $i = $valueEndIndex + 1;

                        continue;
                    }
                }
            }

            $i++;
        }

        return $entries;
    }

    /**
     * @param  array<int, array{id: int|string, text: string, start: int, end: int}>  $tokens
     */
    private function findValueEnd(array $tokens, int $valueIndex, int $closeIndex): int
    {
        $depth = 0;
        $lastSignificant = $valueIndex;

        for ($i = $valueIndex; $i < $closeIndex; $i++) {
            $token = $tokens[$i];
            $text = $token['text'];

            if (in_array($text, ['[', '(', '{'], true)) {
                $depth++;
                $lastSignificant = $i;

                continue;
            }

            if (in_array($text, [']', ')', '}'], true)) {
                $depth--;
                $lastSignificant = $i;

                if ($depth === 0) {
                    $next = $this->nextSignificant($tokens, $i);

                    if ($next === null || $next >= $closeIndex || $tokens[$next]['text'] === ',') {
                        return $i;
                    }
                }

                continue;
            }

            if ($depth === 0 && $text === ',') {
                return $lastSignificant;
            }

            if (! $this->isIgnored($token)) {
                $lastSignificant = $i;
            }
        }

        return $lastSignificant;
    }

    /**
     * @param  array<int, array{id: int|string, text: string, start: int, end: int}>  $tokens
     */
    private function nextSignificant(array $tokens, int $index): ?int
    {
        $count = count($tokens);

        for ($i = $index + 1; $i < $count; $i++) {
            if (! $this->isIgnored($tokens[$i])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{id: int|string, text: string, start: int, end: int}>  $tokens
     */
    private function previousSignificant(array $tokens, int $index): ?int
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (! $this->isIgnored($tokens[$i])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  array{id: int|string, text: string, start: int, end: int}  $token
     */
    private function isIgnored(array $token): bool
    {
        return in_array($token['id'], self::IGNORED_TOKENS, true);
    }

    /**
     * @return array<int, array{id: int|string, text: string, start: int, end: int}>
     */
    private function tokenize(string $code): array
    {
        try {
            $rawTokens = token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $e) {
            throw new RuntimeException("logging.php 语法错误，无法解析：{$e->getMessage()}");
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

    private function stringValue(string $text): string
    {
        if (strlen($text) >= 2 && ($text[0] === "'" || $text[0] === '"')) {
            return stripcslashes(substr($text, 1, -1));
        }

        return $text;
    }

    private function leadingWhitespace(string $value): string
    {
        preg_match('/^[ \t]*/', $value, $matches);

        return $matches[0] ?? '';
    }

    /**
     * Build replacement text for an existing entry.
     *
     * The indentation before the original key is not part of the replacement
     * range, so the first line must stay unindented. Subsequent lines need the
     * original entry indentation plus the snippet's own relative indentation.
     * The trailing comma is kept outside the replacement so the original comma
     * (or the comma inserted by this patcher) is not duplicated.
     */
    private function replacementSnippet(string $snippet, string $indent, string $eol): string
    {
        $snippet = preg_replace('/,\s*$/', '', trim($snippet)) ?? trim($snippet);
        $lines = preg_split('/\R/', $snippet);

        if ($lines === false || $lines === []) {
            return '';
        }

        $first = array_shift($lines);

        $rest = array_map(
            static fn (string $line): string => $line === '' ? '' : $indent.$line,
            $lines
        );

        return implode($eol, array_merge([$first], $rest));
    }

    private function indentSnippet(string $snippet, string $indent, string $eol): string
    {
        $lines = preg_split('/\R/', trim($snippet));

        if ($lines === false) {
            return '';
        }

        return implode($eol, array_map(
            static fn (string $line): string => $line === '' ? '' : $indent.$line,
            $lines
        ));
    }

    /**
     * @param  array<int, array{offset: int, end: int, text: string}>  $edits
     */
    private function applyEdits(string $code, array $edits): string
    {
        usort($edits, static function (array $a, array $b): int {
            if ($a['offset'] !== $b['offset']) {
                return $b['offset'] <=> $a['offset'];
            }

            // A zero-length insertion at the same offset as a replacement
            // (for example, the comma after a replaced last entry) must be
            // applied first. Otherwise the replacement can shift the insertion
            // point into the middle of the new text.
            $aLength = $a['end'] - $a['offset'];
            $bLength = $b['end'] - $b['offset'];

            return $aLength <=> $bLength;
        });

        foreach ($edits as $edit) {
            $length = $edit['end'] - $edit['offset'];
            $code = substr_replace($code, $edit['text'], $edit['offset'], $length);
        }

        return $code;
    }

    private function atomicWrite(string $path, string $contents): void
    {
        $temporary = tempnam(dirname($path), '.logging-');

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

            throw new RuntimeException("无法替换配置文件：{$path}");
        }
    }
}
