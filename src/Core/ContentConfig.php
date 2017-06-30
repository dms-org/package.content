<?php declare(strict_types = 1);

namespace Dms\Package\Content\Core;
use Dms\Common\Structure\FileSystem\Directory;

/**
 * The content configuration class.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentConfig
{
    /**
     * @var string
     */
    protected $imageStorageBasePath;

    /**
     * @var string
     */
    protected $imageBaseUrl;

    /**
     * @var string
     */
    protected $fileStorageBasePath;

    /**
     * ContentConfig constructor.
     *
     * @param string $imageStorageBasePath
     * @param string $imageBaseUrl
     * @param string $fileStorageBasePath
     */
    public function __construct(string $imageStorageBasePath, string $imageBaseUrl, string $fileStorageBasePath)
    {
        $this->imageStorageBasePath = (new Directory($imageStorageBasePath))->getFullPath();
        $this->imageBaseUrl         = $imageBaseUrl;
        $this->fileStorageBasePath = $fileStorageBasePath;
    }

    /**
     * @return string
     */
    public function getImageStorageBasePath() : string
    {
        return $this->imageStorageBasePath;
    }

    /**
     * @return string
     */
    public function getImageBaseUrl() : string
    {
        return $this->imageBaseUrl;
    }

    /**
     * @return string
     */
    public function getFileStorageBasePath() : string
    {
        return $this->fileStorageBasePath;
    }
}