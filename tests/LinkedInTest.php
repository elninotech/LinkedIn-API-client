<?php

namespace Elnino\LinkedIn\Tests;

use Elnino\LinkedIn\Authenticator;
use Elnino\LinkedIn\Http\RequestManager;
use Elnino\LinkedIn\Http\UrlGenerator;
use Elnino\LinkedIn\LinkedIn;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
#[CoversClass(LinkedIn::class)]
class LinkedInTest extends MockeryTestCase
{
    public const APP_ID = '123456789';
    public const APP_SECRET = '987654321';

    public function testApi()
    {
        $resource = 'resource';
        $token = 'token';
        $urlParams = ['url' => 'foo'];
        $postParams = ['post' => 'bar'];
        $method = 'GET';
        $expected = ['foobar' => 'test'];
        $response = new Response(200, [], json_encode($expected));
        $url = 'http://example.com/test';

        $headers = ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json', 'x-li-format' => 'json'];

        $generator = Mockery::mock(UrlGenerator::class);
        $generator->shouldReceive('getUrl')->once()->with(
            $this->equalTo('api'),
            $this->equalTo($resource),
            $this->equalTo([
                'url' => 'foo',
                'format' => 'json',
            ])
        )
            ->andReturn($url);

        $requestManager = Mockery::mock(RequestManager::class);
        $requestManager->shouldReceive('sendRequest')->once()->with(
            $this->equalTo($method),
            $this->equalTo($url),
            $this->equalTo($headers),
            $this->equalTo(json_encode($postParams))
        )
            ->andReturn($response);

        $linkedIn = Mockery::mock(LinkedIn::class, [self::APP_ID, self::APP_SECRET])->makePartial();
        $linkedIn->shouldAllowMockingProtectedMethods();
        $linkedIn->shouldReceive('getAccessToken')->once()->andReturn($token);
        $linkedIn->shouldReceive('getUrlGenerator')->once()->andReturn($generator);
        $linkedIn->shouldReceive('getRequestManager')->once()->andReturn($requestManager);

        $result = $linkedIn->api($method, $resource, ['query' => $urlParams, 'json' => $postParams]);
        $this->assertEquals($expected, $result);
    }

    public function testIsAuthenticated()
    {
        $linkedIn = Mockery::mock(LinkedIn::class, [self::APP_ID, self::APP_SECRET])->makePartial();
        $linkedIn->shouldReceive('getAccessToken')->once()->andReturn(null);
        $this->assertFalse($linkedIn->isAuthenticated());

        $linkedIn = Mockery::mock(LinkedIn::class, [self::APP_ID, self::APP_SECRET])->makePartial();
        $linkedIn->shouldReceive('getAccessToken')->once()->andReturn('token');
        $linkedIn->shouldReceive('api')->once()->andReturn(['id' => 4711]);
        $this->assertTrue($linkedIn->isAuthenticated());

        $linkedIn = Mockery::mock(LinkedIn::class, [self::APP_ID, self::APP_SECRET])->makePartial();
        $linkedIn->shouldReceive('getAccessToken')->once()->andReturn('token');
        $linkedIn->shouldReceive('api')->once()->andReturn(['foobar' => 4711]);
        $this->assertFalse($linkedIn->isAuthenticated());
    }

    /**
     * Test a call to getAccessToken when there is no token.
     */
    public function testAccessTokenAccessors()
    {
        $token = 'token';

        $auth = Mockery::mock(Authenticator::class)->makePartial();
        $auth->shouldReceive('fetchNewAccessToken')->once()->andReturn($token);

        $linkedIn = Mockery::mock(LinkedIn::class)->makePartial();
        $linkedIn->shouldAllowMockingProtectedMethods();
        $linkedIn->shouldReceive('getAuthenticator')->once()->andReturn($auth);

        // Make sure we go to the authenticator only once
        $this->assertEquals($token, $linkedIn->getAccessToken());
        $this->assertEquals($token, $linkedIn->getAccessToken());
    }

    public function testGeneratorAccessors()
    {
        $get = new \ReflectionMethod(LinkedIn::class, 'getUrlGenerator');
        $get->setAccessible(true);
        $linkedIn = new LinkedIn(self::APP_ID, self::APP_SECRET);

        // test default
        $this->assertInstanceOf(UrlGenerator::class, $get->invoke($linkedIn));

        $object = Mockery::mock(UrlGenerator::class);
        $linkedIn->setUrlGenerator($object);
        $this->assertEquals($object, $get->invoke($linkedIn));
    }

    public function testHasError()
    {
        $linkedIn = new LinkedIn(self::APP_ID, self::APP_SECRET);

        unset($_GET['error']);
        $this->assertFalse($linkedIn->hasError());

        $_GET['error'] = 'foobar';
        $this->assertTrue($linkedIn->hasError());
    }

    public function testGetError()
    {
        $linkedIn = new LinkedIn(self::APP_ID, self::APP_SECRET);

        unset($_GET['error']);
        unset($_GET['error_description']);

        $this->assertNull($linkedIn->getError());

        $_GET['error'] = 'foo';
        $_GET['error_description'] = 'bar';

        $this->assertEquals('foo', $linkedIn->getError()->getName());
        $this->assertEquals('bar', $linkedIn->getError()->getDescription());
    }

    public function testGetErrorWithMissingDescription()
    {
        $linkedIn = new LinkedIn(self::APP_ID, self::APP_SECRET);

        unset($_GET['error']);
        unset($_GET['error_description']);

        $_GET['error'] = 'foo';

        $this->assertEquals('foo', $linkedIn->getError()->getName());
        $this->assertNull($linkedIn->getError()->getDescription());
    }

    public function testFormatAccessors()
    {
        $get = new \ReflectionMethod(LinkedIn::class, 'getFormat');
        $get->setAccessible(true);
        $linkedIn = new LinkedIn(self::APP_ID, self::APP_SECRET);

        //test default
        $this->assertEquals('json', $get->invoke($linkedIn));

        $format = 'foo';
        $linkedIn->setFormat($format);
        $this->assertEquals($format, $get->invoke($linkedIn));
    }

    public function testLoginUrl()
    {
        $currentUrl = 'currentUrl';
        $loginUrl = 'result';

        $generator = Mockery::mock(UrlGenerator::class)->makePartial();
        $generator->shouldReceive('getCurrentUrl')->once()->andReturn($currentUrl);

        $auth = Mockery::mock(Authenticator::class)->makePartial();
        $auth->shouldReceive('getLoginUrl')->once()
            ->with($generator, ['redirect_uri' => $currentUrl])
            ->andReturn($loginUrl);

        $linkedIn = Mockery::mock(LinkedIn::class)->makePartial();
        $linkedIn->shouldAllowMockingProtectedMethods();
        $linkedIn->shouldReceive('getAuthenticator')->once()->andReturn($auth);
        $linkedIn->shouldReceive('getUrlGenerator')->once()->andReturn($generator);

        $linkedIn->getLoginUrl();
    }

    public function testLoginUrlWithParameter()
    {
        $loginUrl = 'result';
        $otherUrl = 'otherUrl';

        $generator = Mockery::mock(UrlGenerator::class);

        $auth = Mockery::mock(Authenticator::class)->makePartial();
        $auth->shouldReceive('getLoginUrl')->once()
            ->with($generator, ['redirect_uri' => $otherUrl])
            ->andReturn($loginUrl);

        $linkedIn = Mockery::mock(LinkedIn::class)->makePartial();
        $linkedIn->shouldAllowMockingProtectedMethods();
        $linkedIn->shouldReceive('getAuthenticator')->once()->andReturn($auth);
        $linkedIn->shouldReceive('getUrlGenerator')->once()->andReturn($generator);

        $linkedIn->getLoginUrl(['redirect_uri' => $otherUrl]);
    }
}
