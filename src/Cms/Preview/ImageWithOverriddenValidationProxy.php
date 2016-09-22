<?php declare(strict_types = 1);

namespace Dms\Package\Content\Cms\Preview;

use Dms\Common\Structure\FileSystem\Image;
use Dms\Core\Model\Object\ClassDefinition;

/**
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ImageWithOverriddenValidationProxy extends Image
{
    /**
     * @var bool
     */
    private $valid;

    /**
     * Image constructor.
     *
     * @param string      $fullPath
     * @param string|null $clientFileName
     */
    public function __construct(string $fullPath, string $clientFileName = null, bool $valid)
    {
        parent::__construct($fullPath, $clientFileName);
        $this->valid = $valid;
    }

    /**
     * @inheritDoc
     */
    protected function define(ClassDefinition $class)
    {
        parent::define($class);

        $class->property($this->valid)->asBool();
    }

    /**
     * Returns whether the file is a valid image
     *
     * @return bool
     */
    public function isValidImage() : bool
    {
        return $this->valid;
    }


}