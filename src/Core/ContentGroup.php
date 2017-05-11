<?php declare(strict_types = 1);

namespace Dms\Package\Content\Core;

use Dms\Common\Structure\DateTime\DateTime;
use Dms\Core\Model\EntityCollection;
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
    const ORDER_INDEX = 'orderIndex';
    const HTML_CONTENT_AREAS = 'htmlContentAreas';
    const IMAGE_CONTENT_AREAS = 'imageContentAreas';
    const TEXT_CONTENT_AREAS = 'textContentAreas';
    const METADATA = 'metadata';
    const NESTED_ARRAY_CONTENT_GROUPS = 'nestedArrayContentGroups';
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
     * @var int
     */
    public $orderIndex;

    /**
     * @var ValueObjectCollection|HtmlContentArea[]
     */
    public $htmlContentAreas;

    /**
     * @var ValueObjectCollection|ImageContentArea[]
     */
    public $imageContentAreas;

    /**
     * @var ValueObjectCollection|TextContentArea[]
     */
    public $textContentAreas;

    /**
     * @var ValueObjectCollection|ContentMetadata[]
     */
    public $metadata;

    /**
     * @var EntityCollection|ContentGroup[]
     */
    public $nestedArrayContentGroups;

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

        $this->namespace                = $namespace;
        $this->name                     = $name;
        $this->htmlContentAreas         = HtmlContentArea::collection();
        $this->imageContentAreas        = ImageContentArea::collection();
        $this->textContentAreas         = TextContentArea::collection();
        $this->metadata                 = ContentMetadata::collection();
        $this->nestedArrayContentGroups = ContentGroup::collection();
        $this->updatedAt                = new DateTime($clock->utcNow());
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
        
        $class->property($this->orderIndex)->asInt();

        $class->property($this->htmlContentAreas)->asType(HtmlContentArea::collectionType());

        $class->property($this->imageContentAreas)->asType(ImageContentArea::collectionType());

        $class->property($this->textContentAreas)->asType(TextContentArea::collectionType());

        $class->property($this->metadata)->asType(ContentMetadata::collectionType());

        $class->property($this->nestedArrayContentGroups)->asType(ContentGroup::collectionType());

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

    /**
     * @param string $name
     *
     * @return TextContentArea|null
     */
    public function getText(string $name)
    {
        return $this->textContentAreas->where(function (TextContentArea $contentArea) use ($name) {
            return $contentArea->name === $name;
        })->first();
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasText(string $name) : bool
    {
        return $this->textContentAreas->any(function (TextContentArea $contentArea) use ($name) {
            return $contentArea->name === $name;
        });
    }

    /**
     * @param string $name
     *
     * @return ContentGroup[]
     */
    public function getArrayOf(string $name) : array
    {
        return $this->nestedArrayContentGroups
            ->where(function (ContentGroup $contentArea) use ($name) {
                return $contentArea->name === $name;
            })
            ->reindex()
            ->asArray();
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasArrayOf(string $name) : bool
    {
        return $this->nestedArrayContentGroups
            ->where(function (ContentGroup $contentArea) use ($name) {
                return $contentArea->name === $name;
            })
            ->count() > 0;
    }

    /**
     * @return ContentGroup[][]
     */
    public function getAllArrayGroups() : array
    {
        return $this->nestedArrayContentGroups
            ->groupBy(function (ContentGroup $contentArea) {
                return $contentArea->name;
            })
            ->select(function ($contentGroups) {
                return $contentGroups->asArray();
            })
            ->asArray();
    }

    /**
     * @return string
     */
    public function getHash() : string
    {
        return md5(serialize($this));
    }
}