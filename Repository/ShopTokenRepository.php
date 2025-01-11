<?php

namespace Repository;

use Models\ShopToken;

/**
 * @method ShopToken find($userId, $shopId)
 */
class ShopTokenRepository extends Repository
{
    protected string $entityModel = ShopToken::class;
}