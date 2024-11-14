<?php

namespace Ninja\Metronome\Processors\Contracts;

use Ninja\Metronome\Dto\Metadata;

interface Processable
{
    public function identifier(): string;

    public function metadata(): Metadata;
}
