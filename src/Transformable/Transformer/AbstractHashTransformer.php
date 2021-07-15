<?php

namespace MediaMonks\Doctrine\Transformable\Transformer;

abstract class AbstractHashTransformer extends AbstractTransformer
{
    /**
     * @var string
     */
    protected $algorithm = 'sha256';

    /**
     * @var bool
     */
    protected $binary = true;

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    /**
     * @param array $options
     */
    protected function setOptions(array $options)
    {
        if(array_key_exists('algorithm', $options)) {
            $this->algorithm = $options['algorithm'];
        }
        if(array_key_exists('binary', $options)) {
            $this->binary = $options['binary'];
        }
    }

    /**
     * @return string
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * @return bool
     */
    public function getBinary(): bool
    {
        return $this->binary;
    }

    /**
     * @param string $value
     * @return string | bool
     */
    public abstract function transform($value);

    /**
     * @param mixed $value
     * @return string
     */
    public function reverseTransform($value): string
    {
        return $value;
    }
}
