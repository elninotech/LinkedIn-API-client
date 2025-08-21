<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Tests;

use function http_build_query;
use function json_encode;
use Elnino\LinkedIn\AccessToken;
use Elnino\LinkedIn\Authenticator;
use Elnino\LinkedIn\Exception\LinkedInException;
use Elnino\LinkedIn\Http\GlobalVariableGetter;
use Elnino\LinkedIn\Http\LinkedInUrlGeneratorInterface;
use Elnino\LinkedIn\Http\RequestManager;
use Elnino\LinkedIn\Http\ResponseConverter;
use Elnino\LinkedIn\Http\UrlGenerator;
use Elnino\LinkedIn\Storage\DataStorageInterface;
use Elnino\LinkedIn\Storage\SessionStorage;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionMethod;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
#[CoversClass(Authenticator::class)]
#[UsesClass(AccessToken::class)]
#[UsesClass(GlobalVariableGetter::class)]
#[UsesClass(ResponseConverter::class)]
#[UsesClass(SessionStorage::class)]
class AuthenticatorTest extends MockeryTestCase
{
    public const APP_ID     = '123456789';
    public const APP_SECRET = '987654321';

    public function testGetLoginUrl(): void
    {
        $expected = 'loginUrl';
        $state    = 'random';
        $params   = [
            'response_type' => 'code',
            'client_id'     => self::APP_ID,
            'redirect_uri'  => null,
            'state'         => $state,
        ];

        $storage = Mockery::mock(DataStorageInterface::class);
        $storage->shouldReceive('get')->with('state')->andReturn($state);
        $storage->shouldReceive('set')->twice();

        $auth = Mockery::mock(Authenticator::class, [$this->getRequestManagerMock(), self::APP_ID, self::APP_SECRET])->makePartial();
        $auth->shouldAllowMockingProtectedMethods();
        $auth->shouldReceive('establishCSRFTokenState')->twice()->andReturn(null);
        $auth->shouldReceive('getStorage')->andReturn($storage);

        $generator = Mockery::mock(LinkedInUrlGeneratorInterface::class)
            ->shouldReceive('getUrl')->once()->with('www', 'oauth/v2/authorization', $params)->andReturn($expected)
            ->getMock();

        $this->assertEquals($expected, $auth->getLoginUrl($generator));

        /*
         * Test with a url in the param
         */
        $otherUrl = 'otherUrl';
        $scope    = ['foo', 'bar', 'baz'];
        $params   = [
            'response_type' => 'code',
            'client_id'     => self::APP_ID,
            'redirect_uri'  => $otherUrl,
            'state'         => $state,
            'scope'         => 'foo bar baz',
        ];

        $generator = Mockery::mock(LinkedInUrlGeneratorInterface::class)
            ->shouldReceive('getUrl')->once()->with('www', 'oauth/v2/authorization', $params)->andReturn($expected)
            ->getMock();

        $this->assertEquals($expected, $auth->getLoginUrl($generator, ['redirect_uri' => $otherUrl, 'scope' => $scope]));
    }

    public function testFetchNewAccessToken(): void
    {
        $generator = Mockery::mock(UrlGenerator::class);
        $code      = 'newCode';
        $storage   = Mockery::mock(DataStorageInterface::class)
            ->shouldReceive('set')->once()->with('code', $code)
            ->shouldReceive('set')->once()->with('access_token', 'at')
            ->getMock();

        $auth = Mockery::mock(Authenticator::class)->makePartial();
        $auth->shouldAllowMockingProtectedMethods();
        $auth->shouldReceive('getStorage')->andReturn($storage);
        $auth->shouldReceive('getAccessTokenFromCode')->once()->with($generator, $code)->andReturn('at');
        $auth->shouldReceive('getCode')->once()->andReturn($code);

        $this->assertEquals('at', $auth->fetchNewAccessToken($generator));
    }

    public function testFetchNewAccessTokenFail(): void
    {
        $this->expectException(LinkedInException::class);
        $generator = Mockery::mock(UrlGenerator::class);
        $code      = 'newCode';
        $storage   = Mockery::mock(DataStorageInterface::class)
            ->shouldReceive('clearAll')->once()
            ->getMock();

        $auth = Mockery::mock(Authenticator::class)->makePartial();
        $auth->shouldAllowMockingProtectedMethods();
        $auth->shouldReceive('getStorage')->andReturn($storage);
        $auth->shouldReceive('getAccessTokenFromCode')->once()->with($generator, $code)->andThrowExceptions([new LinkedInException]);
        $auth->shouldReceive('getCode')->once()->andReturn($code);

        $auth->fetchNewAccessToken($generator);
    }

    public function testFetchNewAccessTokenNoCode(): void
    {
        $generator = Mockery::mock(UrlGenerator::class);
        $storage   = Mockery::mock(DataStorageInterface::class)
            ->shouldReceive('get')->with('code')->andReturn('foobar')
            ->shouldReceive('get')->once()->with('access_token')->andReturn('baz')
            ->getMock();

        $auth = Mockery::mock(Authenticator::class)->makePartial();
        $auth->shouldAllowMockingProtectedMethods();
        $auth->shouldReceive('getStorage')->andReturn($storage);
        $auth->shouldReceive('getCode')->once();

        $this->assertEquals('baz', $auth->fetchNewAccessToken($generator));
    }

    public function testGetAccessTokenFromCodeEmptyString(): void
    {
        $this->expectException(LinkedInException::class);
        $generator = Mockery::mock(UrlGenerator::class);

        $method = new ReflectionMethod(Authenticator::class, 'getAccessTokenFromCode');
        $method->setAccessible(true);
        $auth = Mockery::mock(Authenticator::class)->makePartial();

        $method->invoke($auth, $generator, '');
    }

    public function testGetAccessTokenFromCodeNull(): void
    {
        $this->expectException(LinkedInException::class);
        $generator = Mockery::mock(UrlGenerator::class);

        $method = new ReflectionMethod(Authenticator::class, 'getAccessTokenFromCode');
        $method->setAccessible(true);
        $auth = Mockery::mock(Authenticator::class)->makePartial();

        $method->invoke($auth, $generator, null);
    }

    public function testGetAccessTokenFromCodeFalse(): void
    {
        $this->expectException(LinkedInException::class);
        $generator = Mockery::mock(UrlGenerator::class);

        $method = new ReflectionMethod(Authenticator::class, 'getAccessTokenFromCode');
        $method->setAccessible(true);
        $auth = Mockery::mock(Authenticator::class)->makePartial();

        $method->invoke($auth, $generator, false);
    }

    public function testGetAccessTokenFromCode(): void
    {
        $method = new ReflectionMethod(Authenticator::class, 'getAccessTokenFromCode');
        $method->setAccessible(true);

        $code      = 'code';
        $generator = Mockery::mock(UrlGenerator::class)
            ->shouldReceive('getUrl')->with(
                'www',
                'oauth/v2/accessToken',
            )->andReturn('url')
            ->getMock();

        $response = ['access_token' => 'foobar', 'expires_in' => 10];
        $auth     = $this->prepareGetAccessTokenFromCode($code, $response);
        $token    = $method->invoke($auth, $generator, $code);
        $this->assertEquals('foobar', $token, 'Standard get access token form code');
    }

    public function testGetAccessTokenFromCodeNoTokenInResponse(): void
    {
        $this->expectException(LinkedInException::class);
        $method = new ReflectionMethod(Authenticator::class, 'getAccessTokenFromCode');
        $method->setAccessible(true);

        $code      = 'code';
        $generator = Mockery::mock(UrlGenerator::class)
            ->shouldReceive('getUrl')->with(
                'www',
                'oauth/v2/accessToken',
            )->andReturn('url')
            ->getMock();

        $response = ['foo' => 'bar'];
        $auth     = $this->prepareGetAccessTokenFromCode($code, $response);
        $this->assertNull($method->invoke($auth, $generator, $code), 'Found array but no access token');
    }

    public function testGetAccessTokenFromCodeEmptyResponse(): void
    {
        $this->expectException(LinkedInException::class);
        $method = new ReflectionMethod(Authenticator::class, 'getAccessTokenFromCode');
        $method->setAccessible(true);

        $code      = 'code';
        $generator = Mockery::mock(UrlGenerator::class)
            ->shouldReceive('getUrl')->with(
                'www',
                'oauth/v2/accessToken',
            )->andReturn('url')
            ->getMock();

        $response = '';
        $auth     = $this->prepareGetAccessTokenFromCode($code, $response);
        $this->assertNull($method->invoke($auth, $generator, $code), 'Empty result');
    }

    public function testEstablishCSRFTokenState(): void
    {
        $method = new ReflectionMethod(Authenticator::class, 'establishCSRFTokenState');
        $method->setAccessible(true);

        $storage = Mockery::mock(DataStorageInterface::class)
            ->shouldReceive('get')->with('state')->andReturn(null, 'state')
            ->shouldReceive('set')->once()->with('state', Mockery::on(static function (&$param)
            {
                return !empty($param);
            }))
            ->getMock();

        $auth = Mockery::mock(Authenticator::class)->makePartial();
        $auth->shouldAllowMockingProtectedMethods();
        $auth->shouldReceive('getStorage')->andReturn($storage);

        // Make sure we only set the state once
        $method->invoke($auth);
        $method->invoke($auth);
    }

    public function testGetCodeEmpty(): void
    {
        unset($_REQUEST['code'], $_GET['code']);

        $method = new ReflectionMethod(Authenticator::class, 'getCode');
        $method->setAccessible(true);
        $auth = Mockery::mock(Authenticator::class)->makePartial();

        $this->assertNull($method->invoke($auth));
    }

    public function testGetCode(): void
    {
        $method = new ReflectionMethod(Authenticator::class, 'getCode');
        $method->setAccessible(true);
        $state = 'bazbar';

        $storage = Mockery::mock(DataStorageInterface::class)
            ->shouldReceive('clear')->once()->with('state')
            ->shouldReceive('get')->once()->with('code')->andReturn(null)
            ->shouldReceive('get')->once()->with('state')->andReturn($state)
            ->getMock();

        $auth = Mockery::mock(Authenticator::class)->makePartial();
        $auth->shouldAllowMockingProtectedMethods();
        $auth->shouldReceive('getStorage')->once()->andReturn($storage);

        $_REQUEST['code']  = 'foobar';
        $_REQUEST['state'] = $state;

        $this->assertEquals('foobar', $method->invoke($auth));
    }

    public function testGetCodeInvalidCode(): void
    {
        $this->expectException(LinkedInException::class);
        $method = new ReflectionMethod(Authenticator::class, 'getCode');
        $method->setAccessible(true);

        $storage = Mockery::mock(DataStorageInterface::class)
            ->shouldReceive('get')->once()->with('code')->andReturn(null)
            ->shouldReceive('get')->once()->with('state')->andReturn('bazbar')
            ->getMock();

        $auth = Mockery::mock(Authenticator::class)->makePartial();
        $auth->shouldAllowMockingProtectedMethods();
        $auth->shouldReceive('getStorage')->once()->andReturn($storage);

        $_REQUEST['code']  = 'foobar';
        $_REQUEST['state'] = 'invalid';

        $this->assertEquals('foobar', $method->invoke($auth));
    }

    public function testGetCodeUsedCode(): void
    {
        $method = new ReflectionMethod(Authenticator::class, 'getCode');
        $method->setAccessible(true);

        $storage = Mockery::mock(DataStorageInterface::class)
            ->shouldReceive('get')->once()->with('code')->andReturn('foobar')
            ->getMock();

        $auth = Mockery::mock(Authenticator::class)->makePartial();
        $auth->shouldAllowMockingProtectedMethods();
        $auth->shouldReceive('getStorage')->once()->andReturn($storage);

        $_REQUEST['code'] = 'foobar';

        $this->assertEquals(null, $method->invoke($auth));
    }

    public function testStorageAccessors(): void
    {
        $method = new ReflectionMethod(Authenticator::class, 'getStorage');
        $method->setAccessible(true);
        $requestManager = $this->getRequestManagerMock();
        $auth           = new Authenticator($requestManager, self::APP_ID, self::APP_SECRET);

        // test default
        $this->assertInstanceOf(SessionStorage::class, $method->invoke($auth));

        $object = Mockery::mock(DataStorageInterface::class);
        $auth->setStorage($object);
        $this->assertEquals($object, $method->invoke($auth));
    }

    /**
     * Default stuff for GetAccessTokenFromCode.
     *
     * @return array
     */
    protected function prepareGetAccessTokenFromCode($code, $responseData)
    {
        $response   = new Response(200, [], json_encode($responseData));
        $currentUrl = 'foobar';

        $storage = Mockery::mock(DataStorageInterface::class)
            ->shouldReceive('get')->with('redirect_uri')->andReturn($currentUrl)
            ->getMock();

        $requestManager = Mockery::mock(RequestManager::class)
            ->shouldReceive('sendRequest')->once()->with('POST', 'url', [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ], http_build_query([
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $currentUrl,
                'client_id'     => self::APP_ID,
                'client_secret' => self::APP_SECRET,
            ]))->andReturn($response)
            ->getMock();

        $auth = Mockery::mock(Authenticator::class, [$requestManager, self::APP_ID, self::APP_SECRET])->makePartial();
        $auth->shouldAllowMockingProtectedMethods();
        $auth->shouldReceive('getStorage')->andReturn($storage);

        return $auth;
    }

    private function getRequestManagerMock()
    {
        return Mockery::mock(RequestManager::class);
    }
}
