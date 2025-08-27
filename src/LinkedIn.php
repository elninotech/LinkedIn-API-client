<?php declare(strict_types=1);
namespace Elnino\LinkedIn;

use function json_encode;
use function sprintf;
use Elnino\LinkedIn\Exception\LoginError;
use Elnino\LinkedIn\Http\GlobalVariableGetter;
use Elnino\LinkedIn\Http\RequestManager;
use Elnino\LinkedIn\Http\RequestManagerInterface;
use Elnino\LinkedIn\Http\ResponseConverter;
use Elnino\LinkedIn\Http\UrlGenerator;
use Elnino\LinkedIn\Http\UrlGeneratorInterface;
use Elnino\LinkedIn\Storage\DataStorageInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class LinkedIn lets you talk to LinkedIn api.
 *
 * When a new user arrives and want to authenticate here is whats happens:
 * 1. You redirect him to whatever url getLoginUrl() returns.
 * 2. The user logs in on www.linkedin.com and authorize your application.
 * 3. The user returns to your site with a *code* in the the $_REQUEST.
 * 4. You call isAuthenticated() or getAccessToken()
 * 5. If we don't have an access token (only a *code*), getAccessToken() will call fetchNewAccessToken()
 * 6. fetchNewAccessToken() gets the *code* from the $_REQUEST and calls getAccessTokenFromCode()
 * 7. getAccessTokenFromCode() makes a request to www.linkedin.com and exchanges the *code* for an access token
 * 8. When you have the access token you should store it in a database and/or query the API.
 * 9. When you make a second request to the API we have the access token in memory, so we don't go through all these
 *    authentication steps again.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class LinkedIn implements LinkedInInterface
{
    /**
     * The OAuth access token received in exchange for a valid authorization
     * code.  null means the access token has yet to be determined.
     *
     * @var AccessToken
     */
    protected $accessToken;

    /**
     * @var string responseFormat
     */
    private $responseDataType;

    /**
     * @var ResponseInterface
     */
    private $lastResponse;

    /**
     * @var RequestManagerInterface
     */
    private $requestManager;

    /**
     * @var Authenticator
     */
    private $authenticator;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * Constructor.
     *
     * @param string $appId
     * @param string $appSecret
     * @param string $responseDataType 'array', 'string' 'psr7', 'stream'
     */
    public function __construct($appId, $appSecret, $responseDataType = 'array', ?RequestManagerInterface $requestManager = null)
    {
        $this->responseDataType = $responseDataType;
        $this->requestManager   = $requestManager ?? new RequestManager;
        $this->authenticator    = new Authenticator($this->requestManager, $appId, $appSecret);
    }

    /**
     * @inheritDoc
     */
    public function isAuthenticated()
    {
        $accessToken = $this->getAccessToken();

        if ($accessToken === null) {
            return false;
        }

        $user = $this->api('GET', '/v2/me/?projection=(id,firstName,lastName)', ['response_data_type' => 'array']);

        return !empty($user['id']);
    }

    /**
     * @inheritDoc
     */
    public function api($method, $resource, array $options = [])
    {
        // Add access token to the headers
        $options['headers']['Authorization'] = sprintf('Bearer %s', $this->getAccessToken());

        // Do logic and adjustments to the options
        $options                            = $this->filterRequestOption($options);
        $options['headers']['Content-Type'] = 'application/json';

        // Generate an url
        $url = $this->getUrlGenerator()->getUrl(
            'api',
            $resource,
            $options['query'] ?? [],
        );

        $body               = $options['body'] ?? null;
        $this->lastResponse = $this->getRequestManager()->sendRequest($method, $url, $options['headers'], $body);

        // Get the response data format
        $responseDataType = $options['response_data_type'] ?? $this->getResponseDataType();

        return ResponseConverter::convert($this->lastResponse, $responseDataType);
    }

    /**
     * @inheritDoc
     */
    public function getLoginUrl($options = [])
    {
        $urlGenerator = $this->getUrlGenerator();

        // Set redirect_uri to current URL if not defined
        if (!isset($options['redirect_uri'])) {
            $options['redirect_uri'] = $urlGenerator->getCurrentUrl();
        }

        return $this->getAuthenticator()->getLoginUrl($urlGenerator, $options);
    }

    /**
     * See docs for LinkedIn::api().
     *
     * @param string $resource
     *
     * @return mixed
     */
    public function get($resource, array $options = [])
    {
        return $this->api('GET', $resource, $options);
    }

    /**
     * See docs for LinkedIn::api().
     *
     * @param string $resource
     *
     * @return mixed
     */
    public function post($resource, array $options = [])
    {
        return $this->api('POST', $resource, $options);
    }

    /**
     * @inheritDoc
     */
    public function clearStorage()
    {
        $this->getAuthenticator()->clearStorage();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasError()
    {
        return GlobalVariableGetter::has('error');
    }

    /**
     * @inheritDoc
     */
    public function getError()
    {
        if ($this->hasError()) {
            return new LoginError(GlobalVariableGetter::get('error'), GlobalVariableGetter::get('error_description'));
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function setResponseDataType($responseDataType)
    {
        $this->responseDataType = $responseDataType;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * @inheritDoc
     */
    public function getAccessToken()
    {
        if ($this->accessToken === null) {
            if (null !== $newAccessToken = $this->getAuthenticator()->fetchNewAccessToken($this->getUrlGenerator())) {
                $this->setAccessToken($newAccessToken);
            }
        }

        // return the new access token or null if none found
        return $this->accessToken;
    }

    /**
     * @inheritDoc
     */
    public function setAccessToken($accessToken)
    {
        if (!($accessToken instanceof AccessToken)) {
            $accessToken = new AccessToken($accessToken);
        }

        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setStorage(DataStorageInterface $storage)
    {
        $this->getAuthenticator()->setStorage($storage);

        return $this;
    }

    /**
     * Modify and filter the request options. Make sure we use the correct query parameters and headers.
     *
     * @param mixed[] $options
     *
     * @return mixed[] the formatted options
     */
    protected function filterRequestOption(array $options)
    {
        if (isset($options['json'])) {
            $options['body'] = json_encode($options['json']);
        }

        return $options;
    }

    /**
     * Get the default data type to be returned as a response.
     *
     * @return string
     */
    protected function getResponseDataType()
    {
        return $this->responseDataType;
    }

    /**
     * @return UrlGeneratorInterface
     */
    protected function getUrlGenerator()
    {
        if ($this->urlGenerator === null) {
            $this->urlGenerator = new UrlGenerator;
        }

        return $this->urlGenerator;
    }

    /**
     * @return RequestManagerInterface
     */
    protected function getRequestManager()
    {
        return $this->requestManager;
    }

    /**
     * @return Authenticator
     */
    protected function getAuthenticator()
    {
        return $this->authenticator;
    }
}
