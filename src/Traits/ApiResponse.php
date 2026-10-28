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
     */
    public function respond($data, array $headers = ['Content-Type' => 'application/json; charset=utf-8']): JsonResponse
    {
        if (is_array($data)) {
            $traceId = (string) HelperUtil::getContextTraceId();

            if ($traceId !== '') {
                $data[TraceContext::output()] = $traceId;
            }
        }

        if ($this->isJsonP) {
            return response()->jsonp($this->jsonPCallback, $data, $this->httpCode, $headers);
        }

        return response()->json($data, $this->httpCode, $headers);
    }

    /**
     * @param  mixed  $status
     * @param  array<string, mixed>  $data
     */
    public function status($status, array $data, ?int $code = null): JsonResponse
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

        return $this->respond(array_merge($dataBag, $data));
    }

    /**
     * @param  mixed  $message
     */
    public function message($message, string $status = 'success'): JsonResponse
    {
        return $this->status($status, [
            $this->messageField => $message,
            'data' => [],
        ]);
    }

    /**
     * @param  array<mixed>|Data|null  $data
     */
    public function success(array|Data|null $data = [], string $status = 'success'): JsonResponse
    {
        if ($data instanceof Data) {
            $data = $data->toArray();
        }

        return $this->status($status, array_merge(
            ['data' => $data ?? []],
            [$this->messageField => '']
        ));
    }

    /**
     * @param  int|DateTimeInterface|DateInterval|null  $expiresIn  seconds, a date, or a date interval
     */
    public function respondWithToken(
        string $token,
        string $tokenType = 'Bearer',
        int|DateTimeInterface|DateInterval|null $expiresIn = null,
        ?string $guard = null
    ): JsonResponse {
        return $this->status('logged', ['data' => [
            'access_token' => $token,
            'token_type' => $tokenType,
            'expires_in' => TokenExpiration::resolve($expiresIn, $guard),
        ]]);
    }

    /**
     * @param  array<mixed>|Data  $data
     */
    public function jsonp(array|Data $data, string $callback = 'jsonpCallback', string $message = 'success'): JsonResponse
    {
        if ($data instanceof Data) {
            $data = $data->toArray();
        }

        return $this->useJsonP($callback)->status('success', array_merge(
            $data,
            [$this->messageField => $message]
        ));
    }

    /**
     * @param  array<mixed>|Data  $data
     */
    public function listData(array|Data $data): JsonResponse
    {
        $data = $data instanceof Data ? $data->toArray() : $data;

        return $this->success(['list' => $data]);
    }

    /**
     * @param  mixed  $data
     */
    public function created($data, string $status = 'created'): JsonResponse
    {
        return $this->setHttpCode(FoundationResponse::HTTP_CREATED)
            ->status($status, compact('data'));
    }

    public function deleted(string $status = 'deleted'): JsonResponse|Response
    {
        $this->setHttpCode(FoundationResponse::HTTP_NO_CONTENT);

        return response()->noContent(FoundationResponse::HTTP_NO_CONTENT);
    }

    /**
     * @param  mixed  $message
     */
    public function failed(
        $message,
        int $code = FoundationResponse::HTTP_BAD_REQUEST,
        string $status = 'error',
        ?int $httpCode = null
    ): JsonResponse {
        $httpCode ??= $code >= 100 && $code <= 599
            ? $code
            : FoundationResponse::HTTP_BAD_REQUEST;

        return $this->setStatusCode($code)
            ->setHttpCode($httpCode)
            ->message($message, $status);
    }

    public function notFond(string $message = 'Not Fond!'): JsonResponse
    {
        return $this->failed($message, FoundationResponse::HTTP_NOT_FOUND);
    }

    public function notFound(string $message = 'Not Found!'): JsonResponse
    {
        return $this->notFond($message);
    }

    public function error(string $message = 'error', int $code = FoundationResponse::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return $this->failed($message, $code);
    }

    public function errorWithEnum(\BackedEnum $enum): JsonResponse
    {
        $message = method_exists($enum, 'message')
            ? (string) $enum->message()
            : (string) $enum->value;

        $code = is_int($enum->value)
            ? $enum->value
            : FoundationResponse::HTTP_BAD_REQUEST;

        return $this->error($message, $code);
    }

    public function internalError(string $message = 'Internal Error!'): JsonResponse
    {
        return $this->failed($message, FoundationResponse::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @param  mixed  $svgStr
     */
    public function returnSvg($svgStr): Response
    {
        return response($svgStr)->header('Content-type', 'image/svg+xml');
    }
}
