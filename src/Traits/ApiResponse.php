<?php

namespace Overfirmament\OverUtils\Traits;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Spatie\LaravelData\Data;
use Symfony\Component\HttpFoundation\Response as FoundationResponse;

trait ApiResponse
{
    protected int $statusCode = FoundationResponse::HTTP_OK;

    protected int $httpCode = FoundationResponse::HTTP_OK;

    protected string $messageField = 'messages';

    protected bool $useTime = true;
    protected string $timeField = 'date';

    protected bool $isJsonP = false;
    protected string $jsonPCallback = 'jsonpCallback';


    /**
     * @return $this
     */
    public function useJsonP(string $callback = 'jsonpCallback'): static
    {
        $this->isJsonP = true;
        $this->jsonPCallback = $callback;

        return $this;
    }


    /**
     * @return $this
     */
    public function messageField(string $field = 'message'): static
    {
        $this->messageField = $field;

        return $this;
    }


    public function useTime(string $field = 'date'): static
    {
        $this->useTime = true;
        $this->timeField = $field;

        return $this;
    }


    /**
     * 获取状态码
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 获取 http 请求码
     *
     * @return int
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * 设置错误码
     *
     * @param $statusCode
     * @param  null  $httpCode
     *
     * @return $this
     */
    public function setStatusCode($statusCode, $httpCode = null): static
    {
        $this->statusCode = $statusCode;
        $this->httpCode   = $httpCode ?? $this->httpCode;

        return $this;
    }

    /**
     * @param $data
     * @param  array  $headers
     *
     * @return JsonResponse
     */
    public function respond($data, array $headers = ['Content-Type' => 'application/json; charset=utf-8']): JsonResponse
    {
        $data["request_id"] = request()->request_id;
        if ($this->isJsonP) {
            return response()->jsonp($this->jsonPCallback, $data);
        }
        return response()->json($data)->withHeaders($headers);
    }

    /**
     * @param $status
     * @param  array  $data
     * @param  null  $code
     *
     * @return JsonResponse
     */
    public function status($status, array $data, $code = null): JsonResponse
    {
        if ($code) {
            $this->setStatusCode($code);
        }
        $dataBag = [
            'status' => $status,
            'code'   => $this->statusCode,
        ];

        if ($this->useTime) {
            $dataBag[$this->timeField] = now()->getTimestamp();
        }

        $data = array_merge($dataBag, $data);

        return $this->respond($data);
    }


    /**
     * @param $message
     * @param  string  $status
     *
     * @return JsonResponse
     */
    public function message($message, string $status = "success"): JsonResponse
    {
        return $this->status($status, [
            $this->messageField => $message,
            "data"              => [],
        ]);
    }

    /**
     * @param  array|Data  $data
     * @param  string  $status
     *
     * @return JsonResponse
     */
    public function success(array|Data $data, string $status = "success"): JsonResponse
    {
        if ($data instanceof Data) {
            $data = $data->toArray();
        }
        return $this->status($status, array_merge(compact('data'), [$this->messageField => '']));
    }


    public function jsonp(array|Data $data, string $callback = 'jsonpCallback', string $message = "success"): JsonResponse
    {
        if ($data instanceof Data) {
            $data = $data->toArray();
        }

        return $this->useJsonP($callback)->messageField()->status("success", array_merge($data, [$this->messageField => $message]));
    }


    /**
     * @param  array|Data  $data
     *
     * @return JsonResponse
     */
    public function listData(array|Data $data): JsonResponse
    {
        $data = $data instanceof Data ? $data->toArray() : $data;

        return $this->success(['list' => $data]);
    }

    /**
     * 创建成功 201
     *
     * @param $data
     * @param  string  $status
     *
     * @return JsonResponse
     */
    public function created($data, string $status = "created"): JsonResponse
    {
        return $this->status($status, compact('data'), FoundationResponse::HTTP_CREATED);
    }


    /**
     * @param  string  $status
     *
     * @return JsonResponse
     */
    public function deleted(string $status = "deleted"): JsonResponse
    {
        return $this->status($status, [], FoundationResponse::HTTP_NO_CONTENT);
    }

    /**
     * 请求失败 400
     *
     * @param $message
     * @param  int  $code
     * @param  string  $status
     * @param  string|null  $httpCode
     *
     * @return JsonResponse
     */
    public function failed(
        $message,
        int $code = FoundationResponse::HTTP_BAD_REQUEST,
        string $status = 'error',
        string $httpCode = null
    ): JsonResponse {
        return $this->setStatusCode($code, $httpCode)->message($message, $status);
    }

    /**
     * 找不到任何资源 404
     *
     * @param  string  $message
     *
     * @return JsonResponse
     */
    public function notFond(string $message = 'Not Fond!'
    ): JsonResponse {
        return $this->failed($message, Foundationresponse::HTTP_NOT_FOUND);
    }

    /**
     * 验证错误 422
     *
     * @param  string  $message
     * @param  int  $code
     *
     * @return JsonResponse
     */
    public function error(string $message = 'error', int $code = Foundationresponse::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return $this->failed($message, $code);
    }


    public function errorWithEnum(\BackedEnum $enum): JsonResponse
    {
        return $this->error($enum->message(), $enum->value);
    }

    /**
     * 服务器错误 500
     *
     * @param  string  $message
     *
     * @return JsonResponse
     */
    public function internalError(string $message = "Internal Error!"): JsonResponse
    {
        return $this->failed($message, FoundationResponse::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @param $svgStr
     *
     * @return \Illuminate\Contracts\Foundation\Application|ResponseFactory|Application|Response
     */
    public function returnSvg($svgStr): Application|Response|\Illuminate\Contracts\Foundation\Application|ResponseFactory
    {
        return response($svgStr)->header('Content-type', 'image/svg+xml');
    }
}
