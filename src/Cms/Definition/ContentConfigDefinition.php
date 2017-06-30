<?php declare(strict_types=1);

namespace Dms\Package\Content\Cms\Definition;

use Dms\Core\Exception\InvalidOperationException;
use Dms\Package\Content\Core\ContentConfig;

/**
 * The content config definition.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentConfigDefinition
{
    /**
     * @var string
     */
    protected $imageStorageFilePath;

    /**
     * @var string
     */
    protected $rootImageUrl;

    /**
     * @var string
     */
    protected $fileStorageFilePath;

    /**
     * Defines the file path which to store the uploaded images under.
     *
     * @param string $filePath
     *
     * @return self
     */
    public function storeImagesUnder(string $filePath): self
    {
        $this->imageStorageFilePath = $filePath;

        return $this;
    }

    /**
     * Defines the root url which the stored images are accessible from.
     *
     * @param string $rootImageUrl
     *
     * @return self
     */
    public function mappedToUrl(string $rootImageUrl): self
    {
        $this->rootImageUrl = $rootImageUrl;

        return $this;
    }

    /**
     * Defines the file path which to store the uploaded files under.
     *
     * @param string $filePath
     *
     * @return self
     */
    public function storeFilesUnder(string $filePath): self
    {
        $this->fileStorageFilePath = $filePath;

        return $this;
    }

    /**
     * @return ContentConfig
     * @throws InvalidOperationException
     */
    public function finalize(): ContentConfig
    {
        if (!$this->imageStorageFilePath || !$this->rootImageUrl) {
            throw InvalidOperationException::format(
                'Incomplete content config definition: must call the storeImagesUnder method'
            );
        }

        return new ContentConfig($this->imageStorageFilePath, $this->rootImageUrl, $this->fileStorageFilePath ?: $this->imageStorageFilePath);
    }
}