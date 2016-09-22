<?php declare(strict_types = 1);

namespace Dms\Package\Content\Persistence;

use Dms\Common\Structure\DateTime\Persistence\DateTimeMapper;
use Dms\Common\Structure\FileSystem\Persistence\ImageMapper;
use Dms\Common\Structure\Web\Persistence\HtmlMapper;
use Dms\Core\Persistence\Db\Mapping\Definition\MapperDefinition;
use Dms\Core\Persistence\Db\Mapping\EntityMapper;
use Dms\Core\Persistence\Db\Mapping\IOrm;
use Dms\Package\Content\Core\ContentConfig;
use Dms\Package\Content\Core\ContentGroup;
use Dms\Package\Content\Core\ContentMetadata;
use Dms\Package\Content\Core\HtmlContentArea;
use Dms\Package\Content\Core\ImageContentArea;
use Dms\Package\Content\Core\TextContentArea;

/**
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentGroupMapper extends EntityMapper
{
    /**
     * @var ContentConfig
     */
    protected $contentConfig;

    /**
     * ContentGroupMapper constructor.
     *
     * @param IOrm          $orm
     * @param ContentConfig $contentConfig
     */
    public function __construct(IOrm $orm, ContentConfig $contentConfig)
    {
        $this->contentConfig = $contentConfig;
        parent::__construct($orm);
    }

    /**
     * Defines the entity mapper
     *
     * @param MapperDefinition $map
     *
     * @return void
     */
    protected function define(MapperDefinition $map)
    {
        $map->type(ContentGroup::class);
        $map->toTable('content_groups');

        $map->idToPrimaryKey('id');

        $map->column('parent_id')->nullable()->asUnsignedInt();
        $map->property(ContentGroup::NAMESPACE)->to('namespace')->asVarchar(255);
        $map->property(ContentGroup::NAME)->to('name')->asVarchar(255);

        $map->embeddedCollection(ContentGroup::HTML_CONTENT_AREAS)
            ->toTable('content_group_html_areas')
            ->withPrimaryKey('id')
            ->withForeignKeyToParentAs('content_group_id')
            ->usingCustom(function (MapperDefinition $map) {
                $map->type(HtmlContentArea::class);

                $map->property(HtmlContentArea::NAME)->to('name')->asVarchar(100);
                $map->embedded(HtmlContentArea::HTML)
                    ->using(new HtmlMapper('html'));
                $map->column('content_group_id')->asUnsignedInt();

                $map->unique('content_group_html_unique_index')
                    ->on(['content_group_id', 'name']);
            });

        $map->embeddedCollection(ContentGroup::IMAGE_CONTENT_AREAS)
            ->toTable('content_group_image_areas')
            ->withPrimaryKey('id')
            ->withForeignKeyToParentAs('content_group_id')
            ->usingCustom(function (MapperDefinition $map) {
                $map->type(ImageContentArea::class);

                $map->column('content_group_id')->asUnsignedInt();
                $map->property(ImageContentArea::NAME)->to('name')->asVarchar(100);
                $map->embedded(ImageContentArea::IMAGE)
                    ->using(new ImageMapper('image_path', 'client_file_name', $this->contentConfig->getImageStorageBasePath()));
                $map->property(ImageContentArea::ALT_TEXT)->to('alt_text')->nullable()->asVarchar(1000);

                $map->unique('content_group_images_unique_index')
                    ->on(['content_group_id', 'name']);
            });

        $map->embeddedCollection(ContentGroup::TEXT_CONTENT_AREAS)
            ->toTable('content_group_text_areas')
            ->withPrimaryKey('id')
            ->withForeignKeyToParentAs('content_group_id')
            ->usingCustom(function (MapperDefinition $map) {
                $map->type(TextContentArea::class);

                $map->column('content_group_id')->asUnsignedInt();
                $map->property(TextContentArea::NAME)->to('name')->asVarchar(100);
                $map->property(TextContentArea::TEXT)->to('text')->asText();

                $map->unique('content_group_text_unique_index')
                    ->on(['content_group_id', 'name']);
            });


        $map->embeddedCollection(ContentGroup::METADATA)
            ->toTable('content_group_metadata')
            ->withPrimaryKey('id')
            ->withForeignKeyToParentAs('content_group_id')
            ->usingCustom(function (MapperDefinition $map) {
                $map->type(ContentMetadata::class);

                $map->column('content_group_id')->asUnsignedInt();
                $map->property(ContentMetadata::NAME)->to('name')->asVarchar(100);
                $map->property(ContentMetadata::VALUE)->to('value')->asVarchar(1000);

                $map->unique('content_group_metadata_unique_index')
                    ->on(['content_group_id', 'name']);
            });

        $map->relation(ContentGroup::NESTED_ARRAY_CONTENT_GROUPS)
            ->using($this)
            ->toMany()
            ->identifying()
            ->withParentIdAs('parent_id');

        $map->embedded(ContentGroup::UPDATED_AT)
            ->using(new DateTimeMapper('updated_at'));
    }
}