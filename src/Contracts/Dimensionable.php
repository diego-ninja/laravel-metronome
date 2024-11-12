<?php

namespace Ninja\Metronome\Contracts;

use Ninja\Metronome\Dto\DimensionCollection;

interface Dimensionable
{
    public function dimensions(): DimensionCollection;
}
