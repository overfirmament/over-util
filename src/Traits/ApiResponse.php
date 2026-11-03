<?php

namespace Overfirmament\OverUtils\Traits;

use DateInterval;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Overfirmament\OverUtils\Support\TokenExpiration;
use Overfirmament\OverUtils\Support\TraceContext;
use Overfirmament\OverUtils\ToolBox\HelperUtil;
use Spatie\LaravelData\Data;
use Symfony\Component\HttpFoundation\Response as FoundationResponse;

trait ApiResponse
{
    protected int $statusCode = FoundationResponse::HTTP_OK;

    protected int $httpCode = FoundationResponse::HTTP_OK;

    protected string $messageField = 'message';

    protected bool $useTime = true;

    protected string $timeField = 'date';

    protected bool $isJsonP = false;

    protected string $jsonPCallback = 'jsonpCallback';

    public function useJsonP(string $callback = 'jsonpCallback'): static
    {
        $this->isJsonP = true;
        $this->jsonPCallback = $callback;

        return $this;
    }

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

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function setStatusCode(int $statusCode, ?int $httpCode = null): static
    {
        $this->statusCode = $statusCode;

        if ($httpCode !== null) {
            $this->httpCode = $httpCode;
        }

        return $this;
    }

    public function setHttpCode(int $httpCode): static
    {
        $this->httpCode = $httpCode;

        return $this;
    }

    /**
     * @param  mixed  $data
     * @param  array<string, string>  $headers
     * @param  int|null  $httpCode  overrides the instance HTTP status for this response only
     */
    public function respond(
        $data,
        array $headers = ['Content-Type' => 'application/json; charset=utf-8'],
        ?int $httpCode = null
    ): JsonResponse {
        $httpCode ??= $this->httpCode;

        if (is_array($data)) {
            $traceId = (string) HelperUtil::getContextTraceId();

            if ($traceId !== '') {
                $data[TraceContext::output()] = $traceId;
            }
        }

        if ($this->isJsonP) {
            return response()->jsonp($this->jsonPCallback, $data, $httpCode, $headers);
        }

        return response()->json($data, $httpCode, $headers);
    }

    /**
     * @param  mixed  $status
     * @param  array<string, mixed>  $data
     */
    public function status($status, array $data, ?int $code = null, ?int $httpCode = null): JsonResponse
    {
        if ($code !== null) {
            $this->setStatusCode($code);
        }

        $dataBag = [
            'status' => $status,
            'code' => $this->statusCode,
        ];

        if ($this->useTime) {
            $dataBag[$this->timeField] = now()->getTimestamp();
        }

        return $this->respond(array_merge($dataBag, $data), httpCode: $httpCode);
    }

    /**
     * @param  mixed  $message
     */
    public function message($message, string $status = 'success', ?int $httpCode = null): JsonResponse
    {
        return $this->status($status, [
            $this->messageField => $message,
            'data' => [],
        ], null, $httpCode);
    }

    public function success(
        array|Data|null $data = [],
        string $status = 'success',
        ?int $httpCode = null
    ): JsonResponse {
        if ($data instanceof Data) {
            $data = $data->toArray();
        }

        return $this->status($status, array_merge(
            ['data' => $data ?? []],
            [$this->messageField => '']
        ), null, $httpCode);
    }

    /**
     * @param  int|DateTimeInterface|DateInterval|null  $expiresIn  seconds, a date, or a date interval
     */
    public function respondWithToken(
        string $token,
        string $tokenType = 'Bearer',
        int|DateTimeInterface|DateInterval|null $expiresIn = null,
        ?string $guard = null,
        ?int $httpCode = null
    ): JsonResponse {
        return $this->status('logged', ['data' => [
            'access_token' => $token,
            'token_type' => $tokenType,
            'expires_in' => TokenExpiration::resolve($expiresIn, $guard),
        ]], null, $httpCode);
    }

    /**
     * @param  array<mixed>|Data  $data
     */
    public function jsonp(
        array|Data $data,
        string $callback = 'jsonpCallback',
        string $message = 'success',
        ?int $httpCode = null
    ): JsonResponse {
        if ($data instanceof Data) {
            $data = $data->toArray();
        }

        return $this->useJsonP($callback)->status('success', array_merge(
            $data,
            [$this->messageField => $message]
        ), null, $httpCode);
    }

    public function listData(array|Data $data, ?int $httpCode = null): JsonResponse
    {
        $data = $data instanceof Data ? $data->toArray() : $data;

        return $this->success(['list' => $data], httpCode: $httpCode);
    }

    /**
     * @param  mixed  $data
     */
    public function created(
        $data,
        string $status = 'created',
        ?int $httpCode = FoundationResponse::HTTP_CREATED
    ): JsonResponse {
        return $this->status($status, compact('data'), null, $httpCode);
    }

    public function deleted(
        string $status = 'deleted',
        ?int $httpCode = FoundationResponse::HTTP_NO_CONTENT
    ): JsonResponse|Response {
        return response()->noContent($httpCode ?? FoundationResponse::HTTP_NO_CONTENT);
    }

    /**
     * The business code is never used to derive the HTTP status. Pass
     * $httpCode, or call setHttpCode(), when a non-200 HTTP status is needed.
     *
     * @param  mixed  $message
     */
    public function failed(
        $message,
        int $code = FoundationResponse::HTTP_BAD_REQUEST,
        string $status = 'error',
        ?int $httpCode = null
    ): JsonResponse {
        return $this->setStatusCode($code)
            ->message($message, $status, $httpCode);
    }

    public function notFond(
        string $message = 'Not Fond!',
        ?int $httpCode = FoundationResponse::HTTP_NOT_FOUND
    ): JsonResponse {
        return $this->failed($message, FoundationResponse::HTTP_NOT_FOUND, 'error', $httpCode);
    }

    public function notFound(
        string $message = 'Not Found!',
        ?int $httpCode = FoundationResponse::HTTP_NOT_FOUND
    ): JsonResponse {
        return $this->notFond($message, $httpCode);
    }

    public function error(
        string $message = 'error',
        int $code = FoundationResponse::HTTP_UNPROCESSABLE_ENTITY,
        ?int $httpCode = FoundationResponse::HTTP_UNPROCESSABLE_ENTITY
    ): JsonResponse {
        return $this->failed($message, $code, 'error', $httpCode);
    }

    public function errorWithEnum(\BackedEnum $enum, ?int $httpCode = null): JsonResponse
    {
        $message = method_exists($enum, 'message')
            ? (string) $enum->message()
            : (string) $enum->value;

        $code = is_int($enum->value)
            ? $enum->value
            : FoundationResponse::HTTP_BAD_REQUEST;

        return $this->failed($message, $code, 'error', $httpCode);
    }

    public function internalError(
        string $message = 'Internal Error!',
        ?int $httpCode = FoundationResponse::HTTP_INTERNAL_SERVER_ERROR
    ): JsonResponse {
        return $this->failed($message, FoundationResponse::HTTP_INTERNAL_SERVER_ERROR, 'error', $httpCode);
    }

    /**
     * @param  mixed  $svgStr
     */
    public function returnSvg($svgStr): Response
    {
        return response($svgStr)->header('Content-type', 'image/svg+xml');
    }
}
