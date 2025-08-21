<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Tests\Storage;

use Elnino\LinkedIn\Exception\InvalidArgumentException;
use Elnino\LinkedIn\Storage\IlluminateSessionStorage;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Session;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class SessionStorageTest.
 *
 * @author Andreas Creten
 */
#[CoversClass(IlluminateSessionStorage::class)]
#[UsesClass(InvalidArgumentException::class)]
class IlluminateSessionStorageTest extends MockeryTestCase
{
    /**
     * @var IlluminateSessionStorage storage
     */
    protected $storage;
    protected $prefix = 'linkedIn_';

    protected function setUp(): void
    {
        Facade::clearResolvedInstances();

        $this->storage = new IlluminateSessionStorage;
    }

    public function testSet(): void
    {
        Session::shouldReceive('put')->once()->with($this->prefix . 'code', 'foobar');

        $this->storage->set('code', 'foobar');
    }

    public function testSetFail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->set('foobar', 'baz');
    }

    public function testGet(): void
    {
        $expected = 'foobar';
        Session::shouldReceive('get')->once()->with($this->prefix . 'code')->andReturn($expected);
        $result = $this->storage->get('code');
        $this->assertEquals($expected, $result);

        Session::shouldReceive('get')->once()->with($this->prefix . 'state')->andReturn(null);
        $result = $this->storage->get('state');
        $this->assertNull($result);
    }

    public function testClear(): void
    {
        Session::shouldReceive('forget')->once()->with($this->prefix . 'code')->andReturn(true);
        $this->storage->clear('code');
    }

    public function testClearFail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->storage->clear('foobar');
    }
}
