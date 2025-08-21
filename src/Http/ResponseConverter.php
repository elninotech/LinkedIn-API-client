<?php

namespace Elnino\LinkedIn\Http;

use Elnino\LinkedIn\Exception\InvalidArgumentException;
use Elnino\LinkedIn\Exception\LinkedInTransferException;
use Psr\Http\Message\ResponseInterface;

class ResponseConverter
{
    /**
     * Convert a PSR-7 response to a data type you want to work with.
     *
     * @param ResponseInterface $response
     * @param string            $dataType
     *
     * @return ResponseInterface|\Psr\Http\Message\StreamInterface|string
     *
     * @throws InvalidArgumentException
     * @throws LinkedInTransferException
     */
    public static function convert(ResponseInterface $response, $dataType)
    {
        switch ($dataType) {
            case 'array':
                return self::convertToArray($response);
            case 'string':
                return $response->getBody()->__toString();
            case 'stream':
                return $response->getBody();
            case 'psr7':
                return $response;
            default:
                throw new InvalidArgumentException('Format "%s" is not supported', $dataType);
        }
    }

    /**
     * @param ResponseInterface $response
     *
     * @return string
     */
    public static function convertToArray(ResponseInterface $response)
    {
        return json_decode($response->getBody(), true);
    }
}
