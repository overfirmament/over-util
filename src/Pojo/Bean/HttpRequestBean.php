<?php

namespace Overfirmament\OverUtils\Pojo\Bean;

class HttpRequestBean
{
    protected mixed $name = "";

    protected string $method = "";

    protected string $url = "";

    protected array $headers = [];

    protected array $query = [];

    protected array $json = [];

    protected array $body = [];

    public function getName(): mixed
    {
        return $this->name;
    }

    public function setName(mixed $name): void
    {
        $this->name = $name;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }


    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function setQuery(array $query): void
    {
        $this->query = $query;
    }

    public function getJson(): array
    {
        return $this->json;
    }

    public function setJson(array $json): void
    {
        $this->json = $json;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function setBody(array $body): void
    {
        $this->body = $body;
    }
}