<?php

namespace Overfirmament\OverUtils\ToolBox;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;


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
