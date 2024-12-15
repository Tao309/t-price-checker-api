<?php

namespace Repository;

use Core\AccessRight\AccessRight;
use Core\Config;
use Exception\CustomPdoException;
use Exception\ResponseException;
use Models\Book;
use Models\Entity;
use Models\Product;
use Models\ProductUserData;
use PDOException;
use QueryPdo;

class ProductUserDataRepository extends Repository
{
    protected string $entityModel = ProductUserData::class;

    public function get(int $productId): ProductUserData|null
    {
        $query = $this->getListQueryNew();
        $query
            ->where(ProductUserData::PARAM_PRODUCT_ID, ':product_id')
            ->where(ProductUserData::PARAM_USER_ID, ':user_id')
            ->bindParams([
                ProductUserData::PARAM_PRODUCT_ID => $productId,
                ProductUserData::PARAM_USER_ID => Config::getCurrentUserid(),
            ]);

        $data = $query->fetch();

        return $data ? new ProductUserData($data) : null;
    }

    public function update(array $entityData): bool
    {
        $entityDataBuilder = $this->getEntityDataBuilder($entityData);

        $query = (new QueryPdo())
            ->update(
                ProductUserData::TABLE_NAME,
                $entityDataBuilder->getQueryPreparedData()
            )
            ->where(ProductUserData::PARAM_PRODUCT_ID, ':product_id')
            ->where(ProductUserData::PARAM_USER_ID, ':user_id')
            ->bindParams([
                ProductUserData::PARAM_PRODUCT_ID => $entityDataBuilder->getEntityData(ProductUserData::PARAM_PRODUCT_ID),
                ProductUserData::PARAM_USER_ID => Config::getCurrentUserid(),
            ]);

        try {
            $query->execute();

            return true;
        } catch(PDOException $e) {
            throw new CustomPdoException('ProductUserDataRepository.update', $query, $e);
        }
    }

    public function create(array $entityData): bool
    {
        $entityDataBuilder = $this->getEntityDataBuilder($entityData);
        $entityDataBuilder->appendPreparedData([
            ProductUserData::PARAM_USER_ID => Config::getCurrentUserid(),
        ]);

        $query = (new QueryPdo())
            ->insert(
                ProductUserData::TABLE_NAME,
                $entityDataBuilder->getQueryPreparedData()
            );

        try {
            $query->execute();

            return true;
        } catch(PDOException $e) {
            throw new CustomPdoException('ProductUserDataRepository.create', $query, $e);
        }
    }
}