<?php

namespace Repository;

use Core\Config;
use Exception\CustomPdoException;
use Models\BookUserData;
use PDOException;
use QueryPdo;

class BookUserDataRepository extends Repository
{
    protected string $entityModel = BookUserData::class;

    public function get(int $productId): BookUserData|null
    {
        $query = $this->getListQueryNew();
        $query
            ->where(BookUserData::PARAM_BOOK_ID, ':book_id')
            ->where(BookUserData::PARAM_USER_ID, ':user_id')
            ->bindParams([
                BookUserData::PARAM_BOOK_ID => $productId,
                BookUserData::PARAM_USER_ID => Config::getCurrentUserid(),
            ]);

        $data = $query->fetch();

        return $data ? new BookUserData($data) : null;
    }

    public function update(array $entityData): bool
    {
        $entityDataBuilder = $this->getEntityDataBuilder($entityData);

        $query = (new QueryPdo())
            ->update(
                BookUserData::TABLE_NAME,
                $entityDataBuilder->getQueryPreparedData()
            )
            ->where(BookUserData::PARAM_BOOK_ID, ':book_id')
            ->where(BookUserData::PARAM_USER_ID, ':user_id')
            ->bindParams([
                BookUserData::PARAM_BOOK_ID => $entityDataBuilder->getEntityData(BookUserData::PARAM_BOOK_ID),
                BookUserData::PARAM_USER_ID => Config::getCurrentUserid(),
            ]);

        try {
            $query->execute();

            return true;
        } catch(PDOException $e) {
            throw new CustomPdoException('BookUserDataRepository.update', $query, $e);
        }
    }

    public function create(array $entityData): bool
    {
        $entityDataBuilder = $this->getEntityDataBuilder($entityData);
        $entityDataBuilder->appendPreparedData([
            BookUserData::PARAM_USER_ID => Config::getCurrentUserid(),
        ]);

        $query = (new QueryPdo())
            ->insert(
                BookUserData::TABLE_NAME,
                $entityDataBuilder->getQueryPreparedData()
            );

        try {
            $query->execute();

            return true;
        } catch(PDOException $e) {
            throw new CustomPdoException('BookUserDataRepository.create', $query, $e);
        }
    }
}