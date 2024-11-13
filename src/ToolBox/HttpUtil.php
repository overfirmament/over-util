<?php

namespace Overfirmament\OverUtils\ToolBox;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Overfirmament\OverUtils\Pojo\Bean\HttpRequestBean;


class HttpUtil
{
    protected static $instance;
    private Client $client;

    protected array $dotRepost = [

    ];


    protected array $originOptions = [
        "timeout" => 10,
    ];

    private function __construct()
    {
        $this->client = new Client();
        $this->dotRepost = array_merge($this->dotRepost, config("http.dot_repost"));
    }

    public static function getInstance(): HttpUtil
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    public function setTimeout(float $timeout): HttpUtil
    {
        $this->originOptions["timeout"] = $timeout;
        return $this;
    }

    /**
     * @param  string  $url
     * @param  array  $options
     *
     * @return mixed
     * @throws GuzzleException
     */
    public function get(string $url, array $options = []): mixed
    {
        return $this->request("GET", $url, $options);
    }

    /**
     * @param $url
     * @param  array  $options
     *
     * @return mixed
     * @throws GuzzleException
     */
    public function post($url, array $options = []): mixed
    {
        return $this->request("POST", $url, $options);
    }


    /**
     * @param  string  $method
     * @param  string  $url
     * @param  array  $options
     *
     * @return mixed
     * @throws GuzzleException
     */
    public function request(string $method = "GET", string $url, array $options = []): mixed
    {
        $options = array_merge($this->originOptions, $options);
        $response = $this->client->request($method, $url, $options);
        $result = HelperUtil::autoJsonDecode($response->getBody()->getContents());
        $this->log($url, $options, $result, $response->getStatusCode(), $method);

        return $result;
    }


    /**
     * 并发请求 get
     *
     * @param  array<HttpRequestBean>  $request
     *
     * @return array
     */
    public function getAsync(array $request): array
    {
        $promises = [];
        for ($i = 0; $i < count($request); $i++) {
            $bean = $request[$i];

            $promises[$bean->getName() ?: $i] = $this->client->getAsync(
                $bean->getUrl(),
                [
                    "headers" => $bean->getHeaders(),
                    "query" => $bean->getQuery()
                ]
            );
        }

        return Utils::settle($promises)->wait();
    }


    /**
     * @param  array<HttpRequestBean>  $request
     *
     * @return array
     */
    public function postAsync(array $request): array
    {
        $promises = [];
        for ($i = 0; $i < count($request); $i++) {
            $bean = $request[$i];

            $promises[$bean->getName() ?: $i] = $this->client->postAsync(
                $bean->getUrl(),
                [
                    "headers" => $bean->getHeaders(),
                    "json" => $bean->getJson()
                ]
            );
        }

        return Utils::settle($promises)->wait();
    }


    /**
     * @param  array<HttpRequestBean>  $request
     *
     * @return array
     */
    public function async(array $request): array
    {
        $promises = [];
        for ($i = 0; $i < count($request); $i++) {
            $bean = $request[$i];

            $options = $bean->getMethod() == "GET" ? [
                "headers" => $bean->getHeaders(),
                "query" => $bean->getQuery()
            ] : [
                "headers" => $bean->getHeaders(),
                "json" => $bean->getJson()
            ];
            $promises[$bean->getName() ?: $i] = $this->client->requestAsync(
                $bean->getMethod(),
                $bean->getUrl(),
                $options
            );
        }

        return Utils::settle($promises)->wait();
    }


    private function log($url, $options, ?array $response, int $httpCode, $method = "GET"): void
    {
        if (in_array($url, $this->dotRepost)) {
            return ;
        }

        $log = [
            "request_id" => request()->request_id ?? "",
            "method" => $method,
            "request" => [
                "url" => $url,
                "options" => $options,
            ],
            "reponse" => [
                "status" => $httpCode,
                "contents" => $response
            ],
        ];

        Log::channel('http_out')->info('http request out', $log);
    }


    // 其他网络请求方法，例如 put、delete 等

    // 私有化 clone 方法，防止被复制
    private function __clone() { }
}
