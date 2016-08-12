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
     * @var array
     */
    private $contentGroup;

    /**
     * @var int
     */
    private $order = 0;

    /**
     * ContentGroupDefiner constructor.
     *
     * @param array $contentGroup
     */
    public function __construct(array &$contentGroup)
    {
        $this->contentGroup =& $contentGroup;
    }

    /**
     * @param string $name
     * @param string $label
     *
     * @return static
     */
    public function withImage(string $name, string $label)
    {
        $this->contentGroup['images'][$name] = ['name' => $name, 'label' => $label, 'order' => $this->order++];

        return $this;
    }

    /**
     * @param string $name
     * @param string $label
     * @param string $containerElementCssSelector
     *
     * @return static
     */
    public function withHtml(string $name, string $label, string $containerElementCssSelector = null)
    {
        $this->contentGroup['html_areas'][$name] = [
            'name'     => $name,
            'label'    => $label,
            'selector' => $containerElementCssSelector,
            'order'    => $this->order++,
        ];

        return $this;
    }

    /**
     * @param string $name
     * @param string $label
     *
     * @return static
     */
    public function withMetadata(string $name, string $label)
    {
        $this->contentGroup['metadata'][$name] = ['name' => $name, 'label' => $label, 'order' => $this->order++];

        return $this;
    }
}