<?php declare(strict_types = 1);

namespace Dms\Package\Content\Core;

use Dms\Common\Structure\Web\Html;
use Dms\Core\Model\Object\ClassDefinition;
use Dms\Core\Model\Object\ValueObject;

/**
 * The html content area value object.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class HtmlContentArea extends ValueObject
{
    const NAME = 'name';
    const HTML = 'html';
    
    /**
     * @var string
     */
    public $name;

    /**
     * @var Html
     */
    public $html;
    
    /**
     * HtmlContentArea constructor.
     *
     * @param string $name
     * @param Html   $html
     */
    public function __construct(string $name, Html $html)
    {
        parent::__construct();
        $this->name = $name;
        $this->html = $html;
    }

    /**
     * Defines the structure of this class.
     *
     * @param ClassDefinition $class
     */
    protected function define(ClassDefinition $class)
    {
        $class->property($this->name)->asString();

        $class->property($this->html)->asObject(Html::class);
    }
}