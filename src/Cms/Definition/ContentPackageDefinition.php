<?php declare(strict_types = 1);

namespace Dms\Package\Content\Cms\Definition;

use Dms\Core\Auth\IAuthSystem;
use Dms\Core\Exception\InvalidOperationException;
use Dms\Core\Ioc\IIocContainer;
use Dms\Core\Package\Definition\PackageDefinition;
use Dms\Core\Util\IClock;
use Dms\Package\Content\Core\ContentConfig;
use Dms\Package\Content\Core\Repositories\IContentGroupRepository;

/**
 * The content package definition.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentPackageDefinition
{
    /**
     * @var ContentConfig
     */
    protected $config;

    /**
     * @var ContentModuleDefinition[]
     */
    protected $contentModuleDefinitions = [];

    /**
     * @var PackageDefinition
     */
    protected $packageDefinition;

    /**
     * @var IIocContainer
     */
    protected $iocContainer;

    /**
     * ContentPackageDefinition constructor.
     *
     * @param ContentConfig     $config
     * @param PackageDefinition $definition
     * @param IIocContainer     $iocContainer
     */
    public function __construct(ContentConfig $config, PackageDefinition $definition, IIocContainer $iocContainer)
    {
        $this->config            = $config;
        $this->packageDefinition = $definition;
        $this->iocContainer      = $iocContainer;
    }

    /**
     * Defines a module within the content package.
     *
     * Example:
     * <code>
     * $content->module('pages', 'file-text', function (ContentModuleDefinition $content) {
     *      $content->group('template', 'Template')
     *          ->withImage('banner', 'Banner')
     *          ->withHtml('header', 'Header')
     *          ->withHtml('footer', 'Footer');
     *
     *      $content->page('home', 'Home')
     *          ->url(route('home'))
     *          ->withHtml('info', 'Info', '#info')
     *          ->withImage('banner', 'Banner')
     *          ->withMetadata('extra', 'Some Extra Metadata');
     * });
     *
     * $content->module('emails', 'envelope', function (ContentModuleDefinition $content) {
     *      $content->email('home', 'Home')
     *          ->withHtml('info', 'Info');
     * });
     * </code>
     *
     * @param string   $name
     * @param string   $icon
     * @param callable $definitionCallback
     *
     * @throws InvalidOperationException
     */
    public function module(string $name, string $icon, callable $definitionCallback)
    {
        $definition = new ContentModuleDefinition($name, $icon, $this->config);
        $definitionCallback($definition);

        $this->customModules([
            $name => function () use ($definition) {
                return $definition->loadModule(
                    $this->iocContainer->get(IContentGroupRepository::class),
                    $this->iocContainer->get(IAuthSystem::class),
                    $this->iocContainer->get(IClock::class)
                );
            },
        ]);
    }

    /**
     * Defines the modules contained within this package.
     *
     * Example:
     * <code>
     * $content->customModules([
     *      'some-module-name' => SomeModule::class,
     * ]);
     * </code>
     *
     * @param string[] $nameModuleClassMap
     *
     * @return void
     */
    public function customModules(array $nameModuleClassMap)
    {
        $this->packageDefinition->modules($nameModuleClassMap);
    }
}