<?php
declare(strict_types=1);
namespace LLA\DoctrineGraphQL\Mutation;

interface MutationManagerInterface
{
    /**
     * Set mutation listener
     *
     * @param MutationListenerInterface $listener
     * @return MutationManagerInterface
     */
    function setListener(MutationListenerInterface $listener): MutationManagerInterface;
    /**
     * @param mixed $val
     * @param mixed $args
     * @return mixed
     */
    function createMutation($val, $args);
    /**
     * @param mixed $val
     * @param mixed $args
     * @return mixed
     */
    function updateMutation($val, $args);
    /**
     * @param mixed $val
     * @param mixed $args
     * @return mixed
     */
    function deleteMutation($val, $args);
}
