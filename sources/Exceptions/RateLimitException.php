<?php

namespace IPS\discord\Exceptions;

use Exception as BaseException;
use Illuminate\Support\HigherOrderTapProxy;

class RateLimitException extends BaseException
{
    protected ?array $data = null;

    public static function withData(array $data): static|HigherOrderTapProxy
    {
        return tap(new static, function (RateLimitException $exception) use ($data) {
            $exception->setData($data);
        });
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }
}
