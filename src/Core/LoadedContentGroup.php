<?php declare(strict_types = 1);

namespace Dms\Package\Content\Core;

use Dms\Common\Structure\FileSystem\PathHelper;

/**
 * The loaded content group class.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class LoadedContentGroup
{
    /**
     * @var ContentConfig
     */
    protected $config;

    /**
     * @var ContentGroup
     */
    protected $content;

    /**
     * LoadedContentGroup constructor.
     *
     * @param ContentConfig $config
     * @param ContentGroup  $content
     */
    public function __construct(ContentConfig $config, ContentGroup $content)
    {
        $this->config  = $config;
        $this->content = $content;
    }

    /**
     * @return ContentConfig
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return ContentGroup
     */
    public function getContentGroup() : ContentGroup
    {
        return $this->content;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasHtml(string $name) : bool
    {
        return $this->content->hasHtml($name);
    }

    /**
     * @param string $name
     * @param string $default
     *
     * @return string
     */
    public function getHtml(string $name, string $default = '') : string
    {
        $contentArea = $this->content->getHtml($name);

        return $contentArea ? $contentArea->html->asString() : $default;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasImage(string $name) : bool
    {
        return $this->content->hasImage($name);
    }

    /**
     * @param string $name
     * @param string $default
     *
     * @return string
     */
    public function getImageUrl(string $name, string $default = '') : string
    {
        $contentArea = $this->content->getImage($name);

        if ($contentArea) {
            $imagePath = PathHelper::normalize($this->config->getImageStorageBasePath());
            
            return rtrim($this->config->getImageBaseUrl(), '/') . '/' . ltrim(strtr($contentArea->image->getFullPath(), [$imagePath => '', '\\' => '/']), '/');
        }

        return $default;
    }

    /**
     * @param string $name
     * @param string $default
     *
     * @return string
     */
    public function getImageAltText(string $name, string $default = '') : string
    {
        $contentArea = $this->content->getImage($name);

        return ($contentArea ? $contentArea->altText : null) ?? $default;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasMetadata(string $name) : bool
    {
        return $this->content->hasMetadata($name);
    }

    /**
     * @param string $name
     * @param string $default
     *
     * @return string
     */
    public function getMetadata(string $name, string $default = '') : string
    {
        $metadata = $this->content->getMetadata($name);

        return $metadata ? $metadata->value : $default;
    }

    /**
     * @return string
     */
    public function renderMetadataAsHtml() : string
    {
        $metadata = [];

        foreach ($this->content->metadata as $metadataItem) {
            $name  = htmlentities($metadataItem->name, ENT_QUOTES);
            $value = htmlentities($metadataItem->value, ENT_QUOTES);

            if ($metadataItem->name === 'title') {
                $metadata[] = '<title>' . $value . '</title>';
            } else {
                $metadata[] = '<meta name="' . $name . '" content="' . $value . '" />';
            }
        }

        return implode(PHP_EOL, $metadata);
    }
}