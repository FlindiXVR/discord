<?php

namespace IPS\discord;

use Illuminate\Http\Request as BaseRequest;
use IPS\Patterns\Singleton;

/**
 * @mixin BaseRequest
 */
class Request extends Singleton
{
    protected BaseRequest $request;

    public function __construct()
    {
        $this->request = BaseRequest::createFromGlobals();
    }

    public function __call(string $name, array $arguments)
    {
        return $this->request->$name($arguments);
    }

    public function __get(mixed $key): mixed
    {
        return $this->request->$key;
    }

    public function __set(mixed $key, mixed $value): void
    {
        $this->request->$key = $value;
    }
}
