<?php

namespace Repository;

use Models\PriceDate;
use Models\Product;
use Models\SameProduct;
use QueryPdo;
use Core\Config;

class SameProductRepository extends Repository
{
    protected string $entityModel = SameProduct::class;

    public function __construct()
    {
        parent::__construct();
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
            ->select([PriceDate::PARAM_ID, 'MIN('.PriceDate::PARAM_PRICE.') AS price'])
            ->from(PriceDate::TABLE_NAME)
            ->group(PriceDate::PARAM_ID);

        $bookSubQuery = (new QueryPdo())
            ->select(['DISTINCT book_id'])
            ->from(Product::TABLE_NAME)
            ->where('id IN ('.implode(",", $ids).')')
        ;

        $query = $this->getListQueryNew();

        $query->leftJoin(
                ['pd' => '('.$priceDatesSubQuery->assemble().')'],
                'pd.id = '.SameProduct::TABLE_PREFIX.'.id',
                [
                    'pd.price AS ' . Product::PARAM_MIN_PRICE
                ]
            )
//            ->where('p.shop_id != :shop_id')
            ->where(SameProduct::TABLE_PREFIX . '.user_id = :user_id')
            ->where(SameProduct::TABLE_PREFIX . '.book_id IN ('.$bookSubQuery->assemble().')')
            ->order('pd.price');

        $rows = $query->fetchAll([
//            'shop_id' => Config::getCurrentShopId(),
            Product::PARAM_USER_ID => Config::getCurrentUserid(),
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
}