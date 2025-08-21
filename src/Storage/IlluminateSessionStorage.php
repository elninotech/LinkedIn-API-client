<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Storage;

use Illuminate\Support\Facades\Session;

/**
 * Store data in a IlluminateSession.
 *
 * @author Andreas Creten
 */
class IlluminateSessionStorage extends BaseDataStorage
{
    /**
     * @inheritDoc
     */
    public function set($key, $value): void
    {
        $this->validateKey($key);
        $name = $this->getStorageKeyId($key);

        Session::put($name, $value);
    }

    /**
     * @inheritDoc
     */
    public function get($key)
    {
        $this->validateKey($key);
        $name = $this->getStorageKeyId($key);

        return Session::get($name);
    }

    /**
     * @inheritDoc
     */
    public function clear($key): void
    {
        $this->validateKey($key);
        $name = $this->getStorageKeyId($key);

        Session::forget($name);
    }
}
