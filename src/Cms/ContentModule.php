<?php declare(strict_types = 1);

namespace Dms\Package\Content\Cms;

use Dms\Common\Structure\DateTime\DateTime;
use Dms\Common\Structure\Field;
use Dms\Common\Structure\FileSystem\Image;
use Dms\Common\Structure\FileSystem\PathHelper;
use Dms\Common\Structure\Web\Html;
use Dms\Core\Auth\IAuthSystem;
use Dms\Core\Common\Crud\CrudModule;
use Dms\Core\Common\Crud\Definition\CrudModuleDefinition;
use Dms\Core\Common\Crud\Definition\Form\CrudFormDefinition;
use Dms\Core\Common\Crud\Definition\Table\SummaryTableDefinition;
use Dms\Core\File\IFile;
use Dms\Core\Form\Builder\Form;
use Dms\Core\Util\IClock;
use Dms\Package\Content\Cms\Definition\ContentGroupDefinition;
use Dms\Package\Content\Cms\Preview\PreviewContentLoader;
use Dms\Package\Content\Core\ContentConfig;
use Dms\Package\Content\Core\ContentGroup;
use Dms\Package\Content\Core\ContentLoaderService;
use Dms\Package\Content\Core\ContentMetadata;
use Dms\Package\Content\Core\HtmlContentArea;
use Dms\Package\Content\Core\ImageContentArea;
use Dms\Package\Content\Core\Repositories\IContentGroupRepository;
use Dms\Package\Content\Core\TextContentArea;

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
     * @var ContentGroupDefinition[]
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
     * @param IContentGroupRepository  $dataSource
     * @param IAuthSystem              $authSystem
     * @param string                   $name
     * @param string                   $icon
     * @param ContentGroupDefinition[] $contentGroups
     * @param ContentConfig            $config
     * @param IClock                   $clock
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
     * @return ContentGroupDefinition[]
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
            return $this->contentGroups[$group->name]->label ?? '<unkown>';
        };

        $module->labelObjects()->fromCallback($labelCallback);

        $module->crudForm(function (CrudFormDefinition $form) {
            if ($form->isCreateForm()) {
                $form->unsupported();
            }

            $form->dependentOnObject(function (CrudFormDefinition $form, ContentGroup $group) {
                $form->section('Content', []);

                foreach ($this->getFieldsInOrder($this->contentGroups[$group->name]) as $field) {
                    if ($field['type'] === 'html') {
                        $this->defineHtmlField($form, $field);
                    } elseif ($field['type'] === 'image') {
                        $this->defineImageField($form, $field);
                    } elseif ($field['type'] === 'text') {
                        $this->defineTextField($form, $field);
                    } elseif ($field['type'] === 'metadata') {
                        $this->defineMetadataField($form, $field);
                    } else {
                        $this->defineArrayField($form, $field);
                    }
                }
            });

            $form->dependentOn(['*'], function (CrudFormDefinition $form, array $input, ContentGroup $group) {
                if ($this->contentGroups[$group->name]->previewCallback) {
                    $form->continueSection([
                        $form->field(
                            Field::create('preview', 'Preview')
                                ->html()
                                ->readonly()
                                ->value(new Html($this->loadPreviewContent($group, $input)))
                        )->withoutBinding(),
                    ]);
                }
            });

            $form->onSubmit(function (ContentGroup $contentGroup, array $input) {
                $this->updateContentGroup($contentGroup, $this->contentGroups[$contentGroup->name], $input);
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

            $table->mapProperty(ContentGroup::ORDER_INDEX)->hidden()->to(Field::create('order', 'Order')->int());

            $table->view('all', 'All')
                ->asDefault()
                ->loadAll()
                ->where('module_name', '=', $this->name)
                ->orderByAsc('order');
        });
    }

    protected function loadPreviewContent(ContentGroup $contentGroup, array $input) : string
    {
        $groupDefinition = $this->contentGroups[$contentGroup->name];

        $previewContentGroup = unserialize(serialize($contentGroup));
        $this->updateContentGroup($previewContentGroup, $groupDefinition, $input);

        $previewContentLoader = new PreviewContentLoader(
            $this->authSystem->getIocContainer()->get(ContentLoaderService::class),
            [$previewContentGroup]
        );

        $preview = $this->authSystem->getIocContainer()->bindForCallback(
            ContentLoaderService::class,
            $previewContentLoader,
            function () use ($previewContentGroup, $groupDefinition) {
                return call_user_func($groupDefinition->previewCallback, $previewContentGroup);
            }
        );

        return $preview;
    }

    protected function updateContentGroup(ContentGroup $contentGroup, ContentGroupDefinition $groupDefinition, array $input)
    {
        $contentGroup->imageContentAreas->clear();
        $contentGroup->htmlContentAreas->clear();
        $contentGroup->textContentAreas->clear();
        $contentGroup->metadata->clear();
        $contentGroup->nestedArrayContentGroups->clear();

        foreach ($this->getFieldsInOrder($groupDefinition) as $field) {
            if ($field['type'] === 'html' && !empty($input['html_' . $field['name']])) {

                $contentGroup->htmlContentAreas[] = new HtmlContentArea(
                    $field['name'],
                    $input['html_' . $field['name']]
                );

            } elseif ($field['type'] === 'image' && !empty($input['image_' . $field['name']])) {

                $contentGroup->imageContentAreas[] = new ImageContentArea(
                    $field['name'],
                    $input['image_' . $field['name']] ?? new Image(''),
                    $input['image_alt_text_' . $field['name']] ?? ''
                );

            } elseif ($field['type'] === 'text' && !empty($input['text_' . $field['name']])) {

                $contentGroup->textContentAreas[] = new TextContentArea(
                    $field['name'],
                    $input['text_' . $field['name']]
                );

            } elseif ($field['type'] === 'metadata' && !empty($input['metadata_' . $field['name']])) {

                $contentGroup->metadata[] = new ContentMetadata(
                    $field['name'],
                    $input['metadata_' . $field['name']]
                );

            } elseif ($field['type'] === 'array' && !empty($input['array_' . $field['name']])) {

                foreach ((array)$input['array_' . $field['name']] as $element) {
                    $innerContentGroup = new ContentGroup(
                        '__element__',
                        $field['name'],
                        $this->clock
                    );

                    $this->updateContentGroup($innerContentGroup, $field['definition'], $element);

                    $contentGroup->nestedArrayContentGroups[] = $innerContentGroup;
                }
            }
        }
    }

    private function getFieldsInOrder(ContentGroupDefinition $groupDefinition) : array
    {
        $fieldsInOrder = [];

        foreach ($groupDefinition->htmlAreas as $field) {
            $fieldsInOrder[$field['order']] = $field + ['type' => 'html'];
        }

        foreach ($groupDefinition->images as $field) {
            $fieldsInOrder[$field['order']] = $field + ['type' => 'image'];
        }

        foreach ($groupDefinition->textAreas as $field) {
            $fieldsInOrder[$field['order']] = $field + ['type' => 'text'];
        }

        foreach ($groupDefinition->metadata as $field) {
            $fieldsInOrder[$field['order']] = $field + ['type' => 'metadata'];
        }

        foreach ($groupDefinition->nestedArrayContentGroups as $field) {
            $fieldsInOrder[$field['order']] = $field + ['type' => 'array'];
        }

        ksort($fieldsInOrder, SORT_NUMERIC);

        return $fieldsInOrder;
    }

    protected function defineImageField(CrudFormDefinition $form, array $field)
    {
        $fields = [
            $form->field(
                $this->buildImageUploadField($field)
            )->bindToCallbacks(function (ContentGroup $group) use ($field) {
                return $group->hasImage($field['name']) ? $group->getImage($field['name'])->image : null;
            }, function (ContentGroup $group) {

            }),
        ];

        if (!empty($field['alt_text'])) {
            $fields[] = $form->field(
                $this->buildImageAltTextField($field)
            )->bindToCallbacks(function (ContentGroup $group) use ($field) {
                return $group->hasImage($field['name']) ? $group->getImage($field['name'])->altText : '';
            }, function (ContentGroup $group) {

            });
        }

        $form->continueSection($fields);
    }

    protected function buildImageUploadField(array $field)
    {
        return Field::create('image_' . $field['name'], $field['label'])
            ->image()
            ->moveToPathWithCustomFileName(
                PathHelper::combine($this->config->getImageStorageBasePath(), $this->getName()),
                function (IFile $file) use ($field) {
                    return $field['name'] . '_' . substr(bin2hex(random_bytes(12)), 0, 12) . ($file->getExtension() ? '.' . $file->getExtension() : '');
                }
            );
    }

    protected function buildImageAltTextField(array $field)
    {
        return Field::create('image_alt_text_' . $field['name'], $field['label'] . ' - Alt Text')->string();
    }

    protected function defineHtmlField(CrudFormDefinition $form, array $field)
    {
        $form->continueSection([
            $form->field(
                $this->buildHtmlField($field)
            )->bindToCallbacks(function (ContentGroup $group) use ($field) {
                return $group->hasHtml($field['name']) ? $group->getHtml($field['name'])->html : '';
            }, function (ContentGroup $group) {

            }),
        ]);
    }

    protected function buildHtmlField(array $field)
    {
        return Field::create('html_' . $field['name'], $field['label'])->html();
    }

    protected function defineTextField(CrudFormDefinition $form, array $field)
    {
        $form->continueSection([
            $form->field(
                $this->buildTextField($field)
            )->bindToCallbacks(function (ContentGroup $group) use ($field) {
                return $group->hasText($field['name']) ? $group->getText($field['name'])->text : '';
            }, function (ContentGroup $group) {

            }),
        ]);
    }

    protected function buildTextField(array $field)
    {
        return Field::create('text_' . $field['name'], $field['label'])->string()->defaultTo('');
    }

    protected function defineMetadataField(CrudFormDefinition $form, array $field)
    {
        $form->continueSection([
            $form->field(
                $this->buildMetadataField($field)
            )->bindToCallbacks(function (ContentGroup $group) use ($field) {
                return $group->hasMetadata($field['name']) ? $group->getMetadata($field['name'])->value : '';
            }, function (ContentGroup $group) {

            }),
        ]);
    }

    protected function buildMetadataField(array $field)
    {
        return Field::create('metadata_' . $field['name'], $field['label'])->string()->defaultTo('');
    }

    protected function defineArrayField(CrudFormDefinition $form, array $field)
    {
        $form->continueSection([
            $form->field(
                $this->buildArrayField($field)
            )->bindToCallbacks(function (ContentGroup $group) use ($field) {
                return ContentGroup::collection($group->getArrayOf($field['name']))
                    ->select(function (ContentGroup $contentGroup) use ($field) {
                        return $this->transformContentGroupToValues($contentGroup, $field['definition']);
                    })
                    ->asArray();
            }, function (ContentGroup $group) {

            }),
        ]);
    }

    protected function transformContentGroupToValues(ContentGroup $group, ContentGroupDefinition $groupDefinition)
    {
        $values = [];

        foreach ($this->getFieldsInOrder($groupDefinition) as $field) {

            if ($field['type'] === 'html') {

                $values['html_' . $field['name']] = $group->hasHtml($field['name']) ? $group->getHtml($field['name'])->html : new Html('');

            } elseif ($field['type'] === 'image') {

                $values['image_' . $field['name']] = $group->hasImage($field['name']) ? $group->getImage($field['name'])->image : null;

                if (!empty($field['alt_text'])) {
                    $values['image_alt_text_' . $field['name']] = $group->hasImage($field['name']) ? $group->getImage($field['name'])->altText : null;
                }

            } elseif ($field['type'] === 'text') {

                $values['text_' . $field['name']] = $group->hasText($field['name']) ? $group->getText($field['name'])->text : '';

            } elseif ($field['type'] === 'metadata') {

                $values['metadata_' . $field['name']] = $group->hasMetadata($field['name']) ? $group->getMetadata($field['name'])->value : '';

            } elseif ($field['type'] === 'array') {

                $values['array_' . $field['name']] = [];

                foreach ($group->getArrayOf($field['name']) as $element) {
                    $values['array_' . $field['name']][] = $this->transformContentGroupToValues($element, $field['definition']);
                }
            }
        }

        return $values;
    }

    protected function buildArrayField(array $field)
    {
        return Field::create('array_' . $field['name'], $field['label'])
            ->arrayOf(
                Field::element()->form(
                    Form::create()->section($field['label'], $this->buildNestedArrayElementFields($field['definition']))->build()
                )
            );
    }

    protected function buildNestedArrayElementFields(ContentGroupDefinition $groupDefinition)
    {
        $fields = [];

        foreach ($this->getFieldsInOrder($groupDefinition) as $field) {
            if ($field['type'] === 'html') {
                $fields[] = $this->buildHtmlField($field);
            } elseif ($field['type'] === 'image') {
                $fields[] = $this->buildImageUploadField($field);

                if (!empty($field['alt_text'])) {
                    $fields[] = $this->buildImageAltTextField($field);
                }

            } elseif ($field['type'] === 'text') {
                $fields[] = $this->buildTextField($field);
            } elseif ($field['type'] === 'metadata') {
                $fields[] = $this->buildMetadataField($field);
            } else {
                $fields[] = $this->buildArrayField($field);
            }
        }

        return $fields;
    }
}