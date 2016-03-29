<?php declare(strict_types = 1);

namespace Dms\Package\Content\Cms;
use Dms\Package\Content\Cms\Definition\ContentConfigDefinition;
use Dms\Package\Content\Cms\Definition\ContentModuleDefinition;
use Dms\Package\Content\Cms\Definition\ContentPackageDefinition;

/**
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentPackageDream extends ContentPackage
{
    public static function defineConfig(ContentConfigDefinition $config)
    {
        $config
            ->storeImagesUnder(public_path('content/images'))
            ->mappedToUrl(url('content/images'));

    }
    
    protected function defineContent(ContentPackageDefinition $content)
    {
        $content->module('pages', 'file-text', function (ContentModuleDefinition $content) {
            $content->group('template', 'Template')
                ->withImage('banner', 'Banner')
                ->withHtml('header', 'Header')
                ->withHtml('footer', 'Footer');

            $content->page('home', 'Home', route('home'))
                ->withHtml('info', 'Info', '#info')
                ->withImage('banner', 'Banner');

        });

        $content->module('emails', 'envelope', function (ContentModuleDefinition $content) {
            $content->email('home', 'Home')
                ->withHtml('info', 'Info');
        });
    }
}