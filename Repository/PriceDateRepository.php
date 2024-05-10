<?php

namespace Repository;

use Models\PriceDate;
use Models\Product;
use QueryPdo;
use Models\Entity;

class PriceDateRepository extends Repository
{
    protected string $entityModel = PriceDate::class;

    public function __construct()
    {
        parent::__construct();
    }

    public function savePriceDates(int $positionId, array $priceDates = []): void
    {
        if (empty($priceDates)) {
            return;
        }

        $arrayValues = $this->assembleInsertValues([
            PriceDate::PARAM_PRICE,
            PriceDate::PARAM_DATE,
        ]);
        $arrayValues['id'] = ':position_id';

        $query = (new QueryPdo())
            ->insert(
                PriceDate::TABLE_NAME,
                $arrayValues,
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
        $query = $this->getListQueryNew();
        $query
            ->where(PriceDate::TABLE_PREFIX . '.id IN ('.implode(",", $ids).')')
            ->order(PriceDate::TABLE_PREFIX . '.date');

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