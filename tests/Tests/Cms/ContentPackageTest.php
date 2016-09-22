<?php declare(strict_types = 1);

namespace Dms\Package\Content\Tests\Cms;

use Dms\Common\Structure\FileSystem\Image;
use Dms\Common\Structure\FileSystem\UploadAction;
use Dms\Common\Structure\Web\Html;
use Dms\Common\Testing\CmsTestCase;
use Dms\Core\Auth\IAuthSystem;
use Dms\Core\Auth\IAuthSystemInPackageContext;
use Dms\Core\Common\Crud\Action\Object\IObjectAction;
use Dms\Core\Common\Crud\ICrudModule;
use Dms\Core\File\UploadedImageProxy;
use Dms\Core\Ioc\IIocContainer;
use Dms\Core\Persistence\ArrayRepository;
use Dms\Core\Util\DateTimeClock;
use Dms\Core\Util\IClock;
use Dms\Package\Content\Cms\ContentModule;
use Dms\Package\Content\Cms\ContentPackage;
use Dms\Package\Content\Cms\Definition\ContentConfigDefinition;
use Dms\Package\Content\Cms\Definition\ContentModuleDefinition;
use Dms\Package\Content\Cms\Definition\ContentPackageDefinition;
use Dms\Package\Content\Core\ContentGroup;
use Dms\Package\Content\Core\ContentMetadata;
use Dms\Package\Content\Core\HtmlContentArea;
use Dms\Package\Content\Core\ImageContentArea;
use Dms\Package\Content\Core\Repositories\IContentGroupRepository;
use Dms\Package\Content\Core\TextContentArea;

/**
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentPackageTest extends CmsTestCase
{
    /**
     * @var ContentPackage
     */
    protected $package;

    /**
     * @var IContentGroupRepository
     */
    protected $repo;

    public function setUp()
    {
        $ioc        = $this->getMockForAbstractClass(IIocContainer::class);
        $this->repo = $this->mockRepo();

        $ioc->method('get')
            ->willReturnCallback(function (string $class) {
                if ($class === IContentGroupRepository::class) {
                    return $this->repo;
                }

                if ($class === IClock::class) {
                    return $this->mockClock();
                }

                if ($class === IAuthSystem::class) {
                    return $this->getMockWithoutInvokingTheOriginalConstructor(IAuthSystemInPackageContext::class);
                }

                return $this->getMockWithoutInvokingTheOriginalConstructor($class);
            });

        $ioc->method('bindForCallback')
            ->willReturnCallback(function (string $abstract, $concrete, callable $callback) {
                return $callback();
            });


        $this->package = new class($ioc) extends ContentPackage
        {
            /**
             * Defines the config of the content package.
             *
             * @param ContentConfigDefinition $config
             *
             * @return void
             */
            protected static function defineConfig(ContentConfigDefinition $config)
            {
                $config->storeImagesUnder(__DIR__)
                    ->mappedToUrl('/some/url');
            }

            /**
             * Defines the structure of the content.
             *
             * @param ContentPackageDefinition $content
             *
             * @return void
             */
            protected function defineContent(ContentPackageDefinition $content)
            {
                $content->module('pages', 'file-text', function (ContentModuleDefinition $content) {
                    $content->group('template', 'Template')
                        ->withImage('banner', 'Banner')
                        ->withHtml('header', 'Header')
                        ->withText('text', 'Text')
                        ->withHtml('footer', 'Footer');

                    $content->page('home', 'Home', '/homepage')
                        ->withHtml('info', 'Info', '#info')
                        ->withImageAndAltText('banner', 'Banner');

                });

                $content->module('emails', 'envelope', function (ContentModuleDefinition $content) {
                    $content->email('notification', 'Notification')
                        ->withHtml('info', 'Info');
                });
            }
        };
    }

    protected function mockClock() : IClock
    {
        $clock = $this->getMockForAbstractClass(IClock::class);

        $clock->method('utcNow')->willReturn(new \DateTimeImmutable('2000-01-01 00:00:00'));

        return $clock;
    }

    private function mockRepo() : IContentGroupRepository
    {
        $contentGroup = new ContentGroup('namespace', 'name', new DateTimeClock());
        $contentGroup->setId(1);
        $contentGroup->htmlContentAreas[]  = new HtmlContentArea('html-area-1', new Html('<strong>ABC</strong>'));
        $contentGroup->htmlContentAreas[]  = new HtmlContentArea('html-area-2', new Html('<small>123</small>'));
        $contentGroup->imageContentAreas[] = new ImageContentArea('image-area-1', new Image(__FILE__));
        $contentGroup->imageContentAreas[] = new ImageContentArea('image-area-2', new Image(__FILE__, 'client-name.png'), 'alt-text');
        $contentGroup->metadata[]          = new ContentMetadata('key', 'val');
        $contentGroup->metadata[]          = new ContentMetadata('title', 'Some Title');

        $templateGroup = new ContentGroup('pages', 'template', $this->mockClock());
        $templateGroup->setId(2);
        $templateGroup->htmlContentAreas[]  = new HtmlContentArea('header', new Html('some header'));
        $templateGroup->htmlContentAreas[]  = new HtmlContentArea('footer', new Html('some footer'));
        $templateGroup->htmlContentAreas[]  = new HtmlContentArea('extra', new Html(''));
        $templateGroup->imageContentAreas[] = new ImageContentArea('banner', new Image(__FILE__));
        $templateGroup->imageContentAreas[] = new ImageContentArea('extra', new Image(''));
        $templateGroup->textContentAreas[]  = new TextContentArea('fsef', 'test');
        $templateGroup->metadata[]          = new ContentMetadata('extra', 'val');

        $contentGroups = [$contentGroup, $templateGroup];

        return new class(ContentGroup::collection($contentGroups)) extends ArrayRepository implements IContentGroupRepository
        {

        };
    }

    public function testModules()
    {
        $this->assertSame(true, $this->package->hasModule('pages'));
        $this->assertSame(true, $this->package->hasModule('emails'));
        $this->assertInstanceOf(ContentModule::class, $this->package->loadModule('pages'));
        $this->assertSame('file-text', $this->package->loadModule('pages')->getMetadata('icon'));
        $this->assertInstanceOf(ContentModule::class, $this->package->loadModule('emails'));
        $this->assertSame('envelope', $this->package->loadModule('emails')->getMetadata('icon'));
    }

    public function testSyncsRepoOnLoad()
    {
        $templateGroup = new ContentGroup('pages', 'template', $this->mockClock());
        $templateGroup->setId(2);
        $templateGroup->htmlContentAreas[]  = new HtmlContentArea('header', new Html('some header'));
        $templateGroup->htmlContentAreas[]  = new HtmlContentArea('footer', new Html('some footer'));
        $templateGroup->imageContentAreas[] = new ImageContentArea('banner', new Image(__FILE__));
        $templateGroup->textContentAreas[]  = new TextContentArea('text', '');

        $homeGroup = new ContentGroup('pages', 'home', $this->mockClock());
        $homeGroup->setId(3);
        $homeGroup->htmlContentAreas[]  = new HtmlContentArea('info', new Html(''));
        $homeGroup->imageContentAreas[] = new ImageContentArea('banner', new Image(''));

        $emailGroup = new ContentGroup('emails', 'notification', $this->mockClock());
        $emailGroup->setId(4);
        $emailGroup->htmlContentAreas[] = new HtmlContentArea('info', new Html(''));

        $this->assertEquals([$templateGroup, $homeGroup, $emailGroup], $this->repo->getAll());
    }

    public function testAddAndDeleteAreDisabled()
    {
        $this->assertSame(false, $this->package->loadModule('pages')->hasAction(ICrudModule::CREATE_ACTION));
        $this->assertSame(true, $this->package->loadModule('pages')->hasAction(ICrudModule::EDIT_ACTION));
        $this->assertSame(false, $this->package->loadModule('pages')->hasAction(ICrudModule::REMOVE_ACTION));
    }

    public function testEdit()
    {
        $this->package->loadModule('pages')->getParameterizedAction(ICrudModule::EDIT_ACTION)->run([
            IObjectAction::OBJECT_FIELD_NAME => 3,
            'image_banner'                   => [
                'action' => UploadAction::STORE_NEW,
                'file'   => new UploadedImageProxy(new Image(__DIR__ . '/Fixtures/image.gif', 'client-name.png')),
            ],
            'image_alt_text_banner'          => 'Abc',
            'html_info'                      => 'Info',
        ]);

        $homeGroup = new ContentGroup('pages', 'home', $this->mockClock());
        $homeGroup->setId(3);
        $homeGroup->htmlContentAreas[] = new HtmlContentArea('info', new Html('Info'));
        $image                         = new Image(__DIR__ . '/Fixtures/image.gif', 'client-name.png');
        $image->getWidth();
        $homeGroup->imageContentAreas[] = new ImageContentArea('banner', $image, 'Abc');

        $this->assertEquals($homeGroup, $this->repo->get(3));
    }


    public function testEditTemplate()
    {
        $this->package->loadModule('pages')->getParameterizedAction(ICrudModule::EDIT_ACTION)->run([
            IObjectAction::OBJECT_FIELD_NAME => 2,
            'image_banner'                   => [
                'action' => UploadAction::STORE_NEW,
                'file'   => new UploadedImageProxy(new Image(__DIR__ . '/Fixtures/image.gif', 'client-name.png')),
            ],
            'html_header'                    => 'Info',
            'html_footer'                    => 'Abc',
            'text_text'                      => '123',
        ]);

        $homeGroup = new ContentGroup('pages', 'template', $this->mockClock());
        $homeGroup->setId(2);
        $image = new Image(__DIR__ . '/Fixtures/image.gif', 'client-name.png');
        $image->getWidth();
        $homeGroup->imageContentAreas[] = new ImageContentArea('banner', $image);
        $homeGroup->htmlContentAreas[]  = new HtmlContentArea('header', new Html('Info'));
        $homeGroup->htmlContentAreas[]  = new HtmlContentArea('footer', new Html('Abc'));
        $homeGroup->textContentAreas[]  = new TextContentArea('text', '123');

        $this->assertEquals($homeGroup, $this->repo->get(2));
    }

    public function testDetails()
    {
        $this->package->loadModule('pages')->getParameterizedAction(ICrudModule::DETAILS_ACTION)->run([
            IObjectAction::OBJECT_FIELD_NAME => 3,
        ]);
    }

    public function testLoadSummaryTable()
    {
        $this->package->loadModule('pages')->getTable(ICrudModule::SUMMARY_TABLE)->loadView();
    }
}