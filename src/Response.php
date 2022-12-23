<?php
/* (c) EspoCRM */

namespace Espo\ApiClient;

class Response
{
    public function __construct(
        private int $code,
        private ?string $contentType,
        private string $headersPart,
        private string $bodyPart
    ) {}

    public function getCode(): int
    {
        return $this->code;
    }

    public function getHeadersPart(): string
    {
        return $this->headersPart;
    }

    public function getBodyPart(): string
    {
        return $this->bodyPart;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    public function getParsedBody(): mixed
    {
        if ($this->getContentType() === 'application/json') {
            return json_decode($this->getBodyPart());
        }

        return null;
    }
}
