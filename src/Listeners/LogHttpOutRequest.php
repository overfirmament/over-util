<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Queue\InteractsWithQueue;
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
        $patterns = array_merge($this->dontReport, config("httputil.log.dont_report", []));
        $url = Str::after($event->request->url(), "//");

        if (!collect($patterns)->contains(fn ($pattern) => Str::is($pattern, $url))) {
            Log::build(config("httputil.log.channels.http_out"))->info('HTTP Request Sent', [
                'request_id' => $event->request->header("X-REQUEST-ID") ?? "",
                'url' => $event->request->url(),
                'method' => $event->request->method(),
                'headers' => $event->request->headers(),
                'body' => $event->request->body(),
                'data' => $event->request->data(),
            ]);
        }
    }
}
