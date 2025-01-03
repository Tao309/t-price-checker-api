<?php

namespace Repository;

use Models\SourceProductUserData;

/**
 * @method SourceProductUserData find(array $primaryIds) // [$userId, $sourceProductId]
 */
class SourceProductUserDataRepository extends Repository
{
    protected string $entityModel = SourceProductUserData::class;
}