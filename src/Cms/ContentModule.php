<?php declare(strict_types = 1);

namespace Dms\Package\Content\Cms;

use Dms\Common\Structure\Field;
use Dms\Core\Auth\IAuthSystem;
use Dms\Core\Common\Crud\CrudModule;
use Dms\Core\Common\Crud\Definition\CrudModuleDefinition;
use Dms\Core\Common\Crud\Definition\Form\CrudFormDefinition;
use Dms\Core\Common\Crud\Definition\Table\SummaryTableDefinition;
use Dms\Core\Common\Crud\UnsupportedActionException;
use Dms\Core\Form\Builder\Form;
use Dms\Package\Content\Core\ContentConfig;
use Dms\Package\Content\Core\ContentGroup;
use Dms\Package\Content\Core\ContentMetadata;
use Dms\Package\Content\Core\HtmlContentArea;
use Dms\Package\Content\Core\ImageContentArea;
use Dms\Package\Content\Core\Repositories\IContentGroupRepository;

/**
 * The content definition.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentModule extends CrudModule
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $icon;

    /**
     * @var array[]
     */
    private $contentGroups;

    /**
     * @var ContentConfig
     */
    private $config;

    /**
     * ContentModule constructor.
     *
     * @param IContentGroupRepository $dataSource
     * @param IAuthSystem             $authSystem
     * @param string                  $name
     * @param string                  $icon
     * @param array                   $contentGroups
     * @param ContentConfig           $config
     */
    public function __construct(
        IContentGroupRepository $dataSource,
        IAuthSystem $authSystem,
        string $name,
        string $icon,
        array $contentGroups,
        ContentConfig $config
    ) {
        $this->icon          = $icon;
        $this->name          = $name;
        $this->contentGroups = $contentGroups;
        $this->config        = $config;
        parent::__construct($dataSource, $authSystem);
    }

    /**
     * @return array[]
     */
    public function getContentGroups()
    {
        return $this->contentGroups;
    }

    /**
     * Defines the structure of this module.
     *
     * @param CrudModuleDefinition $module
     */
    protected function defineCrudModule(CrudModuleDefinition $module)
    {
        $module->name($this->name);

        $module->metadata([
            'icon' => $this->icon,
        ]);

        $labelCallback = function (ContentGroup $group) {
            return $this->contentGroups[$group->name]['label'] ?? '<unkown>';
        };

        $module->labelObjects()->fromCallback($labelCallback);

        $module->crudForm(function (CrudFormDefinition $form) {
            if ($form->isCreateForm()) {
                throw new UnsupportedActionException();
            }

            $form->dependentOnObject(function (CrudFormDefinition $form, ContentGroup $group) {
                $this->defineImageFields($form, $group);
                $this->defineHtmlFields($form, $group);
                $this->defineMetadataFields($form, $group);
            });

            $form->onSubmit(function (ContentGroup $contentGroup) {
                $contentGroup->namespace = $this->name;
            });
        });

        $module->summaryTable(function (SummaryTableDefinition $table) use ($labelCallback) {
            $table->mapProperty(ContentGroup::NAMESPACE)->hidden()->to(Field::create('module_name', 'Module')->string());

            $table->mapCallback($labelCallback)->to(Field::create('name', 'Name')->string());

            $table->mapProperty(ContentGroup::UPDATED_AT)->to(Field::create('updated_at', 'Updated At')->dateTime());

            $table->view('all', 'All')
                ->asDefault()
                ->loadAll()
                ->where('module_name', '=', $this->name)
                ->orderByAsc('name');
        });
    }

    protected function defineImageFields(CrudFormDefinition $form, ContentGroup $group)
    {
        if ($this->contentGroups[$group->name]['images'] ?? false) {
            $field = $form->field(
                Field::create('images', 'Images')
                    ->arrayOf(Field::element()->form(
                        Form::create()->section('Image', [
                            Field::create('label', 'Name')->string()->readonly(),
                            Field::create('name', 'Name')->string()->hidden()->readonly(),
                            Field::create('alt_text', 'Alt Text')->string()->withEmptyStringAsNull(),
                            Field::create('image', 'Image')
                                ->image()
                                ->moveToPathWithRandomFileName($this->config->getImageStorageBasePath(), 32),
                        ])->build()
                    ))
                    ->exactLength(count($this->contentGroups[$group->name]['images']))
            )->bindToCallbacks(function (ContentGroup $group) : array {
                $values = [];

                foreach ($this->contentGroups[$group->name]['images'] as $area) {
                    $values[] = [
                        'name'     => $area['name'],
                        'label'    => $area['label'],
                        'alt_text' => $group->hasImage($area['name']) ? $group->getImage($area['name'])->altText : '',
                        'image'    => $group->hasImage($area['name']) ? $group->getImage($area['name'])->image : null,
                    ];
                }

                return $values;
            }, function (ContentGroup $group, array $input) {
                $group->imageContentAreas->clear();

                foreach ($input as $image) {
                    $group->imageContentAreas[] = new ImageContentArea($image['name'], $image['image'], $image['alt_text']);
                }
            });

            $form->section('Images', [$field]);
        }
    }

    protected function defineHtmlFields(CrudFormDefinition $form, ContentGroup $group)
    {
        if ($this->contentGroups[$group->name]['html_areas'] ?? false) {
            $field = $form->field(
                Field::create('html_areas', 'Content')
                    ->arrayOf(Field::element()->form(
                        Form::create()->section('Content', [
                            Field::create('label', 'Name')->string()->readonly(),
                            Field::create('name', 'Name')->string()->hidden()->readonly(),
                            Field::create('html', 'Content')->html(),
                        ])->build()
                    ))
                    ->exactLength(count($this->contentGroups[$group->name]['html_areas']))
            )->bindToCallbacks(function (ContentGroup $group) : array {
                $values = [];

                foreach ($this->contentGroups[$group->name]['html_areas'] as $area) {
                    $values[] = [
                        'name'  => $area['name'],
                        'label' => $area['label'],
                        'html'  => $group->hasHtml($area['name']) ? $group->getHtml($area['name'])->html : null,
                    ];
                }

                return $values;
            }, function (ContentGroup $group, array $input) {
                $group->htmlContentAreas->clear();

                foreach ($input as $htmlArea) {
                    $group->htmlContentAreas[] = new HtmlContentArea($htmlArea['name'], $htmlArea['html']);
                }
            });

            $form->section('Content', [$field]);
        }
    }

    protected function defineMetadataFields(CrudFormDefinition $form, ContentGroup $group)
    {
        if ($this->contentGroups[$group->name]['metadata'] ?? false) {
            $field = $form->field(
                Field::create('metadata', 'Metadata')
                    ->arrayOf(Field::element()->form(
                        Form::create()->section('Content', [
                            Field::create('label', 'Name')->string()->readonly(),
                            Field::create('name', 'Name')->string()->hidden()->readonly(),
                            Field::create('value', 'Value')->string(),
                        ])->build()
                    ))
                    ->exactLength(count($this->contentGroups[$group->name]['metadata']))
            )->bindToCallbacks(function (ContentGroup $group) : array {
                $values = [];

                foreach ($this->contentGroups[$group->name]['metadata'] as $item) {
                    $values[] = [
                        'name'  => $item['name'],
                        'label' => $item['label'],
                        'value' => $group->hasMetadata($item['name']) ? $group->getMetadata($item['name'])->value : null,
                    ];
                }

                return $values;
            }, function (ContentGroup $group, array $input) {
                $group->metadata->clear();

                foreach ($input as $metadata) {
                    $group->metadata[] = new ContentMetadata($metadata['name'], $metadata['value']);
                }
            });

            $form->section('Metadata', [$field]);
        }
    }
}