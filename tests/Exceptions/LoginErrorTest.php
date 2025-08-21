<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Tests\Exceptions;

use Elnino\LinkedIn\Exception\LoginError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class LoginErrorTest.
 *
 * @author Tobias Nyholm
 */
#[CoversClass(LoginError::class)]
class LoginErrorTest extends TestCase
{
    public function testGetters(): void
    {
        $error = new LoginError('foo', 'bar');

        $this->assertEquals('foo', $error->getName());
        $this->assertEquals('bar', $error->getDescription());
    }
}
