<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Http;

use function json_decode;
use Elnino\LinkedIn\Exception\InvalidArgumentException;
use Elnino\LinkedIn\Exception\LinkedInTransferException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class ResponseConverter
{
    /**
     * Convert a PSR-7 response to a data type you want to work with.
     *
     * @param string $dataType
     *
     * @throws InvalidArgumentException
     * @throws LinkedInTransferException
     *
     * @return mixed[]|ResponseInterface|StreamInterface|string
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
     * @return mixed[]
     */
    public static function convertToArray(ResponseInterface $response)
    {
        return json_decode((string) $response->getBody(), true);
    }
}
