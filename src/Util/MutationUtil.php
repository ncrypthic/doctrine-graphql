<?php
declare(strict_types=1);

namespace LLA\DoctrineGraphQL\Util;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class MutationUtil
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
     * @var MutationUtil
     */
    private static $instance;
    private function __construct(ClassMetadataInfo $cm, EntityManagerInterface $em)
    {
        $this->cm = $cm;
        $this->em = $em;
    }
    /**
     * @param mixed $className
     * @param mixed $value
     * @param array $args
     * @return mixed
     */
    private function mergeDeep($entity, $val, array $args)
    {
        $cm = $this->em->getClassMetadata(get_class($entity));
        foreach($args as $field=>$value) {
            if($cm->hasAssociation($field)) {
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
                        $this->mergeDeep($relationship, $val, $child);
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
                    $this->mergeDeep($relationship, $val, $value);
                    call_user_func([$entity, 'set'.ucfirst($field)], $relationship);
                }
            } else {
                call_user_func([$entity, 'set'.ucfirst($field)], $value);
            }
        }
        $this->em->persist($entity);
        $this->em->flush();
        return $entity;
    }
    /**
     * @param mixed $val
     * @param mixed $args
     * @return mixed
     */
    public function insertMutation($val, $args)
    {
        $reflect = new \ReflectionClass($this->cm->name);
        $entity = $reflect->newInstance();
        $conn = $this->em->getConnection();
        try {
            $conn->beginTransaction();
            $entity = $this->mergeDeep($entity, $val, $args['input']);
            $conn->commit();
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }
        return $entity;
    }
    /**
     * @param mixed $val
     * @param mixed $args
     * @return mixed
     */
    public function editMutation($val, $args)
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
            $entity = $this->mergeDeep($entity, $val, $input);
            $conn->commit();
        } catch (\Exception $e) {
            $conn->rollback();
            throw $e;
        }

        return $entity;
    }
    /**
     * @param mixed $val
     * @param mixed $args
     * @return mixed
     */
    public function removeMutation($val, $args)
    {
        $reflect = new \ReflectionClass($cm->name);
        $entity = $repository->findOneBy($val);
        if(!empty($entity)) {
            $em->remove($entity);
            $em->flush();
        }
    }
    /**
     * @param ClassMetadataInfo $cm
     * @param EntityManagerInterface $em
     * @return MutationUtil
     */
    public static function createMutation(ClassMetadataInfo $cm, EntityManagerInterface $em)
    {
        $instance = new MutationUtil($cm, $em);

        return [$instance, 'insertMutation'];
    }
    /**
     * @param ClassMetadataInfo $cm
     * @param EntityManagerInterface $em
     * @return MutationUtil
     */
    public static function updateMutation(ClassMetadataInfo $cm, EntityManagerInterface $em)
    {
        $instance = new MutationUtil($cm, $em);

        return [$instance, 'editMutation'];
    }
    /**
     * @param ClassMetadataInfo $cm
     * @param EntityManagerInterface $em
     * @return MutationUtil
     */
    public static function deleteMutation(ClassMetadataInfo $cm, EntityManagerInterface $em)
    {
        $instance = new MutationUtil($cm, $em);

        return [$instance, 'removeMutation'];
    }
}
