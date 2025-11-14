<?php

namespace Repository;

use Models\BookSeries;

/**
 * @method BookSeries find(int $id)
 * @method BookSeries[] findByParams(array $params, array $filters = [])
 */
class BookSeriesRepository extends Repository
{
    protected string $entityModel = BookSeries::class;

    public function getByName(string $name)
    {
        $query = $this->getQuery()
            ->where('LOWER('.BookSeries::TABLE_PREFIX.'.'.BookSeries::PARAM_NAME.') = :name')
            ->limit(1)
            ->bindParams([
                'name' => strtolower(trim($name)),
            ]);

        return $query->fetchColumn();
    }
}