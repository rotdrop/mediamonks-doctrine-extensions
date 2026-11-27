Attributes
==========

.. code-block:: php

    <?php

    namespace Acme\Entity;

    use Doctrine\ORM\Mapping as ORM;
    use MediaMonks\Doctrine\Mapping as MediaMonks;

    #[ORM\Entity()]
    class Foo
    {
        #[ORM\Column(type: 'text', nullable: true)]
        #[MediaMonks\Transformable(name: '<transformer_name>')]
        protected $bar;

Transformation context
----------------------

Set ``context: 'barContext'`` on ``Transformable`` to pass an entity property
by reference to both transformer operations. The context property can be
private and need not be mapped by Doctrine.

Use ``context: 'contexts[]'`` to share an array property between fields. Each
field gets its own entry, keyed by field name; changes made by the transformer
are written back without replacing the other entries. Initialize the property
to ``null`` or an array, for example ``private ?array $contexts = null;``.

Caching reuses the stored representation when plaintext is unchanged; context
changes alone do not invalidate it. A context-sensitive transformer should
return ``false`` from ``isCachable()`` and the entity must be scheduled for an
update if no mapped field changed.
