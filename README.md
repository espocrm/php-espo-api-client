# PHP EspoCRM API client

Require with Composer:

```
composer require espocrm/php-espo-api-client
```

Usage:

```
use Espo\ApiClient\Client;
use Espo\ApiClient\Header;
use Espo\ApiClient\Exception\ResponseError;

$client = new Client($yourEspoUrl);
$client->setApiKey($apiKey);
$client->setSecretKey($secretKey); // if you use HMAC method

try {
    $response = $client->request(
        Client::METHOD_POST,
        'Lead',
        [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'emailAddress' => $emailAddress,
        ],
        [
            new Header('X-Skip-Duplicate-Check', 'true')   
        ]
    );
    
    $parsedBody = $response->getParsedBody();
}
catch (ResponseError $e) {
    // Error response.
    $response = $e->getResponse();    
    
    $code = $response->getCode();
    $body = $response->getBody();
}
```
