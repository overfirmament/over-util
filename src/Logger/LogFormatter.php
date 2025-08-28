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
        $requestId = (request()->request_id ?: ($record["context"]["request_id"] ?? $record["context"]["requestId"] ?? "")) ?: "Local Command Line";
        $record    = array_merge(["request_id" => $requestId], $record);
        if (is_array($record["context"])) {
            unset($record["context"]["request_id"], $record["context"]["requestId"]);
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
