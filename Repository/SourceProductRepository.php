<?php

namespace Repository;

use Core\Config;
use Exception\CustomPdoException;
use Models\Entity;
use Models\Product;
use Models\SourceProduct;
use Models\Stock;
use QueryPdo;
use PDOException;

class SourceProductRepository extends Repository
{
    protected string $entityModel = SourceProduct::class;

    public function linkToProduct(int $entityId, int $sourceProductId): void
    {
        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                [
                    Product::PARAM_SOURCE_PRODUCT_ID => $sourceProductId
                ]
            )
            ->where(Product::PARAM_ID, ':id')
            ->bindParam(Product::PARAM_ID, $entityId);

        try {
            $query->execute();
        } catch(PDOException $e) {
            throw new CustomPdoException('SourceProductRepository.linkToProduct', $query, $e);
        }
    }

    public function unlinkFromProduct(int $entityId): void
    {
        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                [
                    Product::PARAM_SOURCE_PRODUCT_ID => null
                ]
            )
            ->where(Product::PARAM_ID, ':id')
            ->bindParam(Product::PARAM_ID, $entityId);

        try {
            $query->execute();
        } catch(PDOException $e) {
            throw new CustomPdoException('SourceProductRepository.unlinkFromProduct', $query, $e);
        }
    }

    public function get(int $id): SourceProduct|null
    {
        $query = $this->getListQueryNew();
        $query
            ->where('id', ':id')
            ->where(SourceProduct::PARAM_USER_ID, ':user_id')
            ->bindParams([
                Entity::PARAM_ID => $id,
                SourceProduct::PARAM_USER_ID => Config::getCurrentUserid(),
            ]);

        $data = $query->fetch();

        if (!$data) {
            return null;
        }

        return new SourceProduct($data);
    }

    /**
     * @return SourceProduct[]
     */
    public function getSourceProductsByTitle(string $title): array
    {
        $query = $this->getListQueryNew()
            ->where('LOWER('.SourceProduct::TABLE_PREFIX.'.title) LIKE :title')
//            ->where(SourceProduct::PARAM_USER_ID, ':user_id')
            ->limit(7);

        $fetchData = [
            'title' => '%'.strtolower(trim($title)).'%',
//            SourceProduct::PARAM_USER_ID => Config::getCurrentUserid()
        ];

        $explodeTitle = explode(' ', $title);
        $splitTitle = reset($explodeTitle);

        if (!empty($splitTitle)) {
            $query->orWhere('LOWER('.SourceProduct::TABLE_PREFIX.'.title) LIKE :split_title');
            $fetchData['split_title'] = '%'.strtolower(trim($splitTitle)).'%';
        }

        $query->bindParams($fetchData);

        $rows = $query->fetchAll();

        return array_map(function ($row) {
            return new SourceProduct($row);
        }, $rows);
    }

    /**
     * @param array $entityData Входящие данные модели.
     *
     * @return int ID книги.
     */
    public function save(array $entityData): int
    {
        if (isset($entityData[Entity::PARAM_ID])) {
            return $this->update($entityData);
        }

        return $this->create($entityData);
    }

    protected function update(array $entityData): int
    {
        $entityDataBuilder = $this->getEntityDataBuilder($entityData);

        $query = (new QueryPdo())
            ->update(
                SourceProduct::TABLE_NAME,
                $entityDataBuilder->getQueryPreparedData()
            )
            ->where(SourceProduct::PARAM_ID, ':id')
            ->where(SourceProduct::PARAM_USER_ID, ':user_id')
            ->bindParams([
                SourceProduct::PARAM_ID => $entityDataBuilder->getEntityData(SourceProduct::PARAM_ID),
                SourceProduct::PARAM_USER_ID => Config::getCurrentUserid()
            ]);

        try {
            $query->execute();

            return $entityDataBuilder->getEntityData(SourceProduct::PARAM_ID);
        } catch(PDOException $e) {
            throw new CustomPdoException('SourceProductRepository.update', $query, $e);
        }
    }

    protected function create(array $entityData): int
    {
        $entityDataBuilder = $this->getEntityDataBuilder($entityData);
        $entityDataBuilder->appendPreparedData([
            SourceProduct::PARAM_USER_ID => Config::getCurrentUserid(),
        ]);

        $query = (new QueryPdo())
            ->insert(SourceProduct::TABLE_NAME, $entityDataBuilder->getQueryPreparedData());

        try {
            $query->execute();

            return $query->getLastInsertId();
        } catch(PDOException $e) {
            throw new CustomPdoException('SourceProductRepository.create', $query, $e);
        }
    }
}