<?php

namespace Code\Structure;

class Column
{
    private string $field;

    private string $type;

    private string $null;

    private string $key;

    private ?string $default;

    private string $extra;

    public function __construct(object $object)
    {
        $this->setField($object->Field);
        $this->setType($object->Type);
        $this->setNull($object->Null);
        $this->setKey($object->Key);
        $this->setDefault($object->Default);
        $this->setExtra($object->Extra);
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): void
    {
        $this->field = $field;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getNull(): string
    {
        return $this->null;
    }

    public function setNull(string $null): void
    {
        $this->null = $null;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }

    public function setDefault(?string $default): void
    {
        $this->default = $default;
    }

    public function getExtra(): string
    {
        return $this->extra;
    }

    public function setExtra(string $extra): void
    {
        $this->extra = $extra;
    }
}
