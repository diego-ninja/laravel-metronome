<?php

namespace Ninja\Metronome\Processors\Contracts;

interface Processor
{
    public function process(Processable $item): void;
}
