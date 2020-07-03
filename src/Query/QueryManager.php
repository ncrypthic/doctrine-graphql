<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQL\Query;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\QueryBuilder;
use GraphQL\Type\Definition\ResolveInfo;
use LLA\DoctrineGraphQL\Type\Definition\InputTypeDefinition;
use LLA\DoctrineGraphQL\Type\RegistryInterface;

class QueryManager implements QueryManagerInterface
{
    /**
     * @var ClassMetadataInfo
     */
    private $cm;
    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var array
     */
    private $pageArgs;
    /**
     * @var ?QueryListenerInterface
     */
    private $queryListener;

    public function __construct(ClassMetadataInfo $cm, EntityManagerInterface $em, array $pageArgs, QueryListenerInterface $queryListener=null)
    {
        $this->cm = $cm;
        $this->em = $em;
        $this->pageArgs = $pageArgs;
        $this->queryListener = $queryListener;
    }
    /**
     * @{inheritdoc}
     */
    public function get($rootValue, $args, $ctx, ResolveInfo $resolveInfo): object
    {
        $repository = $this->em->getRepository($this->cm->name);

        return $repository->findOneBy($args);
    }
    /**
     * @{inheritdoc}
     */
    public function getMany($rootValue, $args, $ctx, ResolveInfo $resolveInfo): array
    {
        $repository = $this->em->getRepository($this->cm->name);
        $qb = $repository->createQueryBuilder("e");
        foreach($args as $field => $value) {
            if(empty($value)) {
                return [];
            }
            $qb->orWhere("e.{$field} IN (:${field})");
            $qb->setParameter("{$field}", $value);
        }
        return $qb->getQuery()->getResult();
    }
    /**
     * @{inheritdoc}
     */
    public function getPage($rootValue, $args, $ctx, ResolveInfo $resolveInfo): object
    {
        $selectedFields = $resolveInfo->getFieldSelection();
        $total  = 0;
        $filter = [];
        $match  = [];
        $sort   = [];
        $qb      = $this->em->createQueryBuilder()->select('e')->from($this->cm->name, 'e');
        $qbTotal = $this->em->createQueryBuilder()->select('count(e) total')->from($this->cm->name, 'e');
        $parameters = ['filter' => [], 'match' => []];
        if(isset($args['filter'])) {
            $this->walkFilters($qb, $this->pageArgs['filter']['type'], $args['filter'], 'e', function($exprs, $params) use($qb) {
                if(count($exprs) > 0) {
                    $qb->andWhere($qb->expr()->andX(...$exprs));
                }
                if(count($params) > 0) {
                    foreach($params as $field=>$value) {
                        $qb->setParameter($field, $value);
                    }
                }
            });
            $this->walkFilters($qbTotal, $this->pageArgs['filter']['type'], $args['filter'], 'e', function($exprs, $params) use($qbTotal) {
                if(count($exprs) > 0) {
                    $qbTotal->andWhere($qbTotal->expr()->andX(...$exprs));
                }
                if(count($params) > 0) {
                    foreach($params as $field=>$value) {
                        $qbTotal->setParameter($field, $value);
                    }
                }
            });
        }
        if(isset($args['match'])) {
            $this->walkFilters($qb, $this->pageArgs['match']['type'], $args['match'], 'e', function($exprs, $params) use($qb) {
                if(count($exprs) > 0) {
                    $qb->orWhere($qb->expr()->orX(...$exprs));
                }
                if(count($params) > 0) {
                    foreach($params as $field=>$value) {
                        $qb->setParameter($field, $value);
                    }
                }
            });
            $this->walkFilters($qbTotal, $this->pageArgs['match']['type'], $args['match'], 'e', function($exprs, $params) use($qbTotal) {
                if(count($exprs) > 0) {
                    $qbTotal->orWhere($qbTotal->expr()->orX(...$exprs));
                }
                if(count($params) > 0) {
                    foreach($params as $field=>$value) {
                        $qbTotal->setParameter($field, $value);
                    }
                }
            });
        }
        if(isset($args['sort'])) {
            foreach($args['sort'] as $fieldName=>$direction) {
                $qb->orderBy("e.".$fieldName, $direction);
            }
        }
        $page = $args['page'] > 0 ? $args['page'] - 1: 0;
        if(in_array('total', $selectedFields)) {
            foreach($parameters['filter'] as $key=>$value) {
                $qbTotal->setParameter($key, $value);
            }
            foreach($parameters['match'] as $key=>$value) {
                $qbTotal->setParameter($key, $value);
            }
            $res = $qbTotal->setMaxResults(1)->getQuery()->getArrayResult();
            $total = $res[0]['total'];
        }
        $result = new \stdClass;
        $result->total = $total;
        $result->page = $args['page'];
        $result->limit = $args['limit'];
        $result->filter = $filter;
        $result->match = $match;
        $result->sort = $sort;
        $result->items = $qb->setMaxResults($args['limit'])->setFirstResult($page * $args['limit'])->getQuery()->getResult();

        return $result;
    }
    /**
     * @param string $alias Entity alias
     * @param string $field Field name
     * @param array $filters Array of SearchFilterInput type values
     * @param callable $callback
     * @return void
     */
    private function iteratePredicates(string $alias, string $field, array $filters, callable $callback): void
    {
        $parameters = [];
        $expresions = [];
        foreach($filters as $filter) {
            $expr = new Expr();
            $paramName = ":{$alias}_{$field}";
            $parameters[$paramName] = $filter['value'];
            switch($filter['operator']) {
                case RegistryInterface::FILTER_OP_LESS_THAN:
                    $expresions[] = $expr->lt("{$alias}.{$field}", $paramName);
                    break;
                case RegistryInterface::FILTER_OP_LESS_THAN_EQUAL:
                    $expresions[] = $expr->lte("{$alias}.{$field}", $paramName);
                    break;
                case RegistryInterface::FILTER_OP_EQUAL:
                    $expresions[] = $expr->eq("{$alias}.{$field}", $paramName);
                    break;
                case RegistryInterface::FILTER_OP_GREATER_THAN:
                    $expresions[] = $expr->gt("{$alias}.{$field}", $paramName);
                    break;
                case RegistryInterface::FILTER_OP_GREATER_THAN_EQUAL:
                    $expresions[] = $expr->gte("{$alias}.{$field}", $paramName);
                    break;
                case RegistryInterface::FILTER_OP_NOT_EQUAL:
                    $expresions[] = $expr->neq("{$alias}.{$field}", $paramName);
                    break;
            }
        }
        call_user_func_array($callback, [$expresions, $parameters]);
    }
    /**
     * @param QueryBuilder $queryBuilder
     * @param InputTypeDefinition $type
     * @param array $filters
     * @param string $alias
     * @param callable $callback
     * @return void
     */
    private function walkFilters(QueryBuilder &$queryBuilder, InputTypeDefinition $type, array $filters, string $alias, callable $callback): void
    {
        foreach($type->getFields() as $fieldName => $fieldConfig) {
            if(!isset($filters[$fieldName])) continue;

            $filter = $filters[$fieldName];
            $fieldType = $fieldConfig['type'];
            $fieldAlias = "$alias{$fieldName[0]}";
            if($fieldType instanceof InputTypeDefinition) {
                $queryBuilder->join("$alias.$fieldName", $fieldAlias);
                $this->walkFilters($queryBuilder, $fieldType, $filter, $fieldAlias, $callback);
            } else {
                $this->iteratePredicates($alias, $fieldName, $filter, $callback);
            }
        }
    }
}
