<?php

namespace Repository;

use Models\PriceDate;
use Models\Product;
use Models\ProductUserData;
use Models\SameProduct;
use PullRepository\SameProductPullRepository;
use QueryPdo;
use Core\Config;

class SameProductRepository extends Repository
{
    protected string $entityModel = SameProduct::class;

    public function getRowsByProductIds(array $ids): array
    {
        $priceDatesSubQuery = (new QueryPdo())
            ->select([PriceDate::PARAM_ID, 'MIN('.PriceDate::PARAM_PRICE.') AS price'])
            ->from(PriceDate::TABLE_NAME)
            ->group(PriceDate::PARAM_ID);

        $bookSubQuery = (new QueryPdo())
            ->select(['DISTINCT book_id'])
            ->from(Product::TABLE_NAME)
            ->where('id', $ids);

        $sourceProductSubQuery = (new QueryPdo())
            ->select(['DISTINCT source_product_id'])
            ->from(Product::TABLE_NAME)
            ->where('id', $ids);

        $query = $this->getQuery();

        $spPrefix = SameProduct::TABLE_PREFIX;

        $query->leftJoin(
            ['pd' => '('.$priceDatesSubQuery->assemble().')'],
            'pd.id = ' . $spPrefix . '.id',
            [
                'pd.price AS ' . Product::PARAM_MIN_PRICE
            ]
        )
        ->where(
            '('. $spPrefix . '.book_id IN(' . $bookSubQuery->assemble(). ')'
            . ' OR ' . $spPrefix . '.source_product_id IN (' . $sourceProductSubQuery->assemble() . '))'
        )
        ->order('pd.price');

        return $query->fetchAll();
    }
}