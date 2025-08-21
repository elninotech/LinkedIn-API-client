<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Storage;

use function implode;
use function in_array;
use Elnino\LinkedIn\Exception\InvalidArgumentException;

/**
 * @author Tobias Nyholm
 */
abstract class BaseDataStorage implements DataStorageInterface
{
    /** @var string[] */
    public static $validKeys = ['state', 'code', 'access_token', 'redirect_uri'];

    /**
     * @inheritDoc
     */
    public function clearAll(): void
    {
        foreach (self::$validKeys as $key) {
            $this->clear($key);
        }
    }

    /**
     * Validate key. Throws an exception if key is not valid.
     *
     * @param string $key
     *
     * @throws InvalidArgumentException
     */
    protected function validateKey($key): void
    {
        if (!in_array($key, self::$validKeys, true)) {
            throw new InvalidArgumentException('Unsupported key "%s" passed to LinkedIn data storage. Valid keys are: %s', $key, implode(', ', self::$validKeys));
        }
    }

    /**
     * Generate an ID to use with the data storage.
     *
     * @param string $key
     *
     * @return string
     */
    protected function getStorageKeyId($key)
    {
        return 'linkedIn_' . $key;
    }
}
