<?php

namespace Repository;

use Core\Config;
use Exception\CustomPdoException;
use Models\Stock;
use QueryPdo;
use Models\Entity;

class StockRepository extends Repository
{
    protected string $entityModel = Stock::class;

    public function __construct()
    {
        parent::__construct();
    }

    public function saveStocks(int $positionId, array $stocks = []): void
    {
        if (empty($stocks)) {
            return;
        }

        $arrayValues = $this->assembleInsertValues([
            Stock::PARAM_ID,
            Stock::PARAM_QTY,
            Stock::PARAM_USER_ID,
            Stock::PARAM_DATE,
            Stock::PARAM_LOG,
        ]);

        $query = (new QueryPdo())
            ->insert(
                Stock::TABLE_NAME,
                $arrayValues,
                'qty = VALUES(qty), log = VALUES(log)'
            );

        try {
            foreach($stocks as $stock) {
                $query->bindParams([
                    Stock::PARAM_ID => $positionId,
                    Stock::PARAM_QTY => $stock[Stock::PARAM_QTY],
                    Stock::PARAM_DATE => $stock[Stock::PARAM_DATE],
                    Stock::PARAM_LOG => isset($stock[Stock::PARAM_LOG]) ? json_encode($stock[Stock::PARAM_LOG]) : null,
                    Stock::PARAM_USER_ID => Config::getCurrentUserid(),
                ]);

                $query->execute();
            }
        } catch(\PDOException $e) {
            throw new CustomPdoException('StockRepository.saveStocks', $query, $e);
        }
    }

    /**
     * Получаем данные стоков для списка продуктов, уже сгруппированные по id.
     *
     * @param array $ids Массив ID товаров.
     *
     * @return array Массив стоков.
     */
    public function getStocksForProducts(array $ids = []): array
    {
        $query = $this->getListQueryNew();

        $query
            ->where('id', $ids)
            ->where(Stock::PARAM_USER_ID, ':user_id')
            ->order('date');

        $result = [];

        foreach ($query->fetchAll([
            Stock::PARAM_USER_ID => Config::getCurrentUserid(),
        ]) as $row) {
            if (!isset($result[$row[Entity::PARAM_ID]])) {
                $result[$row[Entity::PARAM_ID]] = [];
            }

            $result[$row[Entity::PARAM_ID]][] = $row;
        }

        return $result;
    }

    public function getStock(array $stockData)
    {
        $query = $this->getListQueryNew()
            ->where('id', ':id')
            ->where('qty', ':qty')
            ->where(Stock::PARAM_USER_ID, ':user_id')
            ->where('date', ':date');

        return $query->fetch([
            Stock::PARAM_ID => $stockData[Stock::PARAM_ID],
            Stock::PARAM_QTY => $stockData[Stock::PARAM_QTY],
            Stock::PARAM_DATE => $stockData[Stock::PARAM_DATE],
            Stock::PARAM_USER_ID => Config::getCurrentUserid(),
        ]);
    }

    public function deleteStock(array $stockData): int
    {
        if (empty($stockData[Stock::PARAM_ID])
            || empty($stockData[Stock::PARAM_QTY])
            || empty($stockData[Stock::PARAM_DATE])
        ) {
            throw new \Exception('Не все поля заполнены для удаления');
        }

        $query = (new QueryPdo())
            ->delete(
                Stock::TABLE_NAME
            )
            ->where(Stock::PARAM_ID, ':' . Stock::PARAM_ID)
            ->where(Stock::PARAM_QTY, ':' . Stock::PARAM_QTY)
            ->where(Stock::PARAM_DATE, ':' . Stock::PARAM_DATE)
            ->where(Stock::PARAM_USER_ID, ':user_id')
            ->bindParams([
                Stock::PARAM_ID => $stockData[Stock::PARAM_ID],
                Stock::PARAM_QTY => $stockData[Stock::PARAM_QTY],
                Stock::PARAM_DATE => $stockData[Stock::PARAM_DATE],
                Stock::PARAM_USER_ID => Config::getCurrentUserid(),
            ]);

        try {
            $query->execute();

            if (!$query->getRowCount()) {
                throw new \Exception('Stock is not removed. Not row affected.');
            }

            return $query->getRowCount();
        } catch(\PDOException $e) {
            throw new CustomPdoException('StockRepository.deleteStock', $query, $e);
        }
    }
}