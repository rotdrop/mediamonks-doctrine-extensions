<?php

namespace Transformable\Fixture;

use Doctrine\ORM\Mapping as ORM;
use MediaMonks\Doctrine\Mapping\Annotation as MediaMonks;


class YamlTest
{
    private $id;

    private $value;

    private $updated = false;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     * @return Test
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * @param mixed $updated
     * @return Test
     */
    public function setUpdated($updated)
    {
        $this->updated = $updated;
        return $this;
    }
}