<?php declare(strict_types = 1);

namespace Dms\Package\Content\Core;

use Dms\Common\Structure\DateTime\DateTime;
use Dms\Core\Model\Object\ClassDefinition;
use Dms\Core\Model\Object\Entity;
use Dms\Core\Model\ValueObjectCollection;
use Dms\Core\Util\IClock;

/**
 * The content group entity.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentGroup extends Entity
{
    const NAMESPACE = 'namespace';
    const NAME = 'name';
    const HTML_CONTENT_AREAS = 'htmlContentAreas';
    const IMAGE_CONTENT_AREAS = 'imageContentAreas';
    const METADATA = 'metadata';
    const UPDATED_AT = 'updatedAt';

    /**
     * @var string
     */
    public $namespace;

    /**
     * @var string
     */
    public $name;

    /**
     * @var ValueObjectCollection|HtmlContentArea[]
     */
    public $htmlContentAreas;

    /**
     * @var ValueObjectCollection|ImageContentArea[]
     */
    public $imageContentAreas;

    /**
     * @var ValueObjectCollection|ContentMetadata[]
     */
    public $metadata;

    /**
     * @var DateTime
     */
    public $updatedAt;

    /**
     * ContentGroup constructor.
     *
     * @param string $namespace
     * @param string $name
     * @param IClock $clock
     */
    public function __construct(string $namespace, string $name, IClock $clock)
    {
        parent::__construct();

        $this->namespace         = $namespace;
        $this->name              = $name;
        $this->htmlContentAreas  = HtmlContentArea::collection();
        $this->imageContentAreas = ImageContentArea::collection();
        $this->metadata          = ContentMetadata::collection();
        $this->updatedAt         = new DateTime($clock->utcNow());
    }

    /**
     * Defines the structure of this entity.
     *
     * @param ClassDefinition $class
     */
    protected function defineEntity(ClassDefinition $class)
    {
        $class->property($this->namespace)->asString();

        $class->property($this->name)->asString();

        $class->property($this->htmlContentAreas)->asType(HtmlContentArea::collectionType());

        $class->property($this->imageContentAreas)->asType(ImageContentArea::collectionType());

        $class->property($this->metadata)->asType(ContentMetadata::collectionType());

        $class->property($this->updatedAt)->asObject(DateTime::class);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasHtml(string $name) : bool
    {
        return $this->htmlContentAreas->any(function (HtmlContentArea $contentArea) use ($name) {
            return $contentArea->name === $name;
        });
    }

    /**
     * @param string $name
     *
     * @return HtmlContentArea|null
     */
    public function getHtml(string $name)
    {
        return $this->htmlContentAreas->where(function (HtmlContentArea $contentArea) use ($name) {
            return $contentArea->name === $name;
        })->first();
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasImage(string $name) : bool
    {
        return $this->imageContentAreas->any(function (ImageContentArea $contentArea) use ($name) {
            return $contentArea->image->isValidImage() && $contentArea->name === $name;
        });
    }

    /**
     * @param string $name
     *
     * @return ImageContentArea|null
     */
    public function getImage(string $name)
    {
        /** @var ImageContentArea $contentArea */
        return $this->imageContentAreas->where(function (ImageContentArea $contentArea) use ($name) {
            return $contentArea->image->isValidImage() && $contentArea->name === $name;
        })->first();
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasMetadata(string $name) : bool
    {
        return $this->metadata->any(function (ContentMetadata $metadata) use ($name) {
            return $metadata->name === $name;
        });
    }

    /**
     * @param string $name
     *
     * @return ContentMetadata|null
     */
    public function getMetadata(string $name)
    {
        return $this->metadata->where(function (ContentMetadata $metadata) use ($name) {
            return $metadata->name === $name;
        })->first();
    }

}