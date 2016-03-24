<?php declare(strict_types = 1);

namespace Dms\Package\Content\Core;

use Dms\Common\Structure\FileSystem\Image;
use Dms\Core\Model\Object\ClassDefinition;
use Dms\Core\Model\Object\ValueObject;

/**
 * The image content area value object.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ImageContentArea extends ValueObject
{
    const NAME = 'name';
    const IMAGE = 'image';
    const ALT_TEXT = 'altText';

    /**
     * @var string
     */
    public $name;

    /**
     * @var Image
     */
    public $image;

    /**
     * @var string|null
     */
    public $altText;

    /**
     * ImageContentArea constructor.
     *
     * @param string      $name
     * @param Image       $image
     * @param string|null $altText
     */
    public function __construct(string $name, Image $image, string $altText = null)
    {
        parent::__construct();
        $this->name    = $name;
        $this->image   = $image;
        $this->altText = $altText;
    }

    /**
     * Defines the structure of this class.
     *
     * @param ClassDefinition $class
     */
    protected function define(ClassDefinition $class)
    {
        $class->property($this->name)->asString();

        $class->property($this->image)->asObject(Image::class);

        $class->property($this->altText)->nullable()->asString();
    }
}