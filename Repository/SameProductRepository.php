<?php

namespace Repository;

use QueryPdo;
use Core\Config;
use Models\SameProduct;

class SameProductRepository
{
    public function __construct()
    {

    }

    /**
     * Получение похожих товаров с других магазинов, относительно текущего, уже сгруппированные по book_id.
     *
     * @param array $ids Массив id товаров.
     *
     * @return array Массив похожих товаров.
     */
    public function getAllSameProductsByBook(array $ids): array
    {
        $priceDatesSubQuery = (new QueryPdo())
            ->select(['id', 'MIN(price) AS price'])
            ->from('products_dates')
            ->group('id');

        $bookSubQuery = (new QueryPdo())
            ->select(['DISTINCT book_id'])
            ->from('products')
            ->where('id IN ('.implode(",", $ids).')')
        ;

        $query = (new QueryPdo())
            ->select([
                'p.id',
                'p.product_id',
                'p.book_id',
                'p.available'
            ])
            ->from(['p' => 'products'])
            ->rightJoin(
                ['s' => 'shops'],
                'p.shop_id = s.id',
                's.type AS shop_type'
            )
            ->leftJoin(
                ['pd' => '('.$priceDatesSubQuery->assemble().')'],
                'pd.id = p.id',
                [
                    'pd.price'
                ]
            )
            ->where('p.shop_id != :shop_id')
            ->where('p.user_id = :user_id')
            ->where('p.book_id IN ('.$bookSubQuery->assemble().')')
            ->order('pd.price');

        $rows = $query->fetchAll([
            'shop_id' => Config::getCurrentShopId(),
            'user_id' => Config::getCurrentUserid(),
        ]);

        $result = [];

        foreach ($rows as $row) {
            if (!isset($result[$row[SameProduct::PARAM_BOOK_ID]])) {
                $result[$row[SameProduct::PARAM_BOOK_ID]] = [];
            }

            $result[$row[SameProduct::PARAM_BOOK_ID]][] = $row;
        }

        return $result;
    }
}