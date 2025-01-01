<?php

namespace Repository;

use Core\Config;
use Exception\CustomPdoException;
use Models\PriceDate;
use QueryPdo;
use Models\Entity;

/**
 * @method PriceDate find()
 * @method PriceDate[] findByParams(array $params, array $filters = [])
 */
class PriceDateRepository extends Repository
{
    protected string $entityModel = PriceDate::class;

    public function savePriceDates(int $positionId, array $priceDates = []): void
    {
        if (empty($priceDates)) {
            return;
        }

        $arrayValues = $this->assembleInsertValues([
            PriceDate::PARAM_ID,
            PriceDate::PARAM_PRICE,
            PriceDate::PARAM_USER_ID,
            PriceDate::PARAM_DATE,
        ]);

        $query = (new QueryPdo())
            ->insert(
                PriceDate::TABLE_NAME,
                $arrayValues,
                'price = VALUES(price)'
            );

        QueryPdo::beginTransaction();
        try {
            foreach($priceDates as $priceDate) {
                $query->bindParams([
                    PriceDate::PARAM_ID => $positionId,
                    PriceDate::PARAM_PRICE => $priceDate[PriceDate::PARAM_PRICE],
                    PriceDate::PARAM_DATE => $priceDate[PriceDate::PARAM_DATE],
                    PriceDate::PARAM_USER_ID => Config::getCurrentUserid(),
                ]);

                $query->execute();
            }
            QueryPdo::commit();
        } catch(\PDOException $e) {
            QueryPdo::rollBack();
            throw new CustomPdoException('PriceDateRepository.savePriceDates', $query, $e);
        }
    }

    public function getByProductIds(array $ids)
    {
        return $this->findByParams(
            [
                PriceDate::PARAM_ID => $ids,
                PriceDate::PARAM_USER_ID => Config::getCurrentUserid(),
            ],
            [
                self::PARAM_ORDER => PriceDate::PARAM_DATE
            ]
        );
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
        $query = $this->getQuery();
        $query
            ->where('id', $ids)
            ->where(PriceDate::PARAM_USER_ID, ':user_id')
            ->order('date')
            ->bindParams([
                PriceDate::PARAM_USER_ID => Config::getCurrentUserid()
            ]);

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