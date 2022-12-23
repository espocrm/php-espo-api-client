<?php
/* (c) EspoCRM */

namespace Espo\ApiClient;

use CurlHandle;
use Espo\ApiClient\Exception\Error;
use Espo\ApiClient\Exception\ResponseError;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use stdClass;

class Client
{
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_OPTIONS = 'OPTIONS';

    private string $urlPath = '/api/v1/';
    private ?string $username = null;
    private ?string $password = null;
    private ?string $apiKey = null;
    private ?string $secretKey = null;
    private ?CurlHandle $lastCh;

    /**
     * @param string $url An EspoCRM site URL.
     * @param ?int $port A port.
     */
    public function __construct(
        private string $url,
        private ?int $port = null
    ) {}

    /**
     * Set a username and password (Basic authentication). Not recommended way to authenticate.
     */
    public function setUsernameAndPassword(?string $username, ?string $password): self
    {
        $this->username = $username;
        $this->password = $password;

        return $this;
    }

    /**
     * Set an API key.
     */
    public function setApiKey(?string $apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * Set a secret key (for HMAC authentication).
     */
    public function setSecretKey(?string $secretKey): self
    {
        $this->secretKey = $secretKey;

        return $this;
    }

    /**
     * Send request to EspoCRM.
     *
     * @param self::METHOD_* $method A method. 'GET', 'POST', 'PUT', 'DELETE'.
     * @param string $path A relative URL path. E.g. `Account/00000000000id`.
     * @param array<int, mixed>|array<string, mixed>|stdClass|null $data Payload data.
     * @param Header[] $headers Headers.
     * @return Response A response (on success).
     * @throws Error
     * @throws ResponseError On error occurred on request.
     */
    public function request(
        string $method,
        string $path,
        mixed $data = null,
        array $headers = []
    ): Response {

        $method = strtoupper($method);
        $this->lastCh = null;
        $url = $this->composeFullUrl($path);
        $curlHeaderList = [];

        $ch = curl_init($url);

        if (!$ch) {
            throw new RuntimeException("Could not init CURL.");
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if (
            $this->apiKey &&
            $this->secretKey
        ) {
            $string = $method . ' /' . $path;
            $authPart = base64_encode($this->apiKey . ':' .
                hash_hmac('sha256', $string, $this->secretKey, true));
            $authHeader = 'X-Hmac-Authorization: ' .  $authPart;

            $curlHeaderList[] = $authHeader;
        }
        else if ($this->apiKey) {
            $authHeader = 'X-Api-Key: ' .  $this->apiKey;

            $curlHeaderList[] = $authHeader;
        }
        else if (
            $this->username &&
            $this->password
        ) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        if ($this->port !== null) {
            curl_setopt($ch, CURLOPT_PORT, $this->port);
        }

        if ($method !== self::METHOD_GET) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if (
            (
                $data !== null &&
                !is_array($data) &&
                !is_object($data) &&
                !is_string($data)
            ) ||
            (
                $method === self::METHOD_GET &&
                is_string($data)
            )
        ) {
            throw new InvalidArgumentException("\$data should be array|stdClass|null.");
        }

        if (isset($data)) {
            if ($method === self::METHOD_GET) {
                curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
            }
            else {
                $contentType = $this->obtainHeaderValue($headers, 'Content-Type');

                if (
                    !$contentType ||
                    $contentType === 'application/json'
                ) {
                    try {
                        $payload = json_encode($data, JSON_THROW_ON_ERROR);
                    }
                    catch (JsonException) {
                        throw new InvalidArgumentException("Invalid JSON.");
                    }

                    if (!$contentType) {
                        $curlHeaderList[] = 'Content-Type: application/json';
                    }
                }
                else if ($contentType === 'application/x-www-form-urlencoded') {
                    $payload = $data;
                }
                else {
                    $payload = $data;
                }

                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

                if (is_string($payload)) {
                    $curlHeaderList[] = 'Content-Length: ' . strlen($payload);
                }
            }
        }

        foreach ($headers as $header) {
            $curlHeaderList[] = $header->getName() . ':' . $header->getValue();
        }

        if ($curlHeaderList !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaderList);
        }

        /** @var string|false $lastResponse */
        $lastResponse = curl_exec($ch);

        if ($lastResponse === false) {
            throw new Error('CURL exec failure.', 0);
        }

        $this->lastCh = $ch;

        $parsedResponse = $this->parseResponse($lastResponse);
        $responseCode = $this->getResponseHttpCode();
        $responseContentType = $this->getResponseContentType();

        $response = new Response(
            $responseCode ?? 0,
            $responseContentType,
            $parsedResponse['header'],
            $parsedResponse['body'],
        );

        curl_close($ch);

        if (
            $responseCode !== null &&
            $responseCode >= 200 &&
            $responseCode < 300
        ) {
            return $response;
        }

        $responseHeaders = $this->normalizeHeader($parsedResponse['header']);
        $errorMessage = $responseHeaders['X-Status-Reason'] ?? '';

        throw new ResponseError($response, $errorMessage, $responseCode ?? 0);
    }

    /**
     * Get a response content type.
     */
    private function getResponseContentType(): ?string
    {
        return $this->getInfo(CURLINFO_CONTENT_TYPE);
    }

    /**
     * Get a response total time.
     */
    private function getResponseTotalTime(): ?int
    {
        return $this->getInfo(CURLINFO_TOTAL_TIME);
    }

    /**
     * Get a response code.
     */
    private function getResponseHttpCode(): ?int
    {
        return $this->getInfo(CURLINFO_HTTP_CODE);
    }

    /**
     * @param Header[] $headers
     */
    private function obtainHeaderValue(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if (strtolower($header->getName()) === strtolower($name)) {
                return $header->getValue();
            }
        }

        return null;
    }

    private function composeFullUrl(string $action): string
    {
        return $this->url . $this->urlPath . $action;
    }

    private function getInfo(int $option): mixed
    {
        if (isset($this->lastCh)) {
            return curl_getinfo($this->lastCh, $option);
        }

        return null;
    }

    /**
     * @return array{header: string, body: string}
     */
    private function parseResponse(string $response): array
    {
        $headerSize = $this->getInfo(CURLINFO_HEADER_SIZE);

        return [
            'header' => trim(substr($response, 0, $headerSize)),
            'body' => substr($response, $headerSize),
        ];
    }

    /**
     * @return string[]
     */
    private function normalizeHeader(string $header): array
    {
        preg_match_all('/(.*?): (.*)\r\n/', $header, $matches);

        $headerArray = [];

        foreach ($matches[1] as $index => $name) {
            if (isset($matches[2][$index])) {
                $headerArray[$name] = trim($matches[2][$index]);
            }
        }

        return $headerArray;
    }
}