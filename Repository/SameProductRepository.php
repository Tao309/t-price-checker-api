<?php

namespace Repository;

use Models\PriceDate;
use Models\Product;
use Models\ProductUserData;
use Models\SameProduct;
use QueryPdo;
use Core\Config;

class SameProductRepository extends Repository
{
    protected string $entityModel = SameProduct::class;

    public function getRowsByProductIds(array $ids): array
    {
        return $this->getRows($ids);
    }

    /**
     * Получение похожих товаров с других магазинов, относительно текущего,
     * уже сгруппированные по book_id, source_product_id.
     *
     * @param array $ids Массив id товаров.
     *
     * @return array Массив похожих товаров.
     */
    public function getAllSameProducts(array $ids): array
    {
        $rows = $this->getRows($ids);

        $result = [];

        foreach ($rows as $row) {
            if (isset($row[Product::PARAM_SOURCE_PRODUCT_ID])) {
                $sourceProductId = $row[Product::PARAM_SOURCE_PRODUCT_ID];

                if (!isset($result['source-product-' . $sourceProductId])) {
                    $result['source-product-' . $sourceProductId] = [];
                }

                $result['source-product-' . $sourceProductId][] = $row;
                continue;
            }

            if (isset($row[Product::PARAM_BOOK_ID])) {
                $bookId = $row[Product::PARAM_BOOK_ID];

                if (!isset($result['book-' . $bookId])) {
                    $result['book-' . $bookId] = [];
                }

                $result['book-' . $bookId][] = $row;
            }
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

            if ($sameProductsRow[Product::PARAM_SHOP_PRODUCT_ID] === $productData[Product::PARAM_SHOP_PRODUCT_ID]) {
                $toUnlink = true;
            } else if ($sameProductsRow['shop.type'] === $productData['shop.type']) {
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

    private function getRows(array $ids): array
    {
        $priceDatesSubQuery = (new QueryPdo())
            ->select([PriceDate::PARAM_ID, 'MIN('.PriceDate::PARAM_PRICE.') AS price'])
            ->from(PriceDate::TABLE_NAME)
            ->group(PriceDate::PARAM_ID);

        $bookSubQuery = (new QueryPdo())
            ->select(['DISTINCT book_id'])
            ->from(Product::TABLE_NAME)
            ->where('id', $ids)
        ;

        $sourceProductSubQuery = (new QueryPdo())
            ->select(['DISTINCT source_product_id'])
            ->from(Product::TABLE_NAME)
            ->where('id', $ids)
        ;

        $query = $this->getQuery();

        $spPrefix = SameProduct::TABLE_PREFIX;

        $query->leftJoin(
            ['pd' => '('.$priceDatesSubQuery->assemble().')'],
            'pd.id = ' . $spPrefix . '.id',
            [
                'pd.price AS ' . Product::PARAM_MIN_PRICE
            ]
        )
            ->leftJoin(
                [
                    'pud' => ProductUserData::TABLE_NAME
                ],
                'pud.product_id = same_p.id',
                [
                    'pud.available'
                ]
            )
            ->where('pud.user_id = :user_id')
            ->where(
                '('. $spPrefix . '.book_id IN(' . $bookSubQuery->assemble(). ')'
                . ' OR ' . $spPrefix . '.source_product_id IN (' . $sourceProductSubQuery->assemble() . '))'
            )
            ->order('pd.price')
            ->bindParams([
                ProductUserData::PARAM_USER_ID => Config::getCurrentUserid()
            ]);

        return $query->fetchAll();
    }
}