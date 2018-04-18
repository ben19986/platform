<?php

namespace Shopware\Api\Entity\Dbal\FieldResolver;

use Shopware\Api\Entity\Dbal\EntityDefinitionQueryHelper;
use Shopware\Api\Entity\Dbal\QueryBuilder;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Defaults;

class ManyToOneAssociationFieldResolver implements FieldResolverInterface
{
    public function resolve(
        string $definition,
        string $root,
        Field $field,
        QueryBuilder $query,
        ApplicationContext $context,
        EntityDefinitionQueryHelper $queryHelper,
        bool $raw
    ): void {
        if (!$field instanceof ManyToOneAssociationField) {
            return;
        }

        /** @var EntityDefinition|string $reference */
        $reference = $field->getReferenceClass();
        $alias = $root . '.' . $field->getPropertyName();
        if ($query->hasState($alias)) {
            return;
        }
        $query->addState($alias);


        $this->join($definition, $root, $field, $query, $context, $queryHelper);

        if ($definition === $reference) {
            return;
        }

        if (!$reference::getParentPropertyName()) {
            return;
        }

        /** @var ManyToOneAssociationField $parent */
        $parent = $reference::getFields()->get($reference::getParentPropertyName());

        $queryHelper->resolveField($parent, $reference, $alias, $query, $context);
    }

    private function join(string $definition, string $root, ManyToOneAssociationField $field, QueryBuilder $query, ApplicationContext $context, EntityDefinitionQueryHelper $queryHelper): void
    {
        /** @var EntityDefinition|string $reference */
        /** @var EntityDefinition|string $definition */
        $reference = $field->getReferenceClass();
        $table = $reference::getEntityName();
        $alias = $root.'.'.$field->getPropertyName();

        $catalogJoinCondition = '';
        if ($definition::isCatalogAware() && $reference::isCatalogAware()) {
            $catalogJoinCondition = ' AND #root#.`catalog_id` = #alias#.`catalog_id`';
        }

        $versionAware = ($definition::isVersionAware() && $reference::isVersionAware());

        //specified version requested, use sub version call to solve live version or specified
        if ($versionAware && $context->getVersionId() !== Defaults::LIVE_VERSION) {
            $versionQuery = $this->createSubVersionQuery($field, $query, $context, $queryHelper);

            $query->leftJoin(
                EntityDefinitionQueryHelper::escape($root),
                '(' . $versionQuery->getSQL() . ')',
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(
                    ['#root#', '#source_column#', '#alias#', '#reference_column#'],
                    [
                        EntityDefinitionQueryHelper::escape($root),
                        EntityDefinitionQueryHelper::escape($field->getJoinField()),
                        EntityDefinitionQueryHelper::escape($alias),
                        EntityDefinitionQueryHelper::escape($field->getReferenceField()),
                    ],
                    '#root#.#source_column# = #alias#.#reference_column#'.$catalogJoinCondition
                )
            );

            return;
        }

        if ($versionAware) {
            $query->leftJoin(
                EntityDefinitionQueryHelper::escape($root),
                EntityDefinitionQueryHelper::escape($table),
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(
                    ['#root#', '#source_column#', '#alias#', '#reference_column#'],
                    [
                        EntityDefinitionQueryHelper::escape($root),
                        EntityDefinitionQueryHelper::escape($field->getJoinField()),
                        EntityDefinitionQueryHelper::escape($alias),
                        EntityDefinitionQueryHelper::escape($field->getReferenceField()),
                    ],
                    '#root#.#source_column# = #alias#.#reference_column# AND #root#.`version_id` = #alias#.`version_id`'.$catalogJoinCondition
                )
            );
            return;
        }

        $query->leftJoin(
            EntityDefinitionQueryHelper::escape($root),
            EntityDefinitionQueryHelper::escape($table),
            EntityDefinitionQueryHelper::escape($alias),
            str_replace(
                ['#root#', '#source_column#', '#alias#', '#reference_column#'],
                [
                    EntityDefinitionQueryHelper::escape($root),
                    EntityDefinitionQueryHelper::escape($field->getJoinField()),
                    EntityDefinitionQueryHelper::escape($alias),
                    EntityDefinitionQueryHelper::escape($field->getReferenceField()),
                ],
                '#root#.#source_column# = #alias#.#reference_column#'.$catalogJoinCondition
            )
        );
    }

    private function createSubVersionQuery(ManyToOneAssociationField $field, QueryBuilder $query, ApplicationContext $context, EntityDefinitionQueryHelper $queryHelper): QueryBuilder
    {
        $subRoot = $field->getReferenceClass()::getEntityName();

        $versionQuery = new QueryBuilder($query->getConnection());
        $versionQuery->select(EntityDefinitionQueryHelper::escape($subRoot).'.*');
        $versionQuery->from(
            EntityDefinitionQueryHelper::escape($subRoot),
            EntityDefinitionQueryHelper::escape($subRoot)
        );
        $queryHelper->joinVersion($versionQuery, $field->getReferenceClass(), $subRoot, $context);

        return $versionQuery;
    }
}