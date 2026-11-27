Annotations
===========

Legacy reference only: Doctrine ORM 3 no longer supports annotation mapping.
Use the attribute example in ``attributes.rst`` for the supported ORM integration.

.. code-block:: php

    <?php

    namespace Acme\Entity;

    use Doctrine\ORM\Mapping as ORM;
    use MediaMonks\Doctrine\Mapping as MediaMonks;

    /**
     * @ORM\Entity
     */
    class Foo
    {
        /**
         * @ORM\Column(type="blob")
         * @MediaMonks\Transformable(name="<transformer_name>")
         */
        protected $bar;
