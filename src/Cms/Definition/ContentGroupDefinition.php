<?php declare(strict_types = 1);

namespace Dms\Package\Content\Cms\Definition;

/**
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentGroupDefinition
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $label;

    /**
     * @var array
     */
    public $images = [];

    /**
     * @var array
     */
    public $htmlAreas = [];

    /**
     * @var array
     */
    public $textAreas = [];

    /**
     * @var array
     */
    public $metadata = [];

    /**
     * @var callable|null
     */
    public $previewCallback;

    /**
     * ContentGroupDefinition constructor.
     *
     * @param string $name
     * @param string $label
     */
    public function __construct(string $name, string $label)
    {
        $this->name  = $name;
        $this->label = $label;
    }
}