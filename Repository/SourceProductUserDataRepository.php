<?php

namespace Repository;

use Core\Config;
use Exception\CustomPdoException;
use Models\BookUserData;
use Models\SourceProductUserData;
use PDOException;
use QueryPdo;

class SourceProductUserDataRepository extends Repository
{
    protected string $entityModel = SourceProductUserData::class;

    public function get(int $sourceProductId): SourceProductUserData|null
    {
        $query = $this->getListQueryNew();
        $query
            ->where(SourceProductUserData::PARAM_SOURCE_PRODUCT, ':source_product_id')
            ->where(SourceProductUserData::PARAM_USER_ID, ':user_id')
            ->bindParams([
                SourceProductUserData::PARAM_SOURCE_PRODUCT => $sourceProductId,
                SourceProductUserData::PARAM_USER_ID => Config::getCurrentUserid(),
            ]);

        $data = $query->fetch();

        return $data ? new SourceProductUserData($data) : null;
    }

    public function update(array $entityData): bool
    {
        $entityDataBuilder = $this->getEntityDataBuilder($entityData);

        $query = (new QueryPdo())
            ->update(
                SourceProductUserData::TABLE_NAME,
                $entityDataBuilder->getQueryPreparedData()
            )
            ->where(SourceProductUserData::PARAM_SOURCE_PRODUCT, ':source_product_id')
            ->where(SourceProductUserData::PARAM_USER_ID, ':user_id')
            ->bindParams([
                SourceProductUserData::PARAM_SOURCE_PRODUCT => $entityDataBuilder->getEntityData(SourceProductUserData::PARAM_SOURCE_PRODUCT),
                SourceProductUserData::PARAM_USER_ID => Config::getCurrentUserid(),
            ]);

        try {
            $query->execute();

            return true;
        } catch(PDOException $e) {
            throw new CustomPdoException('SourceProductUserDataRepository.update', $query, $e);
        }
    }

    public function create(array $entityData): bool
    {
        $entityDataBuilder = $this->getEntityDataBuilder($entityData);
        $entityDataBuilder->appendPreparedData([
            SourceProductUserData::PARAM_USER_ID => Config::getCurrentUserid(),
        ]);

        $query = (new QueryPdo())
            ->insert(
                SourceProductUserData::TABLE_NAME,
                $entityDataBuilder->getQueryPreparedData()
            );

        try {
            $query->execute();

            return true;
        } catch(PDOException $e) {
            throw new CustomPdoException('SourceProductUserDataRepository.create', $query, $e);
        }
    }
}