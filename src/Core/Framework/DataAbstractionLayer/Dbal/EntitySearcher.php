<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Struct\Uuid;

/**
 * Used for all search operations in the system.
 * The dbal entity searcher only joins and select fields which defined in sorting, filter or query classes.
 * Fields which are not necessary to determines which ids are affected are not fetched.
 */
class EntitySearcher implements EntitySearcherInterface
{
    use CriteriaQueryHelper;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var SqlQueryParser
     */
    private $queryParser;

    /**
     * @var EntityDefinitionQueryHelper
     */
    private $queryHelper;

    public function __construct(
        Connection $connection,
        SqlQueryParser $queryParser,
        EntityDefinitionQueryHelper $queryHelper
    ) {
        $this->connection = $connection;
        $this->queryParser = $queryParser;
        $this->queryHelper = $queryHelper;
    }

    public function search(string $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        /** @var EntityDefinition $definition */
        $table = $definition::getEntityName();

        $query = new QueryBuilder($this->connection);
        $query->select([
            EntityDefinitionQueryHelper::escape($table) . '.' . EntityDefinitionQueryHelper::escape('id') . ' as array_key',
            EntityDefinitionQueryHelper::escape($table) . '.' . EntityDefinitionQueryHelper::escape('id') . ' as primary_key',
        ]);

        $query = $this->buildQueryByCriteria($query, $this->queryHelper, $this->queryParser, $definition, $criteria, $context);
        $this->addGroupBy($this->queryHelper, $definition, $criteria, $query, $context);

        //add pagination
        if ($criteria->getOffset() >= 0) {
            $query->setFirstResult($criteria->getOffset());
        }
        if ($criteria->getLimit() >= 0) {
            $query->setMaxResults($criteria->getLimit());
        }

        $this->addTotalCountMode($criteria, $query);

        //execute and fetch ids
        $data = $query->execute()->fetchAll();
        $data = FetchModeHelper::groupUnique($data);

        $total = $this->getTotalCount($criteria, $data);

        if ($criteria->getTotalCountMode() === Criteria::TOTAL_COUNT_MODE_NEXT_PAGES) {
            $data = \array_slice($data, 0, $criteria->getLimit());
        }

        $converted = [];
        foreach ($data as $key => $values) {
            $key = Uuid::fromBytesToHex($key);
            $values['primary_key'] = $key;
            $converted[$key] = $values;
        }

        return new IdSearchResult($total, $converted, $criteria, $context);
    }

    private function addTotalCountMode(Criteria $criteria, QueryBuilder $query): void
    {
        //requires total count for query? add save SQL_CALC_FOUND_ROWS
        if ($criteria->getTotalCountMode() === Criteria::TOTAL_COUNT_MODE_NONE) {
            return;
        }
        if ($criteria->getTotalCountMode() === Criteria::TOTAL_COUNT_MODE_NEXT_PAGES) {
            $query->setMaxResults($criteria->getLimit() * 6 + 1);

            return;
        }

        $selects = $query->getQueryPart('select');
        $selects[0] = 'SQL_CALC_FOUND_ROWS ' . $selects[0];
        $query->select($selects);
    }

    private function getTotalCount(Criteria $criteria, array $data): int
    {
        if ($criteria->getTotalCountMode() === Criteria::TOTAL_COUNT_MODE_EXACT) {
            return (int) $this->connection->fetchColumn('SELECT FOUND_ROWS()');
        }

        return \count($data);
    }
}