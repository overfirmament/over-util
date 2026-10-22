<?php

namespace Overfirmament\OverUtils\Logger;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;
use Overfirmament\OverUtils\ToolBox\HelperUtil;
use Throwable;

class LogFormatter extends JsonFormatter
{
    public function __construct()
    {
        parent::__construct();

        $this->setDateFormat('Y-m-d H:i:s');
        $this->addJsonEncodeOption(JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function format(LogRecord $record): string
    {
        $record = $this->normalizeRecord($record);
        $record = $this->autoConvert2Utf8($record);

        $traceId = (string) HelperUtil::getContextTraceId();

        if ($traceId === '') {
            $traceId = (string) (
                data_get($record, ['context', 'trace_id'])
                ?? data_get($record, ['context', 'traceId'])
                ?? data_get($record, ['extra', 'trace_id'])
                ?? data_get($record, ['extra', 'traceId'])
                ?? ''
            );
        }

        if ($traceId === '') {
            $traceId = $this->requestTraceId();
        }

        if ($traceId !== '') {
            $record = array_merge(['trace_id' => $traceId], $record);

            if (isset($record['context']) && is_array($record['context'])) {
                unset($record['context']['traceId'], $record['context']['trace_id']);
            }

            if (isset($record['extra']) && is_array($record['extra'])) {
                unset($record['extra']['traceId'], $record['extra']['trace_id']);
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

    private function requestTraceId(): string
    {
        try {
            if (app()->runningInConsole()) {
                return '';
            }

            return (string) (request()->trace_id ?? request()->traceId ?? '');
        } catch (Throwable) {
            return '';
        }
    }
}
