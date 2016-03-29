<?php declare(strict_types = 1);

namespace Dms\Package\Content\Core;

use Dms\Core\Exception\InvalidArgumentException;
use Dms\Core\Util\IClock;
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
     * @var IClock
     */
    protected $clock;

    /**
     * ContentLoaderService constructor.
     *
     * @param ContentConfig                  $config
     * @param IContentGroupRepository $contentGroupRepo
     * @param IClock                  $clock
     */
    public function __construct(ContentConfig $config, IContentGroupRepository $contentGroupRepo, IClock $clock)
    {
        $this->config           = $config;
        $this->contentGroupRepo = $contentGroupRepo;
        $this->clock            = $clock;
    }

    /**
     * Loads the content group with the supplied name.
     *
     * @param string $moduleAndGroupName eg 'pages.home'
     *
     * @return LoadedContentGroup
     * @throws InvalidArgumentException
     */
    public function load(string $moduleAndGroupName) : LoadedContentGroup
    {
        if (substr_count($moduleAndGroupName, '.') !== 1) {
            throw InvalidArgumentException::format(
                'Invalid content group name supplied to %s: expecting format \'module-name.group-name\', \'%s\' given',
                __METHOD__, $moduleAndGroupName
            );
        }

        list($moduleName, $groupName) = explode('.', $moduleAndGroupName);

        if (!isset($this->cache[$moduleAndGroupName])) {
            $groups = $this->contentGroupRepo->matching(
                $this->contentGroupRepo->criteria()
                    ->where(ContentGroup::NAMESPACE, '=', $moduleName)
                    ->where(ContentGroup::NAME, '=', $groupName)
            );

            $this->cache[$moduleAndGroupName] = new LoadedContentGroup($this->config, $groups[0] ?? new ContentGroup($moduleName, $groupName, $this->clock));
        }

        return $this->cache[$moduleAndGroupName];
    }
}