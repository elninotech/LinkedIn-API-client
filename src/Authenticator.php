<?php declare(strict_types=1);
namespace Elnino\LinkedIn;

use function array_merge;
use function http_build_query;
use function implode;
use function is_array;
use function is_string;
use function md5;
use function mt_rand;
use function str_replace;
use function uniqid;
use Elnino\LinkedIn\Exception\LinkedInException;
use Elnino\LinkedIn\Exception\LinkedInTransferException;
use Elnino\LinkedIn\Http\GlobalVariableGetter;
use Elnino\LinkedIn\Http\LinkedInUrlGeneratorInterface;
use Elnino\LinkedIn\Http\RequestManagerInterface;
use Elnino\LinkedIn\Http\ResponseConverter;
use Elnino\LinkedIn\Storage\DataStorageInterface;
use Elnino\LinkedIn\Storage\SessionStorage;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Authenticator implements AuthenticatorInterface
{
    /**
     * The application ID.
     *
     * @var string
     */
    protected $appId;

    /**
     * The application secret.
     *
     * @var string
     */
    protected $appSecret;

    /**
     * A storage to use to store data between requests.
     *
     * @var DataStorageInterface storage
     */
    private $storage;

    /**
     * @var RequestManagerInterface
     */
    private $requestManager;

    /**
     * @param string $appId
     * @param string $appSecret
     */
    public function __construct(RequestManagerInterface $requestManager, $appId, $appSecret)
    {
        $this->appId          = $appId;
        $this->appSecret      = $appSecret;
        $this->requestManager = $requestManager;
    }

    /**
     * @inheritDoc
     */
    public function fetchNewAccessToken(LinkedInUrlGeneratorInterface $urlGenerator)
    {
        $storage = $this->getStorage();
        $code    = $this->getCode();

        if ($code === null) {
            /*
             * As a fallback, just return whatever is in the persistent
             * store, knowing nothing explicit (signed request, authorization
             *  code, etc.) was present to shadow it.
             */
            return $storage->get('access_token');
        }

        try {
            $accessToken = $this->getAccessTokenFromCode($urlGenerator, $code);
        } catch (LinkedInException $e) {
            // code was bogus, so everything based on it should be invalidated.
            $storage->clearAll();

            throw $e;
        }

        $storage->set('code', $code);
        $storage->set('access_token', $accessToken);

        return $accessToken;
    }

    /**
     * @inheritDoc
     */
    public function getLoginUrl(LinkedInUrlGeneratorInterface $urlGenerator, $options = [])
    {
        // Generate a state
        $this->establishCSRFTokenState();

        // Build request params
        $requestParams = array_merge([
            'response_type' => 'code',
            'client_id'     => $this->appId,
            'state'         => $this->getStorage()->get('state'),
            'redirect_uri'  => null,
        ], $options);

        // Save the redirect url for later
        $this->getStorage()->set('redirect_uri', $requestParams['redirect_uri']);

        // if 'scope' is passed as an array, convert to space separated list
        $scopeParams = $options['scope'] ?? null;

        if ($scopeParams) {
            // if scope is an array
            if (is_array($scopeParams)) {
                $requestParams['scope'] = implode(' ', $scopeParams);
            } elseif (is_string($scopeParams)) {
                // if scope is a string with ',' => make it to an array
                $requestParams['scope'] = str_replace(',', ' ', $scopeParams);
            }
        }

        return $urlGenerator->getUrl('www', 'oauth/v2/authorization', $requestParams);
    }

    /**
     * @inheritDoc
     */
    public function clearStorage()
    {
        $this->getStorage()->clearAll();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setStorage(DataStorageInterface $storage)
    {
        $this->storage = $storage;

        return $this;
    }

    /**
     * Retrieves an access token for the given authorization code
     * (previously generated from www.linkedin.com on behalf of
     * a specific user). The authorization code is sent to www.linkedin.com
     * and a legitimate access token is generated provided the access token
     * and the user for which it was generated all match, and the user is
     * either logged in to LinkedIn or has granted an offline access permission.
     *
     * @param string $code an authorization code
     *
     * @throws LinkedInException
     *
     * @return AccessToken an access token exchanged for the authorization code
     */
    protected function getAccessTokenFromCode(LinkedInUrlGeneratorInterface $urlGenerator, $code)
    {
        if (empty($code)) {
            throw new LinkedInException('Could not get access token: The code was empty.');
        }

        $redirectUri = $this->getStorage()->get('redirect_uri');

        try {
            $url     = $urlGenerator->getUrl('www', 'oauth/v2/accessToken');
            $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
            $body    = http_build_query(
                [
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => $redirectUri,
                    'client_id'     => $this->appId,
                    'client_secret' => $this->appSecret,
                ],
            );

            $response = ResponseConverter::convertToArray($this->getRequestManager()->sendRequest('POST', $url, $headers, $body));
        } catch (LinkedInTransferException $e) {
            // most likely that user very recently revoked authorization.
            // In any event, we don't have an access token, so throw an exception.
            throw new LinkedInException('Could not get access token: The user may have revoked the authorization response from LinkedIn.com was empty.', $e->getCode(), $e);
        }

        if (empty($response)) {
            throw new LinkedInException('Could not get access token: The response from LinkedIn.com was empty.');
        }

        $tokenData = array_merge(['access_token' => null, 'expires_in' => null], $response);
        $token     = new AccessToken($tokenData['access_token'], $tokenData['expires_in']);

        if (!$token->hasToken()) {
            throw new LinkedInException('Could not get access token: The response from LinkedIn.com did not contain a token.');
        }

        return $token;
    }

    /**
     * Get the authorization code from the query parameters, if it exists,
     * and otherwise return null to signal no authorization code was
     * discovered.
     *
     * @throws LinkedInException on invalid CSRF tokens
     *
     * @return null|string the authorization code, or null if the authorization code not exists
     */
    protected function getCode()
    {
        $storage = $this->getStorage();

        if (!GlobalVariableGetter::has('code')) {
            return null;
        }

        if ($storage->get('code') === GlobalVariableGetter::get('code')) {
            // we have already validated this code
            return null;
        }

        // if stored state does not exists
        if (null === $state = $storage->get('state')) {
            throw new LinkedInException('Could not find a stored CSRF state token.');
        }

        // if state not exists in the request
        if (!GlobalVariableGetter::has('state')) {
            throw new LinkedInException('Could not find a CSRF state token in the request.');
        }

        // if state exists in session and in request and if they are not equal
        if ($state !== GlobalVariableGetter::get('state')) {
            throw new LinkedInException('The CSRF state token from the request does not match the stored token.');
        }

        // CSRF state has done its job, so clear it
        $storage->clear('state');

        return GlobalVariableGetter::get('code');
    }

    /**
     * Lays down a CSRF state token for this process.
     */
    protected function establishCSRFTokenState(): void
    {
        $storage = $this->getStorage();

        if ($storage->get('state') === null) {
            $storage->set('state', md5(uniqid((string) mt_rand(), true)));
        }
    }

    /**
     * @return DataStorageInterface
     */
    protected function getStorage()
    {
        if ($this->storage === null) {
            $this->storage = new SessionStorage;
        }

        return $this->storage;
    }

    /**
     * @return RequestManagerInterface
     */
    protected function getRequestManager()
    {
        return $this->requestManager;
    }
}
