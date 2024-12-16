<?php

namespace App\Listeners;

use App\Utils\HelperUtil;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Queue\InteractsWithQueue;
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
        $patterns = array_merge($this->dontReport, config("over_util.http_util.log.dont_report", []));
        $url      = Str::after($event->request->url(), "//");

        if (!collect($patterns)->contains(fn($pattern) => Str::is($pattern, $url))) {
            Log::channel("http_out")->info('HTTP Response Received', [
                'request_id'    => $event->request->header("X-REQUEST-ID") ?? "",
                'url'           => $event->request->url(),
                'status'        => $event->response->status(),
                'body'          => $event->request->isJson() ? HelperUtil::autoJsonDecode($event->response->body()) : $event->response->body(),
                'cookies'       => $event->response->cookies()->toArray(),
                'transfer_time' => $event->response->transferStats->getTransferTime(),
            ]);
        }
    }
}
