<?php

namespace Repository;

use Core\Config;
use Exception\CustomPdoException;
use Models\ProductUserData;
use QueryPdo;

/**
 * @method ProductUserData find(int $primaryId)
 */
class ProductUserDataRepository extends Repository
{
    protected string $entityModel = ProductUserData::class;

    public function changeIsArchive(int $productId, bool $isArchive): void
    {
        $query = (new QueryPdo())
            ->update(
                ProductUserData::TABLE_NAME,
                [ProductUserData::PARAM_IS_ARCHIVE => $isArchive]
            )
            ->where(ProductUserData::PARAM_PRODUCT_ID, ':product_id')
            ->where(ProductUserData::PARAM_USER_ID, ':user_id')
            ->bindParams([
                ProductUserData::PARAM_PRODUCT_ID => $productId,
                ProductUserData::PARAM_USER_ID => Config::getCurrentUserid(),
            ]);

        try {
            $query->execute();
        } catch(\PDOException $e) {
            throw new CustomPdoException('ProductUserDataRepository.changeProductIsArchive', $query, $e);
        }
    }
}