<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogHttpOutRequest
{
    protected array $dontReport = [

    ];

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
    public function handle(RequestSending $event)
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

            Log::channel("http_out")->info('HTTP Request Sent', [
                'request_id' => $requestId ? Arr::first($requestId) : "",
                'url' => $event->request->url(),
                'method' => $event->request->method(),
                'headers' => $event->request->headers(),
                'body' => $event->request->body(),
                'data' => $event->request->data(),
            ]);
        }
    }
}
