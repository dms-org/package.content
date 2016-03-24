<?php declare(strict_types = 1);

namespace Dms\Package\Content\Persistence;

use Dms\Core\Persistence\Db\Connection\IConnection;
use Dms\Core\Persistence\Db\Mapping\IOrm;
use Dms\Core\Persistence\DbRepository;
use Dms\Package\Content\Core\ContentGroup;
use Dms\Package\Content\Core\Repositories\IContentGroupRepository;

/**
 * @author Elliot Levin <elliotlevin@hotmail.com>
 */
class DbContentGroupRepository extends DbRepository implements IContentGroupRepository
{
    public function __construct(IConnection $connection, IOrm $orm)
    {
        parent::__construct($connection, $orm->getEntityMapper(ContentGroup::class));
    }
}