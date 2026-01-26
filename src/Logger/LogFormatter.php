<?php

namespace Overfirmament\OverUtils\Logger;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;
use Overfirmament\OverUtils\ToolBox\HelperUtil;

class LogFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        $record    = $this->setDateFormat("Y-m-d H:i:s")->normalizeRecord($record);

        // 在 record 数组 的 context 和 extra 中 寻找 trace id 或 traceId 字段
        if(($traceId = (string) (request()->trace_id ?? request()->traceId))
            || ($traceId = (string) (data_get($record, ['context', 'trace_id']) ?? data_get($record, ['context', 'traceId'])))
            || ($traceId = (string) (data_get($record, ['extra', 'trace_id']) ?? data_get($record, ['extra', 'traceId'])))
        ) {
            $record    = array_merge(["trace_id" => $traceId], $record);
            if (is_array($record["context"])) {
                unset($record["context"]["traceId"], $record["context"]["trace_id"]);
            }
            if (is_array($record["extra"])) {
                unset($record["extra"]["traceId"], $record["extra"]["trace_id"]);
            }
        }

        $record   = $this->autoConvert2Utf8($record);
        $dateTime = $record["datetime"];
        return "[$dateTime] ".$this->toJson($record, true).($this->appendNewline ? "\n" : "");
    }


    public function autoConvert2Utf8(array $data): array
    {
        foreach ($data as $key => $value) {

            if (is_array($value)) {
                $data[$key] = $this->autoConvert2Utf8($value);
            } elseif (is_object($value)) {
                $data[$key] = $this->autoConvert2Utf8((array)$value);
            } else {
                if (!is_bool($value) && mb_detect_encoding($value, ["CP936", "UTF-8"]) == "CP936") {
                    $data[$key] = HelperUtil::convertGbk($value);
                }
            }
        }

        return $data;
    }
}