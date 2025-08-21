<?php

namespace Elnino\LinkedIn\Tests\Http;

use Elnino\LinkedIn\Http\ResponseConverter;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponseConverter::class)]
class ResponseConverterTest extends TestCase
{
    public function testConvert()
    {
        $body = '{"foo":"bar"}';
        $response = new Response(200, [], $body);

        $result = ResponseConverter::convert($response, 'json', 'psr7');
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $result);

        $result = ResponseConverter::convert($response, 'json', 'stream');
        $this->assertInstanceOf('Psr\Http\Message\StreamInterface', $result);

        $result = ResponseConverter::convert($response, 'json', 'string');
        $this->assertTrue(is_string($result));
        $this->assertEquals($body, $result);

        $result = ResponseConverter::convert($response, 'json', 'array');
        $this->assertTrue(is_array($result));

        $body = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<person>
  <firstname>foo</firstname>
  <lastname>bar</lastname>
</person>
';
        $response = new Response(200, [], $body);
        $result = ResponseConverter::convert($response, 'xml', 'simple_xml');
        $this->assertInstanceOf('\SimpleXMLElement', $result);
    }

    public function testConvertJsonToSimpleXml()
    {
        $this->expectException(\Elnino\LinkedIn\Exception\InvalidArgumentException::class);
        $body = '{"foo":"bar"}';
        $response = new Response(200, [], $body);

        ResponseConverter::convert($response, 'json', 'simple_xml');
    }

    public function testConvertXmlToArray()
    {
        $this->expectException(\Elnino\LinkedIn\Exception\InvalidArgumentException::class);
        $body = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<person>
  <firstname>foo</firstname>
  <lastname>bar</lastname>
</person>
';
        $response = new Response(200, [], $body);

        ResponseConverter::convert($response, 'xml', 'array');
    }

    public function testConvertJsonToFoobar()
    {
        $this->expectException(\Elnino\LinkedIn\Exception\InvalidArgumentException::class);
        $body = '{"foo":"bar"}';
        $response = new Response(200, [], $body);

        ResponseConverter::convert($response, 'json', 'foobar');
    }

    public function testConvertToSimpleXml()
    {
        $body = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<person>
  <firstname>foo</firstname>
  <lastname>bar</lastname>
</person>
';

        $response = new Response(200, [], $body);
        $result = ResponseConverter::convertToSimpleXml($response);

        $this->assertInstanceOf('\SimpleXMLElement', $result);
        $this->assertEquals('foo', $result->firstname);
    }

    public function testConvertToSimpleXmlError()
    {
        $this->expectException(\Elnino\LinkedIn\Exception\LinkedInTransferException::class);
        $body = '{Foo: bar}';

        $response = new Response(200, [], $body);
        ResponseConverter::convertToSimpleXml($response);
    }
}
