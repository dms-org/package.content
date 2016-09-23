<?php declare(strict_types = 1);

namespace Dms\Package\Content\Cms\Preview;

use Dms\Common\Structure\FileSystem\PathHelper;
use Dms\Common\Structure\Web\Html;
use Dms\Core\Util\DateTimeClock;
use Dms\Package\Content\Core\ContentGroup;
use Dms\Package\Content\Core\ContentLoaderService;
use Dms\Package\Content\Core\HtmlContentArea;
use Dms\Package\Content\Core\ImageContentArea;
use Dms\Package\Content\Core\LoadedContentGroup;
use Dms\Package\Content\Core\TextContentArea;

/**
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class InterpolatingContentLoaderServiceProxy extends ContentLoaderService
{
    const START_MARKER = '!~~~@###!';
    const CONTENT_ID_AND_AREA_NAME_SEPARATOR = '!:!:!';
    const END_OF_CONTENT_ID_MARKER = ':!:!:';
    const END_MARKER = '!~~~@###!';

    /**
     * @var ContentLoaderService
     */
    protected $actualContentLoaderService;

    /**
     * ContentLoaderService constructor.
     *
     * @param ContentLoaderService $contentLoaderService
     */
    public function __construct(ContentLoaderService $contentLoaderService)
    {
        $this->config                     = $contentLoaderService->config;
        $this->actualContentLoaderService = $contentLoaderService;
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
        $loadedContentGroup = $this->actualContentLoaderService->load($moduleAndGroupName);

        return new LoadedContentGroup(
            $loadedContentGroup->getConfig(),
            $this->createPreviewContentGroup($loadedContentGroup->getContentGroup())
        );
    }

    private function createPreviewContentGroup(ContentGroup $contentGroup) : ContentGroup
    {
        $newContentGroup            = new ContentGroup($contentGroup->namespace, $contentGroup->name, new DateTimeClock());
        $newContentGroup->updatedAt = $contentGroup->updatedAt;

        $newContentGroup->metadata = $contentGroup->metadata;

        foreach ($contentGroup->htmlContentAreas as $contentArea) {
            $newContentGroup->htmlContentAreas[] = new HtmlContentArea(
                $contentArea->name,
                new Html($this->wrapHtmlWithPreviewTags($contentGroup, $contentArea))
            );
        }

        foreach ($contentGroup->imageContentAreas as $contentArea) {
            $newContentGroup->imageContentAreas[] = new ImageContentArea(
                $contentArea->name,
                new ImageWithOverriddenValidationProxy($this->embedInFileName($contentGroup, $contentArea), $contentArea->image->getClientFileName(), $contentArea->image->isValidImage()),
                $this->embeddedInAltText($contentGroup, $contentArea)
            );
        }

        foreach ($contentGroup->textContentAreas as $contentArea) {
            $newContentGroup->textContentAreas[] = new TextContentArea(
                $contentArea->name,
                $this->embeddedInText($contentGroup, $contentArea)
            );
        }

        foreach ($contentGroup->nestedArrayContentGroups as $nestedArrayContentGroup) {
            $newContentGroup->nestedArrayContentGroups[] = $this->createPreviewContentGroup($nestedArrayContentGroup);
        }

        return $newContentGroup;
    }

    private function wrapHtmlWithPreviewTags(ContentGroup $contentGroup, HtmlContentArea $contentArea) : string
    {
        return self::START_MARKER . $contentGroup->getId() . self::CONTENT_ID_AND_AREA_NAME_SEPARATOR . $contentArea->name . self::END_OF_CONTENT_ID_MARKER . $contentArea->html->asString() . self::END_MARKER;
    }

    private function embedInFileName(ContentGroup $contentGroup, ImageContentArea $contentArea) : string
    {
        $fullPath = PathHelper::normalize($contentArea->image->getFullPath());
        $basePath = PathHelper::normalize($this->config->getImageStorageBasePath());

        $relativePath = substr($fullPath, strlen($basePath));

        return rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . self::START_MARKER . $contentGroup->getId() . self::CONTENT_ID_AND_AREA_NAME_SEPARATOR
        . $contentArea->name . self::END_OF_CONTENT_ID_MARKER
        . ltrim($relativePath, DIRECTORY_SEPARATOR)
        . self::END_MARKER;
    }

    private function embeddedInAltText(ContentGroup $contentGroup, ImageContentArea $contentArea) : string
    {
        return self::START_MARKER . $contentGroup->getId() . self::CONTENT_ID_AND_AREA_NAME_SEPARATOR . $contentArea->name . self::END_OF_CONTENT_ID_MARKER . $contentArea->altText . self::END_MARKER;
    }

    private function embeddedInText(ContentGroup $contentGroup, TextContentArea $contentArea) : string
    {
        return self::START_MARKER . $contentGroup->getId() . self::CONTENT_ID_AND_AREA_NAME_SEPARATOR . $contentArea->name . self::END_OF_CONTENT_ID_MARKER . $contentArea->text . self::END_MARKER;
    }
}