<?php

namespace Ninja\Metronome\Dto;

use JsonSerializable;

final readonly class Dimension implements JsonSerializable
{
    public function __construct(
        public string $name,
        public string $value,
    ) {}

    public function array(): array
    {
        return $this->asLabel();
    }

    public function asLabel(): array
    {
        return [$this->name => $this->value];
    }

    public static function from(string|array|Dimension $data): self
    {
        if ($data instanceof self) {
            return new self($data->name, $data->value);
        }

        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        return new self(
            name: array_key_first($data),
            value: array_values($data)[0],
        );
    }

    public function json(): string
    {
        return json_encode($this->array());
    }

    public function jsonSerialize(): array
    {
        return $this->array();
    }
}
