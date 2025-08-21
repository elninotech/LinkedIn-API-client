<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Http;

use Elnino\LinkedIn\Exception\LinkedInTransferException;
use Http\Client\Exception\TransferException;
use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;
use Http\Message\MessageFactory;

/**
 * A class to create HTTP requests and to send them.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class RequestManager implements RequestManagerInterface
{
    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @inheritDoc
     */
    public function sendRequest($method, $uri, array $headers = [], $body = null, $protocolVersion = '1.1')
    {
        $request = $this->getMessageFactory()->createRequest($method, $uri, $headers, $body, $protocolVersion);

        try {
            return $this->getHttpClient()->sendRequest($request);
        } catch (TransferException $e) {
            throw new LinkedInTransferException('Error while requesting data from LinkedIn.com: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function setHttpClient(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * @return RequestManager
     */
    public function setMessageFactory(MessageFactory $messageFactory)
    {
        $this->messageFactory = $messageFactory;

        return $this;
    }

    /**
     * @return HttpClient
     */
    protected function getHttpClient()
    {
        if ($this->httpClient === null) {
            $this->httpClient = HttpClientDiscovery::find();
        }

        return $this->httpClient;
    }

    /**
     * @return MessageFactory
     */
    private function getMessageFactory()
    {
        if ($this->messageFactory === null) {
            $this->messageFactory = MessageFactoryDiscovery::find();
        }

        return $this->messageFactory;
    }
}
