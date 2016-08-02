<?php declare(strict_types = 1);

namespace Dms\Package\Content\Cms;

use Dms\Common\Structure\DateTime\DateTime;
use Dms\Common\Structure\Field;
use Dms\Common\Structure\FileSystem\Image;
use Dms\Common\Structure\Web\Html;
use Dms\Core\Auth\IAuthSystem;
use Dms\Core\Common\Crud\CrudModule;
use Dms\Core\Common\Crud\Definition\CrudModuleDefinition;
use Dms\Core\Common\Crud\Definition\Form\CrudFormDefinition;
use Dms\Core\Common\Crud\Definition\Table\SummaryTableDefinition;
use Dms\Core\Common\Crud\UnsupportedActionException;
use Dms\Core\Form\Builder\Form;
use Dms\Core\Util\IClock;
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
     * @var IClock
     */
    private $clock;

    /**
     * ContentModule constructor.
     *
     * @param IContentGroupRepository $dataSource
     * @param IAuthSystem             $authSystem
     * @param string                  $name
     * @param string                  $icon
     * @param array                   $contentGroups
     * @param ContentConfig           $config
     * @param IClock                  $clock
     */
    public function __construct(
        IContentGroupRepository $dataSource,
        IAuthSystem $authSystem,
        string $name,
        string $icon,
        array $contentGroups,
        ContentConfig $config,
        IClock $clock
    ) {
        $this->icon          = $icon;
        $this->name          = $name;
        $this->contentGroups = $contentGroups;
        $this->config        = $config;
        $this->clock         = $clock;

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
                $form->unsupported();
            }

            $form->dependentOnObject(function (CrudFormDefinition $form, ContentGroup $group) {
                $this->defineImageFields($form, $group);
                $this->defineHtmlFields($form, $group);
                $this->defineMetadataFields($form, $group);
            });

            $form->onSubmit(function (ContentGroup $contentGroup) {
                $contentGroup->namespace = $this->name;
                $contentGroup->updatedAt = new DateTime($this->clock->utcNow());
            });
        });

        $module->summaryTable(function (SummaryTableDefinition $table) use ($labelCallback) {
            $table->mapProperty(ContentGroup::NAMESPACE)->hidden()->to(Field::create('module_name', 'Module')->string());
            $table->mapProperty(ContentGroup::NAME)->hidden()->to(Field::create('group_name', 'Group Name')->string());

            $table->mapCallback($labelCallback)->to(Field::create('name', 'Name')->string());

            $table->mapProperty(ContentGroup::UPDATED_AT)->to(Field::create('updated_at', 'Updated At')->dateTime());

            $table->view('all', 'All')
                ->asDefault()
                ->loadAll()
                ->where('module_name', '=', $this->name)
                ->orderByAsc('group_name');
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
                            Field::create('name', 'name')->string()->hidden()->readonly(),
                            Field::create('image', 'Image')
                                ->image()
                                ->moveToPathWithRandomFileName($this->config->getImageStorageBasePath(), 32),
                            Field::create('alt_text', 'Alt Text')->string()->withEmptyStringAsNull(),
                        ])->build()
                    )->required())
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
                    $group->imageContentAreas[] = new ImageContentArea($image['name'], $image['image'] ?? new Image(''), $image['alt_text']);
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
                            Field::create('name', 'name')->string()->hidden()->readonly(),
                            Field::create('html', 'Content')->html(),
                        ])->build()
                    )->required())
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
                    $group->htmlContentAreas[] = new HtmlContentArea($htmlArea['name'], $htmlArea['html'] ?? new Html(''));
                }
            });

            $form->section('Content', [$field]);
        }
    }

    protected function defineMetadataFields(CrudFormDefinition $form, ContentGroup $group)
    {
        if ($this->contentGroups[$group->name]['metadata'] ?? false) {
            $fields = [];

            foreach ($this->contentGroups[$group->name]['metadata'] as $item) {
                $fields[] = Field::create($item['name'], $item['label'])->string();
            }

            $field = $form->field(
                Field::create('metadata', 'Metadata')
                    ->form(Form::create()->section('', $fields)->build())
                    ->required()
            )->bindToCallbacks(function (ContentGroup $group) : array {
                $values = [];

                foreach ($this->contentGroups[$group->name]['metadata'] as $item) {
                    $values[$item['name']] = $group->hasMetadata($item['name']) ? $group->getMetadata($item['name'])->value : null;
                }

                return $values;
            }, function (ContentGroup $group, array $input) {
                $group->metadata->clear();

                foreach ($input as $key => $value) {
                    $group->metadata[] = new ContentMetadata($key, $value ?? '');
                }
            });

            $form->section('Metadata', [$field]);
        }
    }
}