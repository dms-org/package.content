<?php declare(strict_types = 1);

namespace Dms\Package\Content\Tests\Core;

use Dms\Common\Structure\FileSystem\File;
use Dms\Common\Structure\FileSystem\Image;
use Dms\Common\Structure\Web\Html;
use Dms\Common\Testing\CmsTestCase;
use Dms\Core\Exception\InvalidArgumentException;
use Dms\Core\Persistence\ArrayRepository;
use Dms\Core\Util\DateTimeClock;
use Dms\Library\Testing\Mock\MockClock;
use Dms\Package\Content\Core\ContentConfig;
use Dms\Package\Content\Core\ContentGroup;
use Dms\Package\Content\Core\ContentLoaderService;
use Dms\Package\Content\Core\ContentMetadata;
use Dms\Package\Content\Core\FileContentArea;
use Dms\Package\Content\Core\HtmlContentArea;
use Dms\Package\Content\Core\ImageContentArea;
use Dms\Package\Content\Core\LoadedContentGroup;
use Dms\Package\Content\Core\Repositories\IContentGroupRepository;
use Dms\Package\Content\Core\TextContentArea;

/**
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentLoaderServiceTest extends CmsTestCase
{
    /**
     * @var ContentLoaderService
     */
    protected $loader;

    public function setUp()
    {
        $this->loader = new ContentLoaderService(new ContentConfig(__DIR__ . '/../Cms/Fixtures', '/some/url', __DIR__ . '/../Cms/Fixtures'), $this->mockRepo(), new DateTimeClock());
    }

    private function mockRepo() : IContentGroupRepository
    {
        $contentGroup = new ContentGroup(
            'namespace', 'name', new DateTimeClock()
        );

        $contentGroup->htmlContentAreas[] = new HtmlContentArea('html-area-1', new Html('<strong>ABC</strong>'));
        $contentGroup->htmlContentAreas[] = new HtmlContentArea('html-area-2', new Html('<small>123</small>'));

        $contentGroup->imageContentAreas[] = new ImageContentArea('image-area-1', new Image(__FILE__));
        $contentGroup->imageContentAreas[] = new ImageContentArea('image-area-2', new Image(__DIR__ . '/../Cms/Fixtures/image.gif', 'client-name.png'), 'alt-text');

        $contentGroup->textContentAreas[] = new TextContentArea('text-a', 'some text');
        $contentGroup->textContentAreas[] = new TextContentArea('text-b', 'more text');

        $contentGroup->fileContentAreas[] = new FileContentArea('file-a', new File(__FILE__));
        $contentGroup->fileContentAreas[] = new FileContentArea('file-b', new File(__FILE__));

        $contentGroup->metadata[] = new ContentMetadata('key', 'val');
        $contentGroup->metadata[] = new ContentMetadata('title', 'Some Title');

        $contentGroup->nestedArrayContentGroups[] = new ContentGroup('__element__', 'item', new MockClock('2000-01-01 00:00:00'));
        $contentGroup->nestedArrayContentGroups[] = new ContentGroup('__element__', 'item', new MockClock('2000-01-01 00:00:00'));

        $contentGroups = [$contentGroup];

        return new class(ContentGroup::collection($contentGroups)) extends ArrayRepository implements IContentGroupRepository
        {

        };
    }

    public function testLoad()
    {
        $group = $this->loader->load('namespace.name');

        $this->assertInstanceOf(LoadedContentGroup::class, $group);
        $this->assertSame('namespace', $group->getContentGroup()->namespace);
        $this->assertSame('name', $group->getContentGroup()->name);
        $this->assertSame('<strong>ABC</strong>', $group->getHtml('html-area-1'));
        $this->assertSame('<small>123</small>', $group->getHtml('html-area-2'));
        $this->assertSame('', $group->getImageUrl('image-area-1'));
        $this->assertSame(null, $group->getImage('image-area-1'));
        $this->assertSame('', $group->getImageAltText('image-area-1'));
        $this->assertSame('/some/url/image.gif', $group->getImageUrl('image-area-2'));
        $this->assertSame('some text', $group->getText('text-a'));
        $this->assertSame('more text', $group->getText('text-b'));
        $this->assertSame('', $group->getText('text-c'));
        $this->assertSame(__FILE__, $group->getFile('file-a')->getFullPath());
        $this->assertSame(__FILE__, $group->getFile('file-b')->getFullPath());
        $this->assertSame(null, $group->getFile('file-c'));
        $this->assertSame('val', $group->getMetadata('key'));
        $this->assertSame('Some Title', $group->getMetadata('title'));
        $this->assertSame('<meta name="key" content="val" />' . PHP_EOL . '<title>Some Title</title>', $group->renderMetadataAsHtml());
        $this->assertSame(true, $group->hasArrayOf('item'));
        $this->assertEquals([
            new LoadedContentGroup($group->getConfig(), new ContentGroup('__element__', 'item', new MockClock('2000-01-01 00:00:00'))),
            new LoadedContentGroup($group->getConfig(), new ContentGroup('__element__', 'item', new MockClock('2000-01-01 00:00:00'))),
        ], $group->getArrayOf('item'));
        $this->assertSame([], $group->getArrayOf('non-existent'));
    }

    public function testLoadNonExistent()
    {
        $group = $this->loader->load('unknown.name');

        $this->assertInstanceOf(LoadedContentGroup::class, $group);
        $this->assertSame('unknown', $group->getContentGroup()->namespace);
        $this->assertSame('name', $group->getContentGroup()->name);
        $this->assertSame(0, $group->getContentGroup()->htmlContentAreas->count());
        $this->assertSame(0, $group->getContentGroup()->imageContentAreas->count());
        $this->assertSame(0, $group->getContentGroup()->metadata->count());
        $this->assertSame(0, $group->getContentGroup()->textContentAreas->count());
        $this->assertSame(0, $group->getContentGroup()->fileContentAreas->count());
        $this->assertSame(0, $group->getContentGroup()->nestedArrayContentGroups->count());
    }

    public function testDefaults()
    {
        $group = $this->loader->load('unknown.name');

        $this->assertInstanceOf(LoadedContentGroup::class, $group);
        $this->assertSame('default', $group->getHtml('invalid', 'default'));
        $this->assertSame('default', $group->getMetadata('invalid', 'default'));
        $this->assertSame('default', $group->getImageUrl('invalid', 'default'));
        $this->assertSame('default', $group->getText('invalid', 'default'));
        $this->assertSame('default', $group->getImageAltText('invalid', 'default'));
    }

    public function testInvalidGroupName()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->loader->load('some-invalid-name');
    }
}