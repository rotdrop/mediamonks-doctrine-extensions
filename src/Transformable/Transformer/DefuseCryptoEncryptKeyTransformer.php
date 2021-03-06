<?php

namespace MediaMonks\Doctrine\Transformable\Transformer;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

class DefuseCryptoEncryptKeyTransformer implements TransformerInterface
{
    /**
     * @var mixed
     */
    private $key;

    /**
     * @var bool
     */
    private $keyVerified = false;

    /**
     * @var bool
     */
    private $binary = true;

    /**
     * @param mixed $key
     * @param array $options
     */
    public function __construct($key, array $options = [])
    {
        $this->key = $key;
        $this->setOptions($options);
    }

    /**
     * @param array $options
     */
    protected function setOptions(array $options)
    {
        if (array_key_exists('binary', $options)) {
            $this->binary = $options['binary'];
        }
    }

    /**
     * @return Key
     * @throws \InvalidArgumentException
     */
    public function getKey()
    {
        if (!$this->keyVerified) {
            if (is_string($this->key)) {
                $this->key = Key::loadFromAsciiSafeString($this->key);
            }
            if (!$this->key instanceof Key) {
                throw new \InvalidArgumentException('Either pass a string key or a Key object');
            }
            $this->keyVerified = true;
        }

        return $this->key;
    }

    /**
     * @return bool
     */
    public function getBinary()
    {
        return $this->binary;
    }

    /**
     * @param mixed $value
     * @return string
     */
    public function transform($value)
    {
        return Crypto::encrypt($value, $this->getKey(), $this->getBinary());
    }

    /**
     * @param mixed $value
     * @return string
     */
    public function reverseTransform($value)
    {
        return Crypto::decrypt($value, $this->getKey(), $this->getBinary());
    }

}
