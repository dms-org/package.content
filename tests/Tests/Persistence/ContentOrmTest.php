<?php declare(strict_types = 1);

namespace Dms\Package\Content\Tests\Persistence;

use Dms\Common\Structure\FileSystem\Image;
use Dms\Common\Structure\Web\Html;
use Dms\Core\Ioc\IIocContainer;
use Dms\Core\Persistence\Db\Mapping\IOrm;
use Dms\Core\Tests\Persistence\Db\Integration\Mapping\DbIntegrationTest;
use Dms\Core\Util\IClock;
use Dms\Package\Content\Core\ContentConfig;
use Dms\Package\Content\Core\ContentGroup;
use Dms\Package\Content\Core\ContentMetadata;
use Dms\Package\Content\Core\HtmlContentArea;
use Dms\Package\Content\Core\ImageContentArea;
use Dms\Package\Content\Core\TextContentArea;
use Dms\Package\Content\Persistence\ContentGroupMapper;
use Dms\Package\Content\Persistence\ContentOrm;
use Dms\Package\Content\Persistence\DbContentGroupRepository;

/**
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentOrmTest extends DbIntegrationTest
{
    /**
     * @var DbContentGroupRepository
     */
    protected $repo;

    public function setUp()
    {
        parent::setUp();
        $this->repo = new DbContentGroupRepository($this->connection, $this->orm);
    }

    /**
     * @return IOrm
     */
    protected function loadOrm()
    {
        $ioc = $this->getMockForAbstractClass(IIocContainer::class);

        $ioc->method('get')
            ->with(ContentGroupMapper::class)
            ->willReturnCallback(function () {
                return new ContentGroupMapper($this->getMockForAbstractClass(IOrm::class), new ContentConfig(__DIR__, '/'));
            });

        $ioc->method('bindForCallback')
            ->willReturnCallback(function ($class, $val, callable $callback) {
                return $callback();
            });

        return new ContentOrm($ioc);
    }

    public function testSaveAndLoad()
    {
        $contentGroup = new ContentGroup(
            'namespace', 'name', $this->mockClock(new \DateTimeImmutable('2000-01-01 00:00:11'))
        );

        $contentGroup->htmlContentAreas[] = new HtmlContentArea('html-area-1', new Html('<strong>ABC</strong>'));
        $contentGroup->htmlContentAreas[] = new HtmlContentArea('html-area-2', new Html('<small>123</small>'));

        $contentGroup->imageContentAreas[] = new ImageContentArea('image-area-1', new Image(__FILE__));
        $contentGroup->imageContentAreas[] = new ImageContentArea('image-area-2', new Image(__FILE__, 'client-name.png'), 'alt-text');

        $contentGroup->textContentAreas[] = new TextContentArea('text-area-1', 'ABC');
        $contentGroup->textContentAreas[] = new TextContentArea('text-area-2', '123');

        $contentGroup->metadata[] = new ContentMetadata('key', 'val');
        $contentGroup->metadata[] = new ContentMetadata('title', 'Some Title');

        $this->repo->save($contentGroup);

        $this->assertDatabaseDataSameAs([
            'content_groups'            => [
                ['id' => 1, 'parent_id' => null, 'namespace' => 'namespace', 'name' => 'name', 'updated_at' => '2000-01-01 00:00:11'],
            ],
            'content_group_html_areas'  => [
                ['id' => 1, 'content_group_id' => 1, 'name' => 'html-area-1', 'html' => '<strong>ABC</strong>'],
                ['id' => 2, 'content_group_id' => 1, 'name' => 'html-area-2', 'html' => '<small>123</small>'],
            ],
            'content_group_image_areas' => [
                ['id' => 1, 'content_group_id' => 1, 'name' => 'image-area-1', 'image_path' => basename(__FILE__), 'client_file_name' => null, 'alt_text' => null],
                ['id' => 2, 'content_group_id' => 1, 'name' => 'image-area-2', 'image_path' => basename(__FILE__), 'client_file_name' => 'client-name.png', 'alt_text' => 'alt-text'],
            ],
            'content_group_text_areas'  => [
                ['id' => 1, 'content_group_id' => 1, 'name' => 'text-area-1', 'text' => 'ABC'],
                ['id' => 2, 'content_group_id' => 1, 'name' => 'text-area-2', 'text' => '123'],
            ],
            'content_group_metadata'    => [
                ['id' => 1, 'content_group_id' => 1, 'name' => 'key', 'value' => 'val'],
                ['id' => 2, 'content_group_id' => 1, 'name' => 'title', 'value' => 'Some Title'],
            ],
        ]);

        $contentGroup->setId(1);

        $this->assertEquals($contentGroup, $this->repo->get(1));
    }

    public function testNestedContentGroups()
    {
        $contentGroup  = new ContentGroup(
            'namespace', 'name', $this->mockClock(new \DateTimeImmutable('2000-01-01 00:00:11'))
        );
        $contentGroup1 = new ContentGroup(
            '__element__', 'name', $this->mockClock(new \DateTimeImmutable('2000-01-01 00:00:11'))
        );
        $contentGroup2 = new ContentGroup(
            '__element__', 'name', $this->mockClock(new \DateTimeImmutable('2000-01-01 00:00:11'))
        );


        $contentGroup1->nestedArrayContentGroups[] = $contentGroup2;
        $contentGroup->nestedArrayContentGroups[]  = $contentGroup1;

        $this->repo->save($contentGroup);

        $this->assertDatabaseDataSameAs([
            'content_groups'            => [
                ['id' => 1, 'parent_id' => null, 'namespace' => 'namespace', 'name' => 'name', 'updated_at' => '2000-01-01 00:00:11'],
                ['id' => 2, 'parent_id' => 1, 'namespace' => '__element__', 'name' => 'name', 'updated_at' => '2000-01-01 00:00:11'],
                ['id' => 3, 'parent_id' => 2, 'namespace' => '__element__', 'name' => 'name', 'updated_at' => '2000-01-01 00:00:11'],
            ],
            'content_group_html_areas'  => [],
            'content_group_image_areas' => [],
            'content_group_metadata'    => [],
            'content_group_text_areas'  => [],
        ]);

        $contentGroup->setId(1);
        $contentGroup1->setId(2);
        $contentGroup2->setId(3);

        $this->assertEquals($contentGroup, $this->repo->get(1));
    }

    protected function mockClock(\DateTimeImmutable $dateTime) : IClock
    {
        $clock = $this->getMockForAbstractClass(IClock::class);

        $clock->method('utcNow')->willReturn($dateTime);

        return $clock;
    }
}