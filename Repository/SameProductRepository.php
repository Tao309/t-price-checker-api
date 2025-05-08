<?php

namespace Repository;

use Core\Config;
use Models\Entity;
use Models\PriceDate;
use Models\Product;
use Models\ProductUserData;
use Models\SameProduct;
use Query\QueryPdo;

class SameProductRepository extends Repository
{
    protected string $entityModel = SameProduct::class;

    /**
     * Получаем строки товаров по книге.
     *
     * @param array $ids
     * @return array
     * @throws \Exception
     */
    public function getRowsBySourceProductIds(array $ids): array
    {
        $currentUserId = Config::getCurrentUserid();

        $priceDatesSubQuery = (new QueryPdo())
            ->select([PriceDate::PARAM_ID, 'MIN('.PriceDate::PARAM_PRICE.') AS price'])
            ->from(PriceDate::TABLE_NAME)
            ->where(PriceDate::PARAM_USER_ID, $currentUserId)
            ->group(PriceDate::PARAM_ID);

//        $sourceSubQueryUserPrefix = 'sub_sp_userdata';
//        $sourceProductSubQuery = (new QueryPdo())
//            ->select(['DISTINCT sub_sp_p.source_product_id'])
//            ->from(['sub_sp_p' => Product::TABLE_NAME])
//            ->leftJoin(
//                [$sourceSubQueryUserPrefix => ProductUserData::TABLE_NAME],
//                sprintf(
//                    'sub_sp_p.%s = %s.%s AND %s.%s = %s',
//                    Entity::PARAM_ID,
//                    $sourceSubQueryUserPrefix,
//                    ProductUserData::PARAM_PRODUCT_ID,
//                    $sourceSubQueryUserPrefix,
//                    ProductUserData::PARAM_USER_ID,
//                    $currentUserId
//                )
//            )
//            ->where(sprintf('%s.%s iS NOT NULL', $sourceSubQueryUserPrefix, ProductUserData::PARAM_PRODUCT_ID))
//            ->where('sub_sp_p.id', $ids);

        $query = $this->getQuery();

        $spPrefix = SameProduct::TABLE_PREFIX;

        $query
            ->leftJoin(
                ['pd' => '('.$priceDatesSubQuery->assemble().')'],
                'pd.id = ' . $spPrefix . '.id',
                [
                    'pd.price AS ' . Product::PARAM_MIN_PRICE
                ]
            )
            ->where($spPrefix . '.source_product_id', $ids)
//            ->where(
//                $spPrefix . '.source_product_id IN (' . $sourceProductSubQuery->assemble() . ')'
//            )
            ->order('pd.price');

        return $query->fetchAll();
    }

    /**
     * Получаем строки товаров по книге.
     *
     * @param array $ids
     * @return array
     * @throws \Exception
     */
    public function getRowsByBookIds(array $ids): array
    {
        $currentUserId = Config::getCurrentUserid();

        $priceDatesSubQuery = (new QueryPdo())
            ->select([PriceDate::PARAM_ID, 'MIN('.PriceDate::PARAM_PRICE.') AS price'])
            ->from(PriceDate::TABLE_NAME)
            ->where(PriceDate::PARAM_USER_ID, $currentUserId)
            ->group(PriceDate::PARAM_ID);

//        $bookSubQueryUserPrefix = 'sub_book_userdata';
//        $bookSubQuery = (new QueryPdo())
//            ->select(['DISTINCT sub_b_p.book_id'])
//            ->from(['sub_b_p' => Product::TABLE_NAME])
//            ->leftJoin(
//                [$bookSubQueryUserPrefix => ProductUserData::TABLE_NAME],
//                sprintf(
//                    'sub_b_p.%s = %s.%s AND %s.%s = %s',
//                    Entity::PARAM_ID,
//                    $bookSubQueryUserPrefix,
//                    ProductUserData::PARAM_PRODUCT_ID,
//                    $bookSubQueryUserPrefix,
//                    ProductUserData::PARAM_USER_ID,
//                    $currentUserId
//                )
//            )
//            ->where(sprintf('%s.%s iS NOT NULL', $bookSubQueryUserPrefix, ProductUserData::PARAM_PRODUCT_ID))
//            ->where('sub_b_p.id', $ids);

        $query = $this->getQuery();

        $spPrefix = SameProduct::TABLE_PREFIX;
        $pudPrefix = ProductUserData::TABLE_PREFIX;

        $query
            ->leftJoin(
                ['pd' => '('.$priceDatesSubQuery->assemble().')'],
                'pd.id = ' . $spPrefix . '.id',
                [
                    'pd.price AS ' . Product::PARAM_MIN_PRICE
                ]
            )
            ->where($spPrefix . '.book_id', $ids)
//            ->where($pudPrefix. '.user_id', $currentUserId)
//            ->where(
//                $spPrefix . '.book_id IN(' . $bookSubQuery->assemble(). ')'
//            )
            ->order('pd.price');

        return $query->fetchAll();
    }

    public function getRowsByProductIds(array $ids): array
    {
        $currentUserId = Config::getCurrentUserid();

        $priceDatesSubQuery = (new QueryPdo())
            ->select([PriceDate::PARAM_ID, 'MIN('.PriceDate::PARAM_PRICE.') AS price'])
            ->from(PriceDate::TABLE_NAME)
            ->where(PriceDate::PARAM_USER_ID, $currentUserId)
            ->group(PriceDate::PARAM_ID);

        $bookSubQueryUserPrefix = 'sub_book_userdata';
        $bookSubQuery = (new QueryPdo())
            ->select(['DISTINCT sub_b_p.book_id'])
            ->from(['sub_b_p' => Product::TABLE_NAME])
            ->leftJoin(
                [$bookSubQueryUserPrefix => ProductUserData::TABLE_NAME],
                sprintf(
                    'sub_b_p.%s = %s.%s AND %s.%s = %s',
                    Entity::PARAM_ID,
                    $bookSubQueryUserPrefix,
                    ProductUserData::PARAM_PRODUCT_ID,
                    $bookSubQueryUserPrefix,
                    ProductUserData::PARAM_USER_ID,
                    $currentUserId
                )
            )
            ->where(sprintf('%s.%s iS NOT NULL', $bookSubQueryUserPrefix, ProductUserData::PARAM_PRODUCT_ID))
            ->where('sub_b_p.id', $ids);

        $sourceSubQueryUserPrefix = 'sub_sp_userdata';
        $sourceProductSubQuery = (new QueryPdo())
            ->select(['DISTINCT sub_sp_p.source_product_id'])
            ->from(['sub_sp_p' => Product::TABLE_NAME])
            ->leftJoin(
                [$sourceSubQueryUserPrefix => ProductUserData::TABLE_NAME],
                sprintf(
                    'sub_sp_p.%s = %s.%s AND %s.%s = %s',
                    Entity::PARAM_ID,
                    $sourceSubQueryUserPrefix,
                    ProductUserData::PARAM_PRODUCT_ID,
                    $sourceSubQueryUserPrefix,
                    ProductUserData::PARAM_USER_ID,
                    $currentUserId
                )
            )
            ->where(sprintf('%s.%s iS NOT NULL', $sourceSubQueryUserPrefix, ProductUserData::PARAM_PRODUCT_ID))
            ->where('sub_sp_p.id', $ids);

        $query = $this->getQuery();

        $spPrefix = SameProduct::TABLE_PREFIX;

        $query
            ->leftJoin(
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