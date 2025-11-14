<?php

namespace Repository;

use Models\Book;
use Models\PublishingHouse;

/**
 * @method PublishingHouse find(int $id)
 * @method PublishingHouse[] findByParams(array $params, array $filters = [])
 */
class PublishingHouseRepository extends Repository
{
    protected string $entityModel = PublishingHouse::class;

    public function getByName(string $name)
    {
        $query = $this->getQuery()
            ->where('LOWER('.PublishingHouse::TABLE_PREFIX.'.'.PublishingHouse::PARAM_NAME.') = :name')
            ->limit(1)
            ->bindParams([
                'name' => strtolower(trim($name)),
            ]);

        return $query->fetchColumn();
    }
}