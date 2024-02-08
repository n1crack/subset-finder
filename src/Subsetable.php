<?php

namespace Ozdemir\SubsetFinder;

interface Subsetable
{
    public function getId(): mixed;

    public function getQuantity(): mixed;

    public function setQuantity($quantity): void;
}
