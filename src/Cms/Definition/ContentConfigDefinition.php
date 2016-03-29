<?php declare(strict_types = 1);

namespace Dms\Package\Content\Cms\Definition;

use Dms\Core\Auth\IAuthSystem;
use Dms\Core\Exception\InvalidOperationException;
use Dms\Core\Ioc\IIocContainer;
use Dms\Core\Package\Definition\PackageDefinition;
use Dms\Package\Content\Core\ContentConfig;
use Dms\Package\Content\Core\Repositories\IContentGroupRepository;

/**
 * The content config definition.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentConfigDefinition
{
    /**
     * @var ContentConfig
     */
    protected $config;

    /**
     * Defines the file path which to store the uploaded images under.
     *
     * @param string $filePath
     *
     * @return ContentRootImageUrlDefiner
     */
    public function storeImagesUnder(string $filePath) : ContentRootImageUrlDefiner
    {
        return new ContentRootImageUrlDefiner(function (string $rootImageUrl) use ($filePath) {
            $this->config = new ContentConfig($filePath, $rootImageUrl);
        });
    }

    /**
     * @return ContentConfig
     * @throws InvalidOperationException
     */
    public function finalize() : ContentConfig
    {
        if (!$this->config) {
            throw InvalidOperationException::format(
                'Incomplete content config definition: must call the storeImagesUnder method'
            );
        }

        return $this->config;
    }
}