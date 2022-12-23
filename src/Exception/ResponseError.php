<?php
/* (c) EspoCRM */

namespace Espo\ApiClient\Exception;

use Espo\ApiClient\Response;

/**
 * Use getCode to obtain HTTP error code. Codes:
 * - 400 Bad Request (bad parameters or validation error);
 * - 404 Not Found (not existing URL path or record not found);
 * - 403 Forbidden (no access to scope or to record);
 * - 409 Conflict (can be that a duplicate record already exists).
 */
class ResponseError extends Error
{
    final public function __construct(
        private Response $response,
        string $message = '',
        int $code = 0
    ) {
        parent::__construct($message, $code);
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
