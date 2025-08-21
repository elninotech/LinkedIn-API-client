<?php declare(strict_types=1);
namespace Elnino\LinkedIn;

use Elnino\LinkedIn\Exception\LinkedInException;
use Elnino\LinkedIn\Http\LinkedInUrlGeneratorInterface;
use Elnino\LinkedIn\Storage\DataStorageInterface;

/**
 * This interface is responsible for the authentication process with LinkedIn.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface AuthenticatorInterface
{
    /**
     * Tries to get a new access token from data storage or code. If it fails, it will return null.
     *
     * @throws LinkedInException
     *
     * @return null|AccessToken a valid user access token, or null if one could not be fetched
     */
    public function fetchNewAccessToken(LinkedInUrlGeneratorInterface $urlGenerator);

    /**
     * Generate a login url.
     *
     * @param array<mixed> $options
     *
     * @return string
     */
    public function getLoginUrl(LinkedInUrlGeneratorInterface $urlGenerator, $options = []);

    /**
     * Clear the storage.
     *
     * @return $this
     */
    public function clearStorage();

    /**
     * @return $this
     */
    public function setStorage(DataStorageInterface $storage);
}
