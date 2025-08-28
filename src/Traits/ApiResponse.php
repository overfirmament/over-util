<?php

namespace Overfirmament\OverUtils\Traits;


use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Overfirmament\OverUtils\ToolBox\HelperUtil;
use Overfirmament\OverUtils\Pojo\POJOInterface;
use Symfony\Component\HttpFoundation\Response as FoundationResponse;

trait ApiResponse
{
    protected int $statusCode = FoundationResponse::HTTP_OK;

    protected int $httpCode = FoundationResponse::HTTP_OK;


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
        return response()->json($data)->withHeaders($headers);
    }

    /**
     * @param $status
     * @param  array  $data
     * @param $code
     *
     * @return JsonResponse
     */
    public function status($status, array $data, $code = null): JsonResponse
    {
        if ($code) {
            $this->setStatusCode($code);
        }
        $status = [
            'status' => $status,
            'code'   => $this->statusCode,
        ];

        $data = array_merge($status, $data);

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
            'messages' => $message,
        ]);
    }

    /**
     * @param  array|POJOInterface  $data
     * @param  string  $status
     *
     * @return JsonResponse
     */
    public function success(array|POJOInterface $data, string $status = "success"): JsonResponse
    {
        if ($data instanceof POJOInterface) {
            $data = $data->toArray();
        }
        return $this->status($status, compact('data'));
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
    public function notFond(string $message = 'Not Fond!'): JsonResponse
    {
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
        return $this->error($enum->getMessage(), $enum->getCode());
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

    public function returnSvg($svgStr): Response|ResponseFactory
    {
        return response($svgStr)->header('Content-type', 'image/svg+xml');
    }
}
