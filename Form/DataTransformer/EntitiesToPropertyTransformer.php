<?php

namespace Tetranz\Select2EntityBundle\Form\DataTransformer;

use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Data transformer for multiple mode (i.e., multiple = true)
 */
class EntitiesToPropertyTransformer implements DataTransformerInterface
{
    protected ObjectManager $em;
    protected string $className;
    protected string $textProperty;
    protected string $primaryKey;
    protected string $newTagPrefix;
    protected string $newTagText;
    protected PropertyAccessor $accessor;


    public function __construct(ObjectManager $em, string $class, ?string $textProperty = null, string $primaryKey = 'id', string $newTagPrefix = '__', string $newTagText = ' (NEW)')
    {
        $this->em = $em;
        $this->className = $class;
        $this->textProperty = $textProperty;
        $this->primaryKey = $primaryKey;
        $this->newTagPrefix = $newTagPrefix;
        $this->newTagText = $newTagText;
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Transform initial entities to array
     */
    public function transform(mixed $value): mixed
    {
        if (empty($entities)) {
            return array();
        }

        $data = array();

        foreach ($entities as $entity) {
            $text = is_null($this->textProperty)
                ? (string) $entity
                : $this->accessor->getValue($entity, $this->textProperty);

            if ($this->em->contains($entity)) {
                $value = (string) $this->accessor->getValue($entity, $this->primaryKey);
            } else {
                $value = $this->newTagPrefix . $text;
                $text = $text.$this->newTagText;
            }

            $data[$value] = $text;
        }

        return $data;
    }

    /**
     * Transform array to a collection of entities
     */
    public function reverseTransform(mixed $value): mixed
    {
        if (!is_array($value) || empty($value)) {
            return array();
        }

        // add new tag entries
        $newObjects = array();
        $tagPrefixLength = strlen($this->newTagPrefix);
        foreach ($value as $key => $item) {
            $cleanValue = substr($item, $tagPrefixLength);
            $valuePrefix = substr($item, 0, $tagPrefixLength);
            if ($valuePrefix == $this->newTagPrefix) {
                $object = new $this->className;
                $this->accessor->setValue($object, $this->textProperty, $cleanValue);
                $newObjects[] = $object;
                unset($value[$key]);
            }
        }

        // get multiple entities with one query
        $entities = $this->em->createQueryBuilder()
            ->select('entity')
            ->from($this->className, 'entity')
            ->where('entity.'.$this->primaryKey.' IN (:ids)')
            ->setParameter('ids', $value)
            ->getQuery()
            ->getResult();

          // this will happen if the form submits invalid data
        if (count($entities) != count($value)) {
            throw new TransformationFailedException('One or more id values are invalid');
        }

        return array_merge($entities, $newObjects);
    }
}
