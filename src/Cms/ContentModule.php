<?php declare(strict_types = 1);

namespace Dms\Package\Content\Cms;

use Dms\Common\Structure\DateTime\DateTime;
use Dms\Common\Structure\Field;
use Dms\Common\Structure\FileSystem\Image;
use Dms\Core\Auth\IAuthSystem;
use Dms\Core\Common\Crud\CrudModule;
use Dms\Core\Common\Crud\Definition\CrudModuleDefinition;
use Dms\Core\Common\Crud\Definition\Form\CrudFormDefinition;
use Dms\Core\Common\Crud\Definition\Table\SummaryTableDefinition;
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
                $form->section('Content', []);

                foreach ($this->getFieldsInOrder($group) as $field) {
                    if ($field['type'] === 'html') {
                        $this->defineHtmlField($form, $field);
                    } elseif ($field['type'] === 'image') {
                        $this->defineImageField($form, $field);
                    } else {
                        $this->defineMetadataField($form, $field);
                    }
                }
            });

            $form->onSubmit(function (ContentGroup $contentGroup, array $input) {
                $contentGroup->imageContentAreas->clear();
                $contentGroup->htmlContentAreas->clear();
                $contentGroup->metadata->clear();

                foreach ($this->getFieldsInOrder($contentGroup) as $field) {
                    if ($field['type'] === 'html') {
                        $contentGroup->htmlContentAreas[] = new HtmlContentArea(
                            $field['name'],
                            $input['html_' . $field['name']]
                        );
                    } elseif ($field['type'] === 'image') {
                        $contentGroup->imageContentAreas[] = new ImageContentArea(
                            $field['name'],
                            $input['image_' . $field['name']] ?? new Image(''),
                            $input['image_alt_text_' . $field['name']]
                        );
                    } else {
                        $contentGroup->metadata[] = new ContentMetadata(
                            $field['name'],
                            $input['metadata_' . $field['name']]
                        );
                    }
                }
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

    private function getFieldsInOrder(ContentGroup $group) : array
    {
        $fieldsInOrder = [];

        foreach (['html_areas' => 'html', 'images' => 'image', 'metadata' => 'metadata'] as $option => $type) {

            foreach ($this->contentGroups[$group->name][$option] ?? [] as $field) {
                $fieldsInOrder[$field['order']] = $field + ['type' => $type];
            }
        }

        ksort($fieldsInOrder, SORT_NUMERIC);

        return $fieldsInOrder;
    }

    protected function defineImageField(CrudFormDefinition $form, array $field)
    {
        $form->continueSection([
            $form->field(
                Field::create('image_' . $field['name'], $field['label'])
                    ->image()
                    ->moveToPathWithRandomFileName($this->config->getImageStorageBasePath(), 32)
            )->bindToCallbacks(function (ContentGroup $group) use ($field) {
                return $group->hasImage($field['name']) ? $group->getImage($field['name'])->image : null;
            }, function (ContentGroup $group) {

            }),
            //
            $form->field(
                Field::create('image_alt_text_' . $field['name'], $field['label'] . ' - Alt Text')->string()->defaultTo('')
            )->bindToCallbacks(function (ContentGroup $group) use ($field) {
                return $group->hasImage($field['name']) ? $group->getImage($field['name'])->altText : '';
            }, function (ContentGroup $group) {

            }),
        ]);
    }

    protected function defineHtmlField(CrudFormDefinition $form, array $field)
    {
        $form->continueSection([
            $form->field(
                Field::create('html_' . $field['name'], $field['label'])->html()
            )->bindToCallbacks(function (ContentGroup $group) use ($field) {
                return $group->hasHtml($field['name']) ? $group->getHtml($field['name'])->html : '';
            }, function (ContentGroup $group) {

            }),
        ]);
    }

    protected function defineMetadataField(CrudFormDefinition $form, array $field)
    {
        $form->continueSection([
            $form->field(
                Field::create('metadata_' . $field['name'], $field['label'])->string()->defaultTo('')
            )->bindToCallbacks(function (ContentGroup $group) use ($field) {
                return $group->hasMetadata($field['name']) ? $group->getMetadata($field['name'])->value : '';
            }, function (ContentGroup $group) {

            }),
        ]);
    }
}