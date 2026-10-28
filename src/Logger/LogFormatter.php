<?php

namespace Overfirmament\OverUtils\Logger;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;
use Overfirmament\OverUtils\Support\TraceContext;

class LogFormatter extends JsonFormatter
{
    protected string $trackField = TraceContext::DEFAULT_FIELD;

    protected string $outputField = TraceContext::DEFAULT_OUTPUT;

    public function __construct()
    {
        parent::__construct();

        $this->trackField = TraceContext::field();
        $this->outputField = TraceContext::output();

        $this->setDateFormat('Y-m-d H:i:s');
        $this->addJsonEncodeOption(JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Override the field read from the log context/extra.
     *
     * @return $this
     */
    public function setTrackField(string $field): static
    {
        $this->trackField = $field;

        return $this;
    }

    /**
     * Override the field emitted at the top level of the log line.
     *
     * @return $this
     */
    public function setOutputField(string $field): static
    {
        $this->outputField = $field;

        return $this;
    }

    public function format(LogRecord $record): string
    {
        $record = $this->normalizeRecord($record);
        $record = $this->autoConvert2Utf8($record);

        $context = isset($record['context']) && is_array($record['context']) ? $record['context'] : [];
        $extra = isset($record['extra']) && is_array($record['extra']) ? $record['extra'] : [];

        $traceId = TraceContext::resolve($context, $extra, $this->trackField);

        if ($traceId !== '') {
            $record = array_merge([$this->outputField => $traceId], $record);

            foreach (TraceContext::candidates($this->trackField) as $key) {
                if (isset($record['context']) && is_array($record['context'])) {
                    unset($record['context'][$key]);
                }

                if (isset($record['extra']) && is_array($record['extra'])) {
                    unset($record['extra'][$key]);
                }
            }
        }

        $dateTime = (string) ($record['datetime'] ?? '');

        return '['.$dateTime.'] '.$this->toJson($record, true).($this->appendNewline ? "\n" : '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function autoConvert2Utf8(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->autoConvert2Utf8($value);

                continue;
            }

            if (is_object($value)) {
                $data[$key] = $this->autoConvert2Utf8((array) $value);

                continue;
            }

            if (! is_string($value) || $value === '' || mb_check_encoding($value, 'UTF-8')) {
                continue;
            }

            $converted = @iconv('GBK', 'UTF-8//IGNORE', $value);

            if ($converted !== false) {
                $data[$key] = $converted;
            }
        }

        return $data;
    }
}
