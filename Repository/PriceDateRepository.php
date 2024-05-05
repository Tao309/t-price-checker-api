<?php

namespace Repository;

use QueryPdo;
use Models\Entity;

class PriceDateRepository
{
    public function __construct()
    {

    }

    public function savePriceDates(int $positionId, array $priceDates = []): void
    {
        if (empty($priceDates)) {
            return;
        }

        $query = (new QueryPdo())
            ->insert(
                'products_dates',
                [
                    'id' => ':position_id',
                    'price' => ':price',
                    'date' => ':date',
                ],
                'price = VALUES(price)'
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        try {
            foreach($priceDates as $priceDate) {
                $stmt->execute([
                    'position_id' => $positionId,
                    'price' => $priceDate['price'],
                    'date' => $priceDate['date']
                ]);
            }
        } catch(\PDOException $e) {
            processPdoException('savePriceDates', ['position_id' => $positionId], $priceDates, $stmt, $e);
        }
    }

    /**
     * Получаем данные цен для списка продуктов, уже сгруппированные по id.
     *
     * @param array $ids Массив ID товаров.
     *
     * @return array Массив цен.
     */
    public function getPriceDatesForProducts(array $ids = []): array
    {
        $query = (new QueryPdo())
            ->select('*')
            ->from('products_dates')
            ->where('id IN ('.implode(",", $ids).')')
            ->order('date');

        $result = [];

        foreach ($query->fetchAll() as $row) {
            if (!isset($result[$row[Entity::PARAM_ID]])) {
                $result[$row[Entity::PARAM_ID]] = [];
            }

            $result[$row[Entity::PARAM_ID]][] = $row;
        }

        return $result;
    }
}