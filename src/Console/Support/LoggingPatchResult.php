<?php

namespace Overfirmament\OverUtils\Console\Support;

final readonly class LoggingPatchResult
{
    /**
     * @param  array<int, string>  $added
     * @param  array<int, string>  $replaced
     * @param  array<int, string>  $skipped
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public array $added = [],
        public array $replaced = [],
        public array $skipped = [],
        public array $warnings = [],
        public ?string $backupPath = null,
        public bool $changed = false,
    ) {}

    public function hasChanges(): bool
    {
        return $this->changed;
    }
}
