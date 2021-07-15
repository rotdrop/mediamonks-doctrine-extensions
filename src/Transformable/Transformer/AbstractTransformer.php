<?php

namespace MediaMonks\Doctrine\Transformable\Transformer;

abstract class AbstractTransformer implements TransformerInterface
{
  public function isCachable()
  {
    return true;
  }
}
