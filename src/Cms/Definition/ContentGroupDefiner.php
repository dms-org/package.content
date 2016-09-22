<?php declare(strict_types = 1);

namespace Dms\Package\Content\Cms\Definition;

/**
 * The content group definer
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentGroupDefiner
{
    /**
     * @var ContentGroupDefinition
     */
    private $contentGroup;

    /**
     * @var int
     */
    private $order = 0;

    /**
     * ContentGroupDefiner constructor.
     *
     * @param ContentGroupDefinition $contentGroup
     */
    public function __construct(ContentGroupDefinition $contentGroup)
    {
        $this->contentGroup = $contentGroup;
    }

    /**
     * @param callable $previewContentCallback
     *
     * @return static
     */
    public function setPreviewCallback(callable $previewContentCallback)
    {
        $this->contentGroup->previewCallback = $previewContentCallback;

        return $this;
    }

    /**
     * @param string $name
     * @param string $label
     *
     * @return static
     */
    public function withImage(string $name, string $label)
    {
        $this->contentGroup->images[$name] = ['name' => $name, 'label' => $label, 'order' => $this->order++];

        return $this;
    }

    /**
     * @param string $name
     * @param string $label
     *
     * @return static
     */
    public function withImageAndAltText(string $name, string $label)
    {
        $this->contentGroup->images[$name] = ['name' => $name, 'label' => $label, 'alt_text' => true, 'order' => $this->order++];

        return $this;
    }

    /**
     * Defines a HTML field.
     *
     * @param string $name
     * @param string $label
     * @param string $containerElementCssSelector
     *
     * @return static
     */
    public function withHtml(string $name, string $label, string $containerElementCssSelector = null)
    {
        $this->contentGroup->htmlAreas[$name] = [
            'name'     => $name,
            'label'    => $label,
            'selector' => $containerElementCssSelector,
            'order'    => $this->order++,
        ];

        return $this;
    }

    /**
     * Defines a text field.
     *
     * @param string $name
     * @param string $label
     *
     * @return static
     */
    public function withText(string $name, string $label)
    {
        $this->contentGroup->textAreas[$name] = ['name' => $name, 'label' => $label, 'order' => $this->order++];

        return $this;
    }

    /**
     * Defines a metadata field.
     *
     * @param string $name
     * @param string $label
     *
     * @return static
     */
    public function withMetadata(string $name, string $label)
    {
        $this->contentGroup->metadata[$name] = ['name' => $name, 'label' => $label, 'order' => $this->order++];

        return $this;
    }

    /**
     * Defines an array field.
     *
     * Example:
     * <code>
     * ->withArrayOf(function ('some-slider', 'Multiple Images', function (ContentGroupDefiner $group) {
     *    $group->withImage(...);
     * })
     * </code>
     *
     * @param string   $name
     * @param string   $label
     * @param callable $elementContentDefinitionCallback
     *
     * @return static
     */
    public function withArrayOf(string $name, string $label, callable $elementContentDefinitionCallback)
    {
        $definition = new ContentGroupDefinition('__element__', $name);
        $elementContentDefinitionCallback(new self($definition));

        $this->contentGroup->nestedArrayContentGroups[$name] = [
            'name'       => $name,
            'label'      => $label,
            'order'      => $this->order++,
            'definition' => $definition,
        ];

        return $this;
    }
}