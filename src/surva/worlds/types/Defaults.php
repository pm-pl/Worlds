<?php

/**
 * Worlds | defaults processing file
 */

namespace surva\worlds\types;

class Defaults extends World
{
    /**
     * Get value from config
     *
     * @param  string  $name
     *
     * @return mixed|null
     */
    public function getValue(string $name): mixed
    {
        if (!$this->getConfig()->exists($name)) {
            return null;
        }

        return $this->getConfig()->get($name);
    }

    /**
     * Load value from config
     *
     * @param  string  $name
     *
     * @return mixed|null
     */
    public function loadValue(string $name): mixed
    {
        if (!$this->getConfig()->exists($name)) {
            $this->flags[$name] = null;
            return null;
        }

        $val = $this->getConfig()->get($name);

        $this->flags[$name] = $val;
        return $val;
    }
}
