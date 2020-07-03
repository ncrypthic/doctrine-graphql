<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQL\Mutation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class MutationManager implements MutationManagerInterface
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
     * @var ?MutationListenerInterface
     */
    private $listener;

    public function __construct(ClassMetadataInfo $cm, EntityManagerInterface $em, MutationListenerInterface $listener = null)
    {
        $this->cm = $cm;
        $this->em = $em;
        $this->listener = $listener;
    }
    /**
     * @{inheritdoc}
     */
    public function setListener(MutationListenerInterface $listener): MutationManagerInterface
    {
        $this->listener = $listener;

        return $this;
    }
    /**
     * @{inheritdoc}
     */
    public function createMutation($val, $args)
    {
        $reflect = new \ReflectionClass($this->cm->name);
        $entity = $reflect->newInstance();
        $conn = $this->em->getConnection();
        try {
            $conn->beginTransaction();
            $entity = $this->mergeDeep($entity, $args['input']);
            $this->notifyListener(MutationListenerInterface::MUTATION_CREATE, $entity);
            $conn->commit();
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
        return $entity;
    }
    /**
     * @{inheritdoc}
     */
    public function updateMutation($val, $args)
    {
        $input = $args['input'];
        $identifiers = $this->cm->getIdentifierFieldNames();
        $idFields = [];
        $values = [];
        foreach($input as $field=>$value) {
            if(in_array($field, $identifiers)) {
                $idFields[$field] = $value;
            } else {
                $values[$field] = $value;
            }
        }
        $conn = $this->em->getConnection();
        try {
            $conn->beginTransaction();
            $repository = $this->em->getRepository($this->cm->name);
            $entity = $repository->findOneBy($idFields);
            $entity = $this->mergeDeep($entity, $input);
            $this->notifyListener(MutationListenerInterface::MUTATION_UPDATE, $entity);
            $conn->commit();
        } catch (\Exception $e) {
            $conn->rollback();
            throw $e;
        }

        return $entity;
    }
    /**
     * @{inheritdoc}
     */
    public function deleteMutation($val, $args)
    {
        $input = $args;
        $identifiers = $this->cm->getIdentifierFieldNames();
        $idFields = [];
        $values = [];
        foreach($input as $field=>$value) {
            if(in_array($field, $identifiers)) {
                $idFields[$field] = $value;
            } else {
                $values[$field] = $value;
            }
        }
        $repository = $this->em->getRepository($this->cm->name);
        $entity = $repository->findOneBy($idFields);
        if(!empty($entity)) {
            $this->notifyListener(MutationListenerInterface::MUTATION_DELETE, $entity);
            $this->em->remove($entity);
            $this->em->flush();
        }
    }

    /**
     * @param mixed $className
     * @param mixed $value
     * @param array $args
     * @return mixed
     */
    private function mergeDeep($entity, array $args)
    {
        $cm = $this->em->getClassMetadata(get_class($entity));
        foreach($args as $field=>$value) {
            if($cm->hasAssociation($field)) {
                $this->mergeDeepAssoc($cm, $entity, $field, $value);
            } else {
                call_user_func([$entity, 'set'.ucfirst($field)], $value);
            }
        }
        $this->em->persist($entity);
        $this->em->flush();
        return $entity;
    }

    public function mergeDeepAssoc(ClassMetadataInfo $cm, $entity, $field, $value)
    {
        $targetClass = $cm->getAssociationTargetClass($field);
        $relationshipMeta = $this->em->getClassMetadata($targetClass);
        $relationshipIdFields = $relationshipMeta->getIdentifierFieldNames();
        $repo = $this->em->getRepository($targetClass);
        if($cm->isCollectionValuedAssociation($field)) {
            foreach($value as $child) {
                $idValues = [];
                foreach($relationshipIdFields as $idField) {
                    if(isset($child[$idField])) {
                       $idValues[$idField] = $child[$idField];
                    }
                }
                $relationship = empty($idValues)
                    ? $relationshipMeta->getReflectionClass()->newInstance()
                    : $repo->findOneBy($idValues);
                $this->mergeDeep($relationship, $child);
                call_user_func([$relationship, 'add'.ucfirst($field)], $relationship);
            }
        } else {
            $idValues = [];
            foreach($relationshipIdFields as $idField) {
                if(isset($value[$idField])) {
                   $idValues[$idField] = $value[$idField];
                }
            }
            if(empty($idValues)) {
                $relationship = $relationshipMeta->getReflectionClass()->newInstance();
            } else {
                $relationship = $repo->findOneBy($idValues);
                if(empty($relationship)) {
                    $relationship = $relationshipMeta->getReflectionClass()->newInstance();
                    foreach($idValues as $idField=>$idValue) {
                        call_user_func([$relationship, 'set'.ucfirst($idField)], $idValue);
                    }
                }
            }
            $this->mergeDeep($relationship, $value);
            call_user_func([$entity, 'set'.ucfirst($field)], $relationship);
        }
    }

    private function notifyListener(string $type, object $entity)
    {
        if(!empty($this->listener)) {
            switch($type) {
                case MutationListenerInterface::MUTATION_DELETE:
                    $this->listener->onCreate($entity);
                    break;
                case MutationListenerInterface::MUTATION_UPDATE:
                    $this->listener->onUpdate($entity);
                    break;
                case MutationListenerInterface::MUTATION_DELETE:
                    $this->listener->onDelete($entity);
                    break;
                default:
                    $entity;
            }
        }
    }
}
