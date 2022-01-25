<?php

namespace MediaMonks\Doctrine\Transformable\Transformer;

interface TransformerInterface
{
    /**
     * Called to encode the value before persisting.
     *
     * @param null|string $value The untransformed value.
     *
     * @param mixed $context Optional entity and field dependent state. If
     * configured this is a reference to the field named by the 'context'
     * annotation. May be changed by the transformer implementation.
     *
     * @return mixed
     */
    public function transform(?string $value, mixed &$context = null): mixed;

    /**
     * Called to retrieve the original value, e.g. after entity load.
     *
     * @param null|string $value The value as stored in the data-base.
     *
     * @param mixed $context Optional entity and field dependent state. If
     * configured this is a reference to the field named by the 'context'
     * annotatation. May be changed by the transformer implementation.
     *
     * @return mixed
     */
    public function reverseTransform(?string $value, mixed &$context = null): mixed;

    /**
     * Decide whether the result of transform() and reverseTransform() may be
     * cached or could changed by "side-effects".
     *
     * @return bool
     */
    public function isCachable(): bool;
}
