<?php

namespace MediaMonks\Doctrine\Transformable\Transformer;

use Zend\Crypt\Hmac;

class ZendCryptHmacTransformer extends AbstractHmacTransformer
{
    /**
     * @param string $value
     * @return string
     */
    public function transform($value)
    {
        return Hmac::compute($this->getKey(), $this->algorithm, $value, $this->getBinary());
    }
}
