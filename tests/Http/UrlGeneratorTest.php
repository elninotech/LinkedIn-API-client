<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Tests\Http;

use Elnino\LinkedIn\Http\UrlGenerator;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class UrlGeneratorTest.
 *
 * @author Tobias Nyholm
 */
#[CoversClass(UrlGenerator::class)]
class UrlGeneratorTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);

        $_SERVER['HTTP_HOST'] = 'localhost';
        unset($_SERVER['HTTP_X_FORWARDED_HOST']);
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['REQUEST_URI'] = '';
    }

    public function testDropLinkedInParams(): void
    {
        $gen = new DummyUrlGenerator;

        $test     = 'foo=bar&code=foobar&baz=foo';
        $expected = '?foo=bar&baz=foo';
        $this->assertEquals($expected, $gen->dropLinkedInParams($test));

        $test     = 'code=foobar&baz=foo';
        $expected = '?baz=foo';
        $this->assertEquals($expected, $gen->dropLinkedInParams($test));

        $test     = 'foo=bar&code=foobar';
        $expected = '?foo=bar';
        $this->assertEquals($expected, $gen->dropLinkedInParams($test));

        $test     = 'code=foobar';
        $expected = '';
        $this->assertEquals($expected, $gen->dropLinkedInParams($test));

        $test     = '';
        $expected = '';
        $this->assertEquals($expected, $gen->dropLinkedInParams($test));

        /* ----------------- */

        $test     = 'foo=bar&code=';
        $expected = '?foo=bar';
        $this->assertEquals($expected, $gen->dropLinkedInParams($test));

        $test     = 'code=';
        $expected = '';
        $this->assertEquals($expected, $gen->dropLinkedInParams($test));

        $test     = 'foo=bar&code';
        $expected = '?foo=bar';
        $this->assertEquals($expected, $gen->dropLinkedInParams($test));

        $test     = 'code';
        $expected = '';
        $this->assertEquals($expected, $gen->dropLinkedInParams($test));
    }

    public function testGetUrl(): void
    {
        $gen = new DummyUrlGenerator;

        $expected = 'https://api.linkedin.com/?bar=baz';
        $this->assertEquals($expected, $gen->getUrl('api', '', ['bar' => 'baz']), 'No path');

        $expected = 'https://api.linkedin.com/foobar';
        $this->assertEquals($expected, $gen->getUrl('api', 'foobar'), 'Path does not begin with forward slash');
        $this->assertEquals($expected, $gen->getUrl('api', '/foobar'), 'Path begins with forward slash');

        $expected = 'https://api.linkedin.com/foobar?bar=baz';
        $this->assertEquals($expected, $gen->getUrl('api', 'foobar', ['bar' => 'baz']), 'One parameter');

        $expected = 'https://api.linkedin.com/foobar?bar=baz&a=b&c=d';
        $this->assertEquals($expected, $gen->getUrl('api', 'foobar', ['bar' => 'baz', 'a' => 'b', 'c' => 'd']), 'Many parameters');

        $expected    = 'https://api.linkedin.com/foobar?bar=baz%20a%20b';
        $notExpected = 'https://api.linkedin.com/foobar?bar=baz+a+b';
        $this->assertEquals($expected, $gen->getUrl('api', 'foobar', ['bar' => 'baz a b']), 'Use of PHP_QUERY_RFC3986');
        $this->assertNotEquals($notExpected, $gen->getUrl('api', 'foobar', ['bar' => 'baz a b']), 'Dont use PHP_QUERY_RFC1738');
    }

    public function testGetUrlWithParams(): void
    {
        $gen = new UrlGenerator;

        $expected = 'https://api.linkedin.com/endpoint?bar=baz&format=json';
        $this->assertEquals($expected, $gen->getUrl('api', 'endpoint?bar=baz', ['format' => 'json']));

        $expected = 'https://api.linkedin.com/endpoint?bar=baz&bar=baz';
        $this->assertEquals($expected, $gen->getUrl('api', 'endpoint?bar=baz', ['bar' => 'baz']));
    }

    public function testGetCurrentURL(): void
    {
        $gen = Mockery::mock(UrlGenerator::class)->makePartial();
        $gen->shouldAllowMockingProtectedMethods();
        $gen->shouldReceive('getHttpProtocol')->andReturn('http');
        $gen->shouldReceive('getHttpHost')->andReturn('www.test.com');
        $gen->shouldReceive('dropLinkedInParams')->andReturnUsing(static function ($arg)
        {
            return empty($arg) ? '' : '?' . $arg;
        });

        // fake the HPHP $_SERVER globals
        $_SERVER['REQUEST_URI'] = '/unit-tests.php?one=one&two=two&three=three';
        $this->assertEquals(
            'http://www.test.com/unit-tests.php?one=one&two=two&three=three',
            $gen->getCurrentUrl(),
            'getCurrentUrl function is changing the current URL',
        );

        // ensure structure of valueless GET params is retained (sometimes
        // an = sign was present, and sometimes it was not)
        // first test when equal signs are present
        $_SERVER['REQUEST_URI'] = '/unit-tests.php?one=&two=&three=';
        $this->assertEquals(
            'http://www.test.com/unit-tests.php?one=&two=&three=',
            $gen->getCurrentUrl(),
            'getCurrentUrl function is changing the current URL',
        );

        // now confirm that
        $_SERVER['REQUEST_URI'] = '/unit-tests.php?one&two&three';
        $this->assertEquals(
            'http://www.test.com/unit-tests.php?one&two&three',
            $gen->getCurrentUrl(),
            'getCurrentUrl function is changing the current URL',
        );
    }

    public function testGetCurrentURLPort80(): void
    {
        $gen = Mockery::mock(UrlGenerator::class)->makePartial();
        $gen->shouldAllowMockingProtectedMethods();
        $gen->shouldReceive('getHttpProtocol')->andReturn('http');
        $gen->shouldReceive('getHttpHost')->andReturn('www.test.com:80');
        $gen->shouldReceive('dropLinkedInParams')->andReturnUsing(static function ($arg)
        {
            return empty($arg) ? '' : '?' . $arg;
        });

        // test port 80
        $_SERVER['REQUEST_URI'] = '/foobar.php';
        $this->assertEquals(
            'http://www.test.com/foobar.php',
            $gen->getCurrentUrl(),
            'port 80 should not be shown',
        );
    }

    public function testGetCurrentURLPort8080(): void
    {
        $gen = Mockery::mock(UrlGenerator::class)->makePartial();
        $gen->shouldAllowMockingProtectedMethods();
        $gen->shouldReceive('getHttpProtocol')->andReturn('http');
        $gen->shouldReceive('getHttpHost')->andReturn('www.test.com:8080');
        $gen->shouldReceive('dropLinkedInParams')->andReturnUsing(static function ($arg)
        {
            return empty($arg) ? '' : '?' . $arg;
        });

        // test non default port 8080
        $_SERVER['REQUEST_URI'] = '/foobar.php';
        $this->assertEquals(
            'http://www.test.com:8080/foobar.php',
            $gen->getCurrentUrl(),
            'port 80 should not be shown',
        );
    }

    public function testHttpHost(): void
    {
        $real                             = 'foo.com';
        $_SERVER['HTTP_HOST']             = $real;
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'evil.com';
        $gen                              = new DummyUrlGenerator;
        $this->assertEquals($real, $gen->getHttpHost());
    }

    public function testHttpProtocolApache(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $gen              = new DummyUrlGenerator;
        $this->assertEquals('https', $gen->getHttpProtocol());
    }

    public function testHttpProtocolNginx(): void
    {
        $_SERVER['SERVER_PORT'] = '443';
        $gen                    = new DummyUrlGenerator;
        $this->assertEquals('https', $gen->getHttpProtocol());
    }

    public function testHttpHostForwarded(): void
    {
        $real                             = 'foo.com';
        $_SERVER['HTTP_HOST']             = 'localhost';
        $_SERVER['HTTP_X_FORWARDED_HOST'] = $real;
        $gen                              = new DummyUrlGenerator;
        $gen->setTrustForwarded(true);
        $this->assertEquals($real, $gen->getHttpHost());
    }

    public function testHttpProtocolForwarded(): void
    {
        $_SERVER['HTTPS']                  = 'on';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
        $gen                               = new DummyUrlGenerator;
        $gen->setTrustForwarded(true);
        $this->assertEquals('http', $gen->getHttpProtocol());
    }

    public function testHttpProtocolForwardedSecure(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $gen                               = new DummyUrlGenerator;
        $this->assertEquals('http', $gen->getHttpProtocol());

        $gen->setTrustForwarded(true);
        $this->assertEquals('https', $gen->getHttpProtocol());
    }
}

class DummyUrlGenerator extends UrlGenerator
{
    public function getHttpHost()
    {
        return parent::getHttpHost();
    }

    public function getHttpProtocol()
    {
        return parent::getHttpProtocol();
    }

    public function dropLinkedInParams($query)
    {
        return parent::dropLinkedInParams($query);
    }
}
