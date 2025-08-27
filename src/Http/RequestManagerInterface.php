<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Http;

use Elnino\LinkedIn\Exception\LinkedInTransferException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

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
     * @param string                      $method
     * @param string|UriInterface         $uri
     * @param mixed[]                     $headers
     * @param null|StreamInterface|string $body
     *
     * @throws LinkedInTransferException
     *
     * @return ResponseInterface
     */
    public function sendRequest($method, $uri, array $headers = [], $body = null);
}
