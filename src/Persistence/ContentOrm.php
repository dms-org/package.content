<?php declare(strict_types = 1);

namespace Dms\Package\Content\Persistence;

use Dms\Core\Persistence\Db\Mapping\Definition\Orm\OrmDefinition;
use Dms\Core\Persistence\Db\Mapping\Orm;
use Dms\Package\Content\Core\ContentGroup;

/**
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class ContentOrm extends Orm
{
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