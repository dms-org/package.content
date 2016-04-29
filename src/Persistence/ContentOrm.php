<?php declare(strict_types = 1);

namespace Dms\Package\Content\Persistence;

use Dms\Core\Ioc\IIocContainer;
use Dms\Core\Persistence\Db\Mapping\Definition\Orm\OrmDefinition;
use Dms\Core\Persistence\Db\Mapping\Orm;
use Dms\Package\Content\Core\ContentGroup;

/**
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentOrm extends Orm
{
    /**
     * Orm constructor.
     *
     * @param IIocContainer $iocContainer
     */
    public function __construct(IIocContainer $iocContainer)
    {
        parent::__construct($iocContainer);
    }

    /**
     * Defines the object mappers registered in the orm.
     *
     * @param OrmDefinition $orm
     *
     * @return void
     */
    protected function define(OrmDefinition $orm)
    {
        $orm->entities([
            ContentGroup::class => ContentGroupMapper::class,
        ]);
    }
}