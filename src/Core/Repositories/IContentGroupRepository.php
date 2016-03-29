<?php declare(strict_types = 1);

namespace Dms\Package\Content\Core\Repositories;

use Dms\Core\Model\ICriteria;
use Dms\Core\Model\ISpecification;
use Dms\Core\Persistence\IRepository;
use Dms\Package\Content\Core\ContentGroup;

/**
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
interface IContentGroupRepository extends IRepository
{
    /**
     * {@inheritDoc}
     *
     * @return ContentGroup[]
     */
    public function getAll() : array;

    /**
     * {@inheritDoc}
     *
     * @return ContentGroup
     */
    public function get($id);

    /**
     * {@inheritDoc}
     *
     * @return ContentGroup[]
     */
    public function getAllById(array $ids) : array;

    /**
     * {@inheritDoc}
     *
     * @return ContentGroup|null
     */
    public function tryGet($id);

    /**
     * {@inheritDoc}
     *
     * @return ContentGroup[]
     */
    public function tryGetAll(array $ids) : array;

    /**
     * {@inheritDoc}
     *
     * @return ContentGroup[]
     */
    public function matching(ICriteria $criteria) : array;

    /**
     * {@inheritDoc}
     *
     * @return ContentGroup[]
     */
    public function satisfying(ISpecification $specification) : array;
}