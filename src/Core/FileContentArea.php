<?php declare(strict_types = 1);

namespace Dms\Package\Content\Core;

use Dms\Common\Structure\FileSystem\File;
use Dms\Core\Model\Object\ClassDefinition;
use Dms\Core\Model\Object\ValueObject;

/**
 * The file metadata value object.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class FileContentArea extends ValueObject
{
    const NAME = 'name';
    const FILE = 'file';
    
    /**
     * @var string
     */
    public $name;

    /**
     * @var File
     */
    public $file;

    /**
     * FileContentArea constructor.
     *
     * @param string $name
     * @param File   $file
     */
    public function __construct(string $name, File $file)
    {
        parent::__construct();
        $this->name = $name;
        $this->file = $file;
    }


    /**
     * Defines the structure of this class.
     *
     * @param ClassDefinition $class
     */
    protected function define(ClassDefinition $class)
    {
        $class->property($this->name)->asString();

        $class->property($this->file)->asObject(File::class);
    }
}