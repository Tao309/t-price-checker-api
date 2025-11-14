<?php

namespace Repository;

use Models\PublishingBrand;

/**
 * @method PublishingBrand find(int $id)
 * @method PublishingBrand[] findByParams(array $params, array $filters = [])
 */
class PublishingBrandRepository extends Repository
{
    protected string $entityModel = PublishingBrand::class;

    public function getByName(string $name)
    {
        $query = $this->getQuery()
            ->where('LOWER('.PublishingBrand::TABLE_PREFIX.'.'.PublishingBrand::PARAM_NAME.') = :name')
            ->limit(1)
            ->bindParams([
                'name' => strtolower(trim($name)),
            ]);

        return $query->fetchColumn();
    }
}