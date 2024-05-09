<?php

namespace Repository;

use http\Params;
use Models\Product;
use QueryPdo;
use Core\Config;

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
                'p.code',
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
                    'pd.price AS min_price'
                ]
            )
//            ->where('p.shop_id != :shop_id')
            ->where('p.user_id = :user_id')
            ->where('p.book_id IN ('.$bookSubQuery->assemble().')')
            ->order('pd.price');

        $rows = $query->fetchAll([
//            'shop_id' => Config::getCurrentShopId(),
            'user_id' => Config::getCurrentUserid(),
        ]);

        $result = [];

        foreach ($rows as $row) {
            if (!isset($result[$row[Product::PARAM_BOOK_ID]])) {
                $result[$row[Product::PARAM_BOOK_ID]] = [];
            }

            $result[$row[Product::PARAM_BOOK_ID]][] = $row;
        }

        return $result;
    }

    /**
     * Обработка похожих товаров для позиции текущей по книге. Выводим первый товар по текущему магазину выше других.
     *
     * @param int   $productData Данные текущего товара.
     * @param array $rows        Массив похохиж товаров.
     *
     * @return array Массив похожих товаров, после обработки.
     */
    public function prepareSameProducts(array $productData, array $sameProductsRows): array
    {
        $sameProductByShop = null;

        foreach ($sameProductsRows as $index => $sameProductsRow) {
            $toUnlink = false;

            if ($sameProductsRow[Product::PARAM_PRODUCT_ID] === $productData[Product::PARAM_PRODUCT_ID]) {
                $toUnlink = true;
            } else if ($sameProductsRow[Product::PARAM_SHOP_TYPE] === $productData[Product::PARAM_SHOP_TYPE]) {
                if (!$sameProductByShop) {
                    $sameProductByShop = $sameProductsRow;
                }

                $toUnlink = true;
            }

            if ($toUnlink) {
                unset($sameProductsRows[$index]);
            }
        }

        if ($sameProductByShop) {
            array_unshift($sameProductsRows, $sameProductByShop);
        }

        return $sameProductsRows;
    }
}