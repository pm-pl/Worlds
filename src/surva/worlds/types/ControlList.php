<?php

/**
 * Worlds | control list logic (whitelisting & blacklisting)
 */

namespace surva\worlds\types;

class ControlList
{
    /**
     * @var array content of the list
     */
    private array $list;

    /**
     * @param  array  $list
     */
    public function __construct(array $list = [])
    {
        $this->list = $list;
    }

    /**
     * Add value to list
     *
     * @param  mixed  $value
     *
     * @return void
     */
    public function add(mixed $value): void
    {
        if ($this->isListed($value)) {
            return;
        }

        $this->list[] = $value;
    }

    /**
     * Remove value from list
     *
     * @param  mixed  $value
     *
     * @return void
     */
    public function remove(mixed $value): void
    {
        $this->list = array_filter($this->list, function ($val) use ($value) {
            return $val !== $value;
        });
    }

    /**
     * Make list empty
     *
     * @return void
     */
    public function reset(): void
    {
        $this->list = [];
    }

    /**
     * Check if list contains value
     *
     * @param  mixed  $value
     *
     * @return bool
     */
    public function isListed(mixed $value): bool
    {
        return in_array($value, $this->list);
    }

    /**
     * Check if list contains any of the values
     *
     * @param  array  $values
     *
     * @return bool
     */
    public function anyListed(array $values): bool
    {
        foreach ($values as $value) {
            if ($this->isListed($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function getList(): array
    {
        return $this->list;
    }
}
