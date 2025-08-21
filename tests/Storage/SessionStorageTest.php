<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Tests\Storage;

use function count;
use function in_array;
use Elnino\LinkedIn\Exception\InvalidArgumentException;
use Elnino\LinkedIn\Storage\SessionStorage;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class SessionStorageTest.
 *
 * @author Tobias Nyholm
 */
#[CoversClass(SessionStorage::class)]
#[UsesClass(InvalidArgumentException::class)]
class SessionStorageTest extends MockeryTestCase
{
    /**
     * @var SessionStorage storage
     */
    protected $storage;
    protected $prefix = 'linkedIn_';

    protected function setUp(): void
    {
        $this->storage = new SessionStorage;
    }

    public function testSet(): void
    {
        $this->storage->set('code', 'foobar');
        $this->assertEquals($_SESSION[$this->prefix . 'code'], 'foobar');
    }

    public function testSetFail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->set('foobar', 'baz');
    }

    public function testGet(): void
    {
        unset($_SESSION[$this->prefix . 'state']);
        $result = $this->storage->get('state');
        $this->assertNull($result);

        $expected                         = 'foobar';
        $_SESSION[$this->prefix . 'code'] = $expected;
        $result                           = $this->storage->get('code');
        $this->assertEquals($expected, $result);
    }

    public function testClear(): void
    {
        $_SESSION[$this->prefix . 'code'] = 'foobar';
        $this->storage->clear('code');
        $this->assertFalse(isset($_SESSION[$this->prefix . 'code']));
    }

    public function testClearFail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->clear('foobar');
    }

    public function testClearAll(): void
    {
        $validKeys = SessionStorage::$validKeys;

        $storage = Mockery::mock('Elnino\LinkedIn\Storage\SessionStorage[clear]')
            ->shouldReceive('clear')->times(count($validKeys))
            ->with(Mockery::on(static function ($arg) use ($validKeys)
            {
                return in_array($arg, $validKeys, true);
            }))
            ->getMock();

        $storage->clearAll();
    }
}
