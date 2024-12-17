<?php

namespace App\Listeners;

use App\Utils\HelperUtil;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogHttpOutResponse
{
    protected array $dontReport = [];
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ResponseReceived $event)
    {
        $logConfig = config("over_util.http_util.log");
        $enable = $logConfig["enable"] ?? false;
        if (!$enable) {
            return;
        }

        $dontReport = $logConfig["dont_report"] ?? [];
        $patterns = array_merge($this->dontReport, $dontReport);
        $url = Str::after($event->request->url(), "//");

        if (!collect($patterns)->contains(fn ($pattern) => Str::is($pattern, $url))) {
            $requestId = $event->request->header("X-REQUEST-ID");

            Log::channel("http_out")->info('HTTP Response Received', [
                'request_id'    => $requestId ? Arr::first($requestId) : "",
                'status'        => $event->response->status(),
                'method'        => $event->request->method(),
                'url'           => $event->request->url(),
                'body'          => $event->request->isJson() ? HelperUtil::autoJsonDecode($event->response->body()) : $event->response->body(),
                'transfer_time' => $event->response->transferStats->getTransferTime(),
            ]);
        }
    }
}
