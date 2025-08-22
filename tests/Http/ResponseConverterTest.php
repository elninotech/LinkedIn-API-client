<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Tests\Http;

use function is_array;
use function is_string;
use Elnino\LinkedIn\Exception\InvalidArgumentException;
use Elnino\LinkedIn\Http\ResponseConverter;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponseConverter::class)]
#[UsesClass(InvalidArgumentException::class)]
class ResponseConverterTest extends TestCase
{
    public function testConvert(): void
    {
        $body     = '{"foo":"bar"}';
        $response = new Response(200, [], $body);

        $result = ResponseConverter::convert($response, 'psr7');
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $result);

        $result = ResponseConverter::convert($response, 'stream');
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $result);

        $result = ResponseConverter::convert($response, 'string');
        $this->assertTrue(is_string($result));
        $this->assertEquals($body, $result);

        $result = ResponseConverter::convert($response, 'array');
        $this->assertTrue(is_array($result));
    }

    public function testConvertJsonToFoobar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $body     = '{"foo":"bar"}';
        $response = new Response(200, [], $body);

        ResponseConverter::convert($response, 'foobar');
    }
}
