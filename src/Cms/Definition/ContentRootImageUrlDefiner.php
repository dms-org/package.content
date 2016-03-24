<?php declare(strict_types = 1);

namespace Dms\Package\Content\Cms\Definition;

/**
 * The content root image url definer class.
 *
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentRootImageUrlDefiner
{
    /**
     * @var callable
     */
    protected $callback;

    /**
     * ContentRootImageUrlDefiner constructor.
     *
     * @param callable $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Defines the root url which the stored images are accessible from.
     *
     * @param string $rootImageUrl
     *
     * @return void
     */
    public function mappedToUrl(string $rootImageUrl)
    {
        call_user_func($this->callback, $rootImageUrl);
    }
}