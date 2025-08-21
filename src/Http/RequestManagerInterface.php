<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Http;

use Elnino\LinkedIn\Exception\LinkedInTransferException;
use Http\Client\HttpClient;
use Psr\Http\Message\ResponseInterface;

/**
 * A request manager builds a request.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface RequestManagerInterface
{
    /**
     * Send a request.
     *
     * @param string  $method
     * @param string  $uri
     * @param mixed[] $headers
     * @param string  $body
     * @param string  $protocolVersion
     *
     * @throws LinkedInTransferException
     *
     * @return ResponseInterface
     */
    public function sendRequest($method, $uri, array $headers = [], $body = null, $protocolVersion = '1.1');

    /**
     * @return RequestManager
     */
    public function setHttpClient(HttpClient $httpClient);
}
