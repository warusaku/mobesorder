<?php

namespace CoreInterfaces\Core\Request;

use CoreInterfaces\Core\Request\ParamInterface;

interface NonEmptyParamInterface extends ParamInterface
{
    public function requiredNonEmpty();
}
