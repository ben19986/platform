<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Aggregate\CmsSlot;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CmsSlotCollection extends EntityCollection
{
    /**
     * @var CmsSlotEntity[]|null indexed by slot name
     */
    private $slotCache;

    /**
     * @param string        $key
     * @param CmsSlotEntity $entity
     */
    public function set($key, $entity): void
    {
        parent::set($key, $entity);

        $this->slotCache[$entity->getSlot()] = $entity;
    }

    /**
     * @param CmsSlotEntity $entity
     */
    public function add($entity): void
    {
        parent::add($entity);

        $this->slotCache[$entity->getSlot()] = $entity;
    }

    public function getSlot(string $slot): ?CmsSlotEntity
    {
        $this->createSlotHashMap();

        return $this->slotCache[$slot] ?? null;
    }

    protected function getExpectedClass(): string
    {
        return CmsSlotEntity::class;
    }

    private function createSlotHashMap(): void
    {
        if ($this->slotCache !== null) {
            return;
        }

        /** @var CmsSlotEntity $element */
        foreach ($this->elements as $element) {
            $this->slotCache[$element->getSlot()] = $element;
        }
    }
}