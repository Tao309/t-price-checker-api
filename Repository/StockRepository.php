<?php

namespace Repository;

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
            Stock::PARAM_DATE,
            Stock::PARAM_LOG,
        ]);

        $query = (new QueryPdo())
            ->insert(
                Stock::TABLE_NAME,
                $arrayValues,
                'qty = VALUES(qty), log = VALUES(log)'
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        try {
            foreach($stocks as $stock) {
                $stmt->execute([
                    Stock::PARAM_ID => $positionId,
                    Stock::PARAM_QTY => $stock[Stock::PARAM_QTY],
                    Stock::PARAM_DATE => $stock[Stock::PARAM_DATE],
                    Stock::PARAM_LOG => isset($stock[Stock::PARAM_LOG]) ? json_encode($stock[Stock::PARAM_LOG]) : null
                ]);
            }
        } catch(\PDOException $e) {
            processPdoException('saveStocks', ['position_id' => $positionId], $stocks, $stmt, $e);
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
            ->where(Stock::TABLE_PREFIX . '.id IN ('.implode(",", $ids).')')
            ->order(Stock::TABLE_PREFIX . '.date');

        $result = [];

        foreach ($query->fetchAll() as $row) {
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
            ->where(Stock::TABLE_PREFIX . '.id = :id')
            ->where(Stock::TABLE_PREFIX . '.qty = :qty')
            ->where(Stock::TABLE_PREFIX . '.date = :date');

        return $query->fetch([
            Stock::PARAM_ID => $stockData[Stock::PARAM_ID],
            Stock::PARAM_QTY => $stockData[Stock::PARAM_QTY],
            Stock::PARAM_DATE => $stockData[Stock::PARAM_DATE]
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
        $arrayValues = $this->assembleInsertValues([
            Stock::PARAM_ID,
            Stock::PARAM_QTY,
            Stock::PARAM_DATE,
        ]);

        $query = (new QueryPdo())
            ->delete(
                Stock::TABLE_NAME,
                $arrayValues
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        $stmt->execute([
            Stock::PARAM_ID => $stockData[Stock::PARAM_ID],
            Stock::PARAM_QTY => $stockData[Stock::PARAM_QTY],
            Stock::PARAM_DATE => $stockData[Stock::PARAM_DATE]
        ]);

        if (!$stmt->rowCount()) {
            throw new \Exception('Stock is not removed. Not row affected.');
        }

        return $stmt->rowCount();
    }
}