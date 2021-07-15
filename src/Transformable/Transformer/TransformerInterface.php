<?php

namespace MediaMonks\Doctrine\Transformable\Transformer;

interface TransformerInterface
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public function transform($value);

    /**
     * @param mixed $value
     * @return mixed
     */
    public function reverseTransform($value);

    /**
     * @return bool
     */
    public function isCachable();
}
