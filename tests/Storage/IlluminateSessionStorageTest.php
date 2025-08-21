<?php

namespace Elnino\LinkedIn\Tests\Storage;

use Elnino\LinkedIn\Storage\IlluminateSessionStorage;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Session;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class SessionStorageTest.
 *
 * @author Andreas Creten
 */
#[CoversClass(IlluminateSessionStorage::class)]
class IlluminateSessionStorageTest extends MockeryTestCase
{
    /**
     * @var \Elnino\LinkedIn\Storage\IlluminateSessionStorage storage
     */
    protected $storage;

    protected $prefix = 'linkedIn_';

    public function setUp(): void
    {
        Facade::clearResolvedInstances();

        $this->storage = new IlluminateSessionStorage();
    }

    public function testSet()
    {
        Session::shouldReceive('put')->once()->with($this->prefix . 'code', 'foobar');

        $this->storage->set('code', 'foobar');
    }

    public function testSetFail()
    {
        $this->expectException(\Elnino\LinkedIn\Exception\InvalidArgumentException::class);
        $this->storage->set('foobar', 'baz');
    }

    public function testGet()
    {
        $expected = 'foobar';
        Session::shouldReceive('get')->once()->with($this->prefix . 'code')->andReturn($expected);
        $result = $this->storage->get('code');
        $this->assertEquals($expected, $result);

        Session::shouldReceive('get')->once()->with($this->prefix . 'state')->andReturn(null);
        $result = $this->storage->get('state');
        $this->assertNull($result);
    }

    public function testClear()
    {
        Session::shouldReceive('forget')->once()->with($this->prefix . 'code')->andReturn(true);
        $this->storage->clear('code');
    }

    public function testClearFail()
    {
        $this->expectException(\Elnino\LinkedIn\Exception\InvalidArgumentException::class);
        $this->storage->clear('foobar');
    }
}
