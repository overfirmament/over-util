<?php

namespace Overfirmament\OverUtils\Console\Support;

final readonly class AppServiceProviderPatchResult
{
    /**
     * @param  'added'|'skipped'  $status
     */
    public function __construct(
        public string $status,
        public ?string $backupPath = null,
        public bool $addedBootMethod = false,
    ) {}

    public function hasChanges(): bool
    {
        return $this->status === 'added';
    }
}
