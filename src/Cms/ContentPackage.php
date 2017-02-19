<?php declare(strict_types = 1);

namespace Dms\Package\Content\Cms;

use Dms\Common\Structure\FileSystem\Image;
use Dms\Common\Structure\Web\Html;
use Dms\Core\Exception\NotImplementedException;
use Dms\Core\ICms;
use Dms\Core\Ioc\IIocContainer;
use Dms\Core\Package\Definition\PackageDefinition;
use Dms\Core\Package\Package;
use Dms\Core\Util\IClock;
use Dms\Package\Content\Cms\Definition\ContentConfigDefinition;
use Dms\Package\Content\Cms\Definition\ContentGroupDefinition;
use Dms\Package\Content\Cms\Definition\ContentPackageDefinition;
use Dms\Package\Content\Core\ContentConfig;
use Dms\Package\Content\Core\ContentGroup;
use Dms\Package\Content\Core\ContentLoaderService;
use Dms\Package\Content\Core\ContentMetadata;
use Dms\Package\Content\Core\HtmlContentArea;
use Dms\Package\Content\Core\ImageContentArea;
use Dms\Package\Content\Core\Repositories\IContentGroupRepository;
use Dms\Package\Content\Core\TextContentArea;
use Dms\Package\Content\Persistence\DbContentGroupRepository;

/**
 * The content package base class.
 *
 * Since the content schema is structured differently for each site
 * as per the design requirements, there is no generic concrete content
 * package.
 *
 * This is a base class where you may define the structure of the content
 * and the modules and backend will be generated accordingly.
 *
 * Example:
 * <code>
 * protected function defineContent(ContentPackageDefinition $content)
 * {
 *      $content->module('pages', 'file-text', function (ContentModuleDefinition $content) {
 *          $content->group('template', 'Template')
 *              ->withImage('banner', 'Banner')
 *              ->withHtml('header', 'Header')
 *              ->withHtml('footer', 'Footer');
 *
 *          $content->page('home', 'Home', route('home'))
 *              ->withHtml('info', 'Info', '#info')
 *              ->withImage('banner', 'Banner');
 *
 *      });
 *
 *      $content->module('emails', 'envelope', function (ContentModuleDefinition $content) {
 *          $content->email('home', 'Home')
 *              ->withHtml('info', 'Info');
 *      });
 * }
 * </code>
 *
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
abstract class ContentPackage extends Package
{
    /**
     * Package constructor.
     *
     * @param IIocContainer $container
     */
    public function __construct(IIocContainer $container)
    {
        parent::__construct($container);

        $this->syncRepoWithCurrentContentSchema($container->get(IContentGroupRepository::class), $container->get(IClock::class));
    }


    /**
     * Boots and configures the package resources and services.
     *
     * @param ICms $cms
     *
     * @return void
     */
    public static function boot(ICms $cms)
    {
        $iocContainer = $cms->getIocContainer();

        $iocContainer->bind(IIocContainer::SCOPE_SINGLETON, IContentGroupRepository::class, DbContentGroupRepository::class);
        $iocContainer->bind(IIocContainer::SCOPE_SINGLETON, ContentLoaderService::class, ContentLoaderService::class);

        $iocContainer->bindCallback(
            IIocContainer::SCOPE_SINGLETON,
            ContentConfig::class,
            function () use ($cms) : ContentConfig {
                $definition = new ContentConfigDefinition();

                static::defineConfig($definition);

                return $definition->finalize();
            }
        );
    }

    /**
     * Defines the config of the content package.
     *
     * @param ContentConfigDefinition $config
     *
     * @return void
     * @throws NotImplementedException
     */
    protected static function defineConfig(ContentConfigDefinition $config)
    {
        throw NotImplementedException::format(
            'Invalid content package class %s: the \'%s\' static method must be overridden',
            get_called_class(), __FUNCTION__
        );
    }

    /**
     * Defines the structure of this cms package.
     *
     * @param PackageDefinition $package
     *
     * @return void
     */
    final protected function define(PackageDefinition $package)
    {
        $package->name('content');

        $package->metadata([
            'icon' => 'cubes',
        ]);

        $contentDefinition = new ContentPackageDefinition($this->getIocContainer()->get(ContentConfig::class), $package, $this->getIocContainer());

        $this->defineContent($contentDefinition);
    }

    /**
     * Defines the structure of the content.
     *
     * @param ContentPackageDefinition $content
     *
     * @return void
     */
    abstract protected function defineContent(ContentPackageDefinition $content);

    private function syncRepoWithCurrentContentSchema(IContentGroupRepository $contentGroupRepository, IClock $clock)
    {
        $namespacedContentGroups = [];
        $contentGroupsToRemove   = [];
        $contentGroupsToCreate   = [];
        $contentGroupsToSync     = [];

        $parentContentGroups = $contentGroupRepository->matching(
            $contentGroupRepository->criteria()
                ->where(ContentGroup::NAMESPACE, '!=', '__element__')
        );

        foreach ($parentContentGroups as $contentGroup) {
            $namespacedContentGroups[$contentGroup->namespace][$contentGroup->name] = $contentGroup;
        }

        foreach ($this->loadModules() as $module) {
            /** @var ContentModule $module */
            $contentGroups = $namespacedContentGroups[$module->getName()] ?? [];

            $contentGroupSchemas  = $module->getContentGroups();
            $contentGroupsToOrder = [];

            foreach (array_diff_key($contentGroupSchemas, $contentGroups) as $contentGroupDefinition) {
                $contentGroup                              = $this->buildNewContentGroup($module, $contentGroupDefinition, $clock);
                $contentGroupsToCreate[]                   = $contentGroup;
                $contentGroupsToOrder[$contentGroup->name] = $contentGroup;
            }

            foreach (array_intersect_key($contentGroups, $contentGroupSchemas) as $contentGroup) {
                /** @var ContentGroup $contentGroup */
                $originalContentHash = $contentGroup->getHash();
                $this->syncContentGroupWithSchema($contentGroup, $contentGroupSchemas[$contentGroup->name]);

                if ($contentGroup->getHash() !== $originalContentHash) {
                    $contentGroupsToSync[] = $contentGroup;
                }

                $contentGroupsToOrder[$contentGroup->name] = $contentGroup;
            }

            foreach (array_diff_key($contentGroups, $contentGroupSchemas) as $contentGroup) {
                $contentGroupsToRemove[] = $contentGroup;
            }


            $order = 1;

            foreach ($contentGroupSchemas as $contentGroupDefinition) {
                $contentGroupsToOrder[$contentGroupDefinition->name]->orderIndex = $order++;
            }
        }

        foreach (array_diff_key($namespacedContentGroups, array_fill_keys($this->getModuleNames(), true)) as $removedGroups) {
            $contentGroupsToRemove = array_merge($contentGroupsToRemove, array_values($removedGroups));
        }

        $contentGroupRepository->removeAll($contentGroupsToRemove);
        $contentGroupRepository->saveAll(array_merge($contentGroupsToCreate, $contentGroupsToSync));
    }

    /**
     * @param ContentModule          $module
     * @param ContentGroupDefinition $contentGroupDefinition
     * @param IClock                 $clock
     *
     * @return ContentGroup
     */
    private function buildNewContentGroup(ContentModule $module, ContentGroupDefinition $contentGroupDefinition, IClock $clock) : ContentGroup
    {
        $contentGroup = new ContentGroup($module->getName(), $contentGroupDefinition->name, $clock);

        foreach ($contentGroupDefinition->htmlAreas as $area) {
            $contentGroup->htmlContentAreas[] = new HtmlContentArea($area['name'], new Html(''));
        }

        foreach ($contentGroupDefinition->images as $area) {
            $contentGroup->imageContentAreas[] = new ImageContentArea($area['name'], new Image(''));
        }

        foreach ($contentGroupDefinition->textAreas as $area) {
            $contentGroup->textContentAreas[] = new TextContentArea($area['name'], '');
        }

        foreach ($contentGroupDefinition->metadata as $item) {
            $contentGroup->metadata[] = new ContentMetadata($item['name'], '');
        }

        return $contentGroup;
    }

    /**
     * @param ContentGroup           $contentGroup
     * @param ContentGroupDefinition $contentGroupSchema
     *
     * @return ContentGroup
     */
    private function syncContentGroupWithSchema(ContentGroup $contentGroup, ContentGroupDefinition $contentGroupSchema)
    {
        $contentGroup->htmlContentAreas->removeWhere(function (HtmlContentArea $area) use ($contentGroupSchema) {
            return !isset($contentGroupSchema->htmlAreas[$area->name]);
        });

        $htmlNames = $contentGroup->htmlContentAreas->indexBy(function (HtmlContentArea $area) {
            return $area->name;
        })->asArray();

        foreach (array_diff_key($contentGroupSchema->htmlAreas, $htmlNames) as $area) {
            $contentGroup->htmlContentAreas[] = new HtmlContentArea($area['name'], new Html(''));
        }

        $contentGroup->imageContentAreas->removeWhere(function (ImageContentArea $area) use ($contentGroupSchema) {
            return !isset($contentGroupSchema->images[$area->name]);
        });

        $imageNames = $contentGroup->imageContentAreas->indexBy(function (ImageContentArea $area) {
            return $area->name;
        })->asArray();

        foreach (array_diff_key($contentGroupSchema->images, $imageNames) as $area) {
            $contentGroup->imageContentAreas[] = new ImageContentArea($area['name'], new Image(''));
        }

        $validTextOptions = array_fill_keys(array_column($contentGroupSchema->textAreas, 'name'), true);
        $contentGroup->textContentAreas->removeWhere(function (TextContentArea $contentArea) use ($validTextOptions) {
            return !isset($validTextOptions[$contentArea->name]);
        });

        $textNames = $contentGroup->textContentAreas->indexBy(function (TextContentArea $contentArea) {
            return $contentArea->name;
        })->asArray();

        foreach (array_diff_key($validTextOptions, $textNames) as $name => $unusedVariable) {
            $contentGroup->textContentAreas[] = new TextContentArea($name, '');
        }

        $validMetadataOptions = array_fill_keys(array_column($contentGroupSchema->metadata, 'name'), true);
        $contentGroup->metadata->removeWhere(function (ContentMetadata $metadata) use ($validMetadataOptions) {
            return !isset($validMetadataOptions[$metadata->name]);
        });

        $metadataNames = $contentGroup->metadata->indexBy(function (ContentMetadata $content) {
            return $content->name;
        })->asArray();

        foreach (array_diff_key($validMetadataOptions, $metadataNames) as $name => $unusedVariable) {
            $contentGroup->metadata[] = new ContentMetadata($name, '');
        }

        $validArrayOptions = array_fill_keys(array_column($contentGroupSchema->nestedArrayContentGroups, 'name'), true);
        $contentGroup
            ->nestedArrayContentGroups
            ->removeWhere(function (ContentGroup $group) use ($validArrayOptions) {
                return !isset($validArrayOptions[$group->name]);
            });
    }
}