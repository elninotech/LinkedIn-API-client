<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Http;

use function is_string;
use Elnino\LinkedIn\Exception\LinkedInTransferException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * A class to create HTTP requests and to send them.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class RequestManager implements RequestManagerInterface
{
    /**
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * @var null|StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @param null|ClientInterface         $httpClient
     * @param null|RequestFactoryInterface $requestFactory
     * @param null|StreamFactoryInterface  $streamFactory
     */
    public function __construct($httpClient = null, $requestFactory = null, $streamFactory = null)
    {
        $this->httpClient     = $httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory  = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    /**
     * @inheritDoc
     */
    public function sendRequest($method, $uri, array $headers = [], $body = null)
    {
        try {
            return $this->httpClient->sendRequest(
                $this->createRequest($method, $uri, $headers, $body),
            );
        } catch (ClientExceptionInterface $e) {
            throw new LinkedInTransferException('Error while requesting data from LinkedIn.com: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param string|UriInterface         $uri
     * @param mixed[]                     $headers
     * @param null|StreamInterface|string $body
     *
     * @return RequestInterface
     */
    private function createRequest(string $method, $uri, array $headers = [], $body = null)
    {
        $request = $this->requestFactory->createRequest($method, $uri);

        foreach ($headers as $key => $value) {
            $request = $request->withHeader($key, $value);
        }

        if (null !== $body && '' !== $body) {
            if (null === $this->streamFactory) {
                throw new RuntimeException('Cannot create request: A stream factory is required to create a request with a non-empty string body.');
            }

            $request = $request->withBody(
                is_string($body) ? $this->streamFactory->createStream($body) : $body,
            );
        }

        return $request;
    }
}
