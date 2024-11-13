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

    public function setName(mixed $name): HttpRequestBean
    {
        $this->name = $name;
        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): HttpRequestBean
    {
        $this->method = $method;
        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): HttpRequestBean
    {
        $this->url = $url;
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): HttpRequestBean
    {
        $this->headers = $headers;
        return $this;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function setQuery(array $query): HttpRequestBean
    {
        $this->query = $query;
        return $this;
    }

    public function getJson(): array
    {
        return $this->json;
    }

    public function setJson(array $json): HttpRequestBean
    {
        $this->json = $json;
        return $this;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function setBody(array $body): HttpRequestBean
    {
        $this->body = $body;
        return $this;
    }
}