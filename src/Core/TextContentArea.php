<?php declare(strict_types = 1);

namespace Dms\Package\Content\Core;

use Dms\Core\Model\Object\ClassDefinition;
use Dms\Core\Model\Object\ValueObject;

/**
 * The text content area value object.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class TextContentArea extends ValueObject
{
    const NAME = 'name';
    const TEXT = 'text';

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $text;

    /**
     * TextContentArea constructor.
     *
     * @param string $name
     * @param string $text
     */
    public function __construct(string $name, string $text)
    {
        parent::__construct();
        $this->name = $name;
        $this->text = $text;
    }

    /**
     * Defines the structure of this class.
     *
     * @param ClassDefinition $class
     */
    protected function define(ClassDefinition $class)
    {
        $class->property($this->name)->asString();

        $class->property($this->text)->asString();
    }
}