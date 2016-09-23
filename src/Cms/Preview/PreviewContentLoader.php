<?php declare(strict_types = 1);

namespace Dms\Package\Content\Cms\Preview;

use Dms\Package\Content\Core\ContentGroup;
use Dms\Package\Content\Core\ContentLoaderService;
use Dms\Package\Content\Core\LoadedContentGroup;

/**
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class PreviewContentLoader extends ContentLoaderService
{
    /**
     * @var ContentLoaderService
     */
    protected $innerContentLoader;
    
    /**
     * @var ContentGroup[]
     */
    protected $previewContentGroups;

    /**
     * PreviewContentLoader constructor.
     *
     * @param ContentLoaderService $innerContentLoader
     * @param ContentGroup[]       $previewContentGroups
     */
    public function __construct(ContentLoaderService $innerContentLoader, array $previewContentGroups)
    {
        parent::__construct($innerContentLoader->config, $innerContentLoader->contentGroupRepo, $innerContentLoader->clock);
        $this->innerContentLoader   = $innerContentLoader;
        $this->previewContentGroups = $previewContentGroups;
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
        foreach ($this->previewContentGroups as $contentGroup) {
            if ($contentGroup->namespace . '.' . $contentGroup->name === $moduleAndGroupName) {
                return new LoadedContentGroup($this->config, $contentGroup);
            }
        }

        return $this->innerContentLoader->load($moduleAndGroupName);
    }


}