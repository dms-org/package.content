<?php declare(strict_types = 1);

namespace Dms\Package\Content\Tests\Cms\Preview;

use Dms\Common\Structure\FileSystem\Image;
use Dms\Common\Structure\Web\Html;
use Dms\Common\Testing\CmsTestCase;
use Dms\Core\Persistence\ArrayRepository;
use Dms\Core\Util\DateTimeClock;
use Dms\Package\Content\Cms\Preview\InterpolatingContentLoaderServiceProxy;
use Dms\Package\Content\Core\ContentConfig;
use Dms\Package\Content\Core\ContentGroup;
use Dms\Package\Content\Core\ContentLoaderService;
use Dms\Package\Content\Core\ContentMetadata;
use Dms\Package\Content\Core\HtmlContentArea;
use Dms\Package\Content\Core\ImageContentArea;
use Dms\Package\Content\Core\LoadedContentGroup;
use Dms\Package\Content\Core\Repositories\IContentGroupRepository;
use Dms\Package\Content\Core\TextContentArea;

/**
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class InterpolatingContentLoaderServiceProxyTest extends CmsTestCase
{
    /**
     * @var InterpolatingContentLoaderServiceProxy
     */
    protected $loader;


    public function setUp(): void
    {
        $this->loader = new InterpolatingContentLoaderServiceProxy(
            new ContentLoaderService(new ContentConfig(__DIR__ . '/../Fixtures', '/some/url', __DIR__ . '/../Fixtures'), $this->mockRepo(), new DateTimeClock())
        );
    }

    private function mockRepo() : IContentGroupRepository
    {
        $contentGroup = new ContentGroup(
            'namespace', 'name', new DateTimeClock()
        );

        $contentGroup->setId(123);

        $contentGroup->htmlContentAreas[] = new HtmlContentArea('html-area-1', new Html('<strong>ABC</strong>'));
        $contentGroup->htmlContentAreas[] = new HtmlContentArea('html-area-2', new Html('<small>123</small>'));

        $contentGroup->imageContentAreas[] = new ImageContentArea('image-area-1', new Image(__FILE__));
        $contentGroup->imageContentAreas[] = new ImageContentArea('image-area-2', new Image(__DIR__ . '/../Fixtures/image.gif', 'client-name.png'), 'alt-text');

        $contentGroup->textContentAreas[] = new TextContentArea('text-a', 'some text');
        $contentGroup->textContentAreas[] = new TextContentArea('text-b', 'more text');

        $contentGroup->metadata[] = new ContentMetadata('key', 'val');
        $contentGroup->metadata[] = new ContentMetadata('title', 'Some Title');

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
        $this->assertSame('!~~~@###!123!:!:!html-area-1:!:!:<strong>ABC</strong>!~~~@###!', $group->getHtml('html-area-1'));
        $this->assertSame('!~~~@###!123!:!:!html-area-2:!:!:<small>123</small>!~~~@###!', $group->getHtml('html-area-2'));
        $this->assertSame('', $group->getImageUrl('image-area-1'));
        $this->assertSame(null, $group->getImage('image-area-1'));
        $this->assertSame('', $group->getImageAltText('image-area-1'));
        $this->assertSame('/some/url/!~~~@###!123!:!:!image-area-2:!:!:image.gif!~~~@###!', $group->getImageUrl('image-area-2'));
        $this->assertSame('!~~~@###!123!:!:!text-a:!:!:some text!~~~@###!', $group->getText('text-a'));
        $this->assertSame('!~~~@###!123!:!:!text-b:!:!:more text!~~~@###!', $group->getText('text-b'));
        $this->assertSame('', $group->getText('text-c'));
        $this->assertSame('val', $group->getMetadata('key'));
        $this->assertSame('Some Title', $group->getMetadata('title'));
    }
}