<?php declare(strict_types = 1);

namespace Dms\Package\Content\Core;

use Dms\Core\Model\Object\ClassDefinition;
use Dms\Core\Model\Object\ValueObject;

/**
 * The content metadata value object.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentMetadata extends ValueObject
{
    const NAME = 'name';
    const VALUE = 'value';
    
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $value;

    /**
     * ContentMetadata constructor.
     *
     * @param string $name
     * @param string $value
     */
    public function __construct(string $name, string $value)
    {
        parent::__construct();
        $this->name  = $name;
        $this->value = $value;
    }

    /**
     * Defines the structure of this class.
     *
     * @param ClassDefinition $class
     */
    protected function define(ClassDefinition $class)
    {
        $class->property($this->name)->asString();

        $class->property($this->value)->asString();
    }
}