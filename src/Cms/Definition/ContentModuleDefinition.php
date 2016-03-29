<?php declare(strict_types = 1);

namespace Dms\Package\Content\Cms\Definition;

use Dms\Core\Auth\IAuthSystem;
use Dms\Core\Common\Crud\CrudModule;
use Dms\Package\Content\Cms\ContentModule;
use Dms\Package\Content\Core\ContentConfig;
use Dms\Package\Content\Core\Repositories\IContentGroupRepository;

/**
 * The content module definition.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentModuleDefinition
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $icon;

    /**
     * @var array[]
     */
    protected $contentGroups = [];

    /**
     * @var ContentConfig
     */
    protected $config;

    /**
     * ContentModuleDefinition constructor.
     *
     * @param string        $name
     * @param string        $icon
     * @param ContentConfig $config
     */
    public function __construct(string $name, string $icon, ContentConfig $config)
    {
        $this->name   = $name;
        $this->icon   = $icon;
        $this->config = $config;
    }

    /**
     * @param string $name
     * @param string $label
     *
     * @return ContentGroupDefiner
     */
    public function group(string $name, string $label) : ContentGroupDefiner
    {
        $contentGroup = [
            'name'       => $name,
            'label'      => $label,
            'images'     => [],
            'html_areas' => [],
            'metadata'   => [],
        ];

        $this->contentGroups[$name] =& $contentGroup;

        return new ContentGroupDefiner($contentGroup);
    }

    /**
     * @param string      $name
     * @param string      $label
     * @param string|null $pageUrl
     *
     * @return ContentGroupDefiner
     */
    public function page(string $name, string $label, string $pageUrl = null) : ContentGroupDefiner
    {
        $contentGroup = [
            'name'       => $name,
            'label'      => $label,
            'page_url'   => $pageUrl,
            'images'     => [],
            'html_areas' => [],
            'metadata'   => [
                ['name' => 'title', 'label' => 'Title'],
                ['name' => 'description', 'label' => 'Description'],
                ['name' => 'keywords', 'label' => 'Keywords'],
            ],
        ];

        $this->contentGroups[$name] =& $contentGroup;

        return new ContentGroupDefiner($contentGroup);
    }

    /**
     * @param string $name
     * @param string $label
     *
     * @return ContentGroupDefiner
     */
    public function email(string $name, string $label) : ContentGroupDefiner
    {
        $contentGroup = [
            'name'       => $name,
            'label'      => $label,
            'images'     => [],
            'html_areas' => [],
            'metadata'   => [
                ['name' => 'subject', 'label' => 'Subject'],
            ],
        ];

        $this->contentGroups[$name] =& $contentGroup;

        return new ContentGroupDefiner($contentGroup);
    }

    public function loadModule(IContentGroupRepository $repo, IAuthSystem $authSystem) : CrudModule
    {
        return new ContentModule($repo, $authSystem, $this->name, $this->icon, $this->contentGroups, $this->config);
    }
}