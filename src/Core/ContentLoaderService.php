<?php declare(strict_types = 1);

namespace Dms\Package\Content\Core;

use Dms\Package\Content\Core\Repositories\IContentGroupRepository;

/**
 * The content service class.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentLoaderService
{
    /**
     * @var ContentConfig
     */
    protected $config;

    /**
     * @var IContentGroupRepository
     */
    protected $contentGroupRepo;

    /**
     * @var LoadedContentGroup[]
     */
    protected $cache;

    /**
     * ContentLoaderService constructor.
     *
     * @param string                  $config
     * @param IContentGroupRepository $contentGroupRepo
     */
    public function __construct($config, IContentGroupRepository $contentGroupRepo)
    {
        $this->config           = $config;
        $this->contentGroupRepo = $contentGroupRepo;
    }

    /**
     * Loads the content group with the supplied name.
     *
     * @param string $moduleAndGroupName eg 'pages.home'
     *
     * @return LoadedContentGroup
     */
    public function load(string $moduleAndGroupName) : LoadedContentGroup
    {
        list($moduleName, $groupName) = explode('.', $moduleAndGroupName);

        if (!isset($this->cache[$moduleAndGroupName])) {
            $groups = $this->contentGroupRepo->matching(
                $this->contentGroupRepo->criteria()
                    ->where(ContentGroup::NAMESPACE, '=', $moduleName)
                    ->where(ContentGroup::NAME, '=', $groupName)
            );

            $this->cache[$moduleAndGroupName] = new LoadedContentGroup($this->config, $groups[0] ?? new ContentGroup($moduleName, $groupName));
        }

        return $this->cache[$moduleAndGroupName];
    }
}