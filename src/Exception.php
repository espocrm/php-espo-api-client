<?php
/* (c) EspoCRM */

namespace Espo\ApiClient;

class Exception extends \Exception
{
    private ?string $body;

    public function withBody(?string $body): self
    {
        $obj = clone $this;
        $obj->body = $body;

        return $obj;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }
}
