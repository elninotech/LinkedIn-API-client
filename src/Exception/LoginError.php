<?php declare(strict_types=1);
namespace Elnino\LinkedIn\Exception;

use function sprintf;

/**
 * Class LoginError.
 *
 * @author Tobias Nyholm
 */
class LoginError
{
    /**
     * @var string name
     */
    protected $name;

    /**
     * @var string description
     */
    protected $description;

    /**
     * @param string $name
     * @param string $description
     */
    public function __construct($name, $description)
    {
        $this->name        = $name;
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('Name: %s, Description: %s', $this->getName(), $this->getDescription());
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }
}
