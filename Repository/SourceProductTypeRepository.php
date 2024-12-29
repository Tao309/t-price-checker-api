<?php

namespace Repository;

use Models\SourceProductType;

/**
 * @method SourceProductType find()
 * @method SourceProductType[] findByParams(array $params, array $filters = [])
 */
class SourceProductTypeRepository extends Repository
{
    protected string $entityModel = SourceProductType::class;

}