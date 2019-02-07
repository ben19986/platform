<?php declare(strict_types=1);

namespace Shopware\Core\Framework\StateMachine;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Symfony\Component\HttpFoundation\JsonResponse;

class StateMachineRegistry
{
    /**
     * @var EntityRepositoryInterface
     */
    protected $stateMachineRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $stateMachineHistoryRepository;

    /**
     * @var StateMachineEntity[]
     */
    protected $stateMachines;

    public function __construct(EntityRepositoryInterface $stateMachineRepository, EntityRepositoryInterface $stateMachineHistoryRepository)
    {
        $this->stateMachineRepository = $stateMachineRepository;
        $this->stateMachineHistoryRepository = $stateMachineHistoryRepository;
    }

    /**
     * @param string  $name
     * @param Context $context
     *
     * @throws StateMachineNotFoundException
     * @throws InconsistentCriteriaIdsException
     *
     * @return StateMachineEntity
     */
    public function getStateMachine(string $name, Context $context): StateMachineEntity
    {
        if (isset($this->stateMachines[$name])) {
            return $this->stateMachines[$name];
        }

        $transitionCriteria = new Criteria();
        $transitionCriteria->addSorting(new FieldSorting('state_machine_transition.actionName'));
        $statesCriteria = new Criteria();
        $statesCriteria->addSorting(new FieldSorting('state_machine_state.technicalName'));

        $criteria = new Criteria();
        $criteria->addAssociation('state_machine.transitions', $transitionCriteria);
        $criteria->addAssociation('state_machine.states', $statesCriteria);
        $criteria->addFilter(new EqualsFilter('state_machine.technicalName', $name));
        $criteria->setLimit(1);

        $results = $this->stateMachineRepository->search($criteria, $context);

        if ($results->count() === 0) {
            throw new StateMachineNotFoundException($name);
        }

        return $this->stateMachines[$name] = $results->first();
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     * @throws StateMachineWithoutInitialStateException
     */
    public function getInitialState(string $stateMachineName, Context $context): StateMachineStateEntity
    {
        /** @var StateMachineEntity|null $stateMachine */
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        if ($stateMachine === null) {
            throw new StateMachineNotFoundException($stateMachineName);
        }

        $initialState = $stateMachine->getInitialState();
        if ($initialState === null) {
            throw new StateMachineWithoutInitialStateException($stateMachineName);
        }

        return $initialState;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     * @throws StateMachineStateNotFoundException
     */
    public function getStateByTechnicalName(string $stateMachineName, string $technicalName, Context $context): StateMachineStateEntity
    {
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        /** @var StateMachineStateEntity $state */
        foreach ($stateMachine->getStates() as $state) {
            if ($state->getTechnicalName() === $technicalName) {
                return $state;
            }
        }

        throw new StateMachineStateNotFoundException($stateMachineName, $technicalName);
    }

    /**
     * @throws StateMachineNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function getAvailableTransitions(string $stateMachineName, string $fromStateName, Context $context): array
    {
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        $transitions = [];
        /** @var StateMachineTransitionEntity $transition */
        foreach ($stateMachine->getTransitions() as $transition) {
            if ($transition->getFromStateMachineState()->getTechnicalName() === $fromStateName) {
                $transitions[] = $transition;
            }
        }

        return $transitions;
    }

    /**
     * @throws StateMachineNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    public function buildAvailableTransitionsJsonResponse(string $stateMachineName, string $fromStateName, string $baseUrl, Context $context): JsonResponse
    {
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        $currentStateJson = null;

        /** @var StateMachineStateEntity $place */
        foreach ($stateMachine->getStates()->getElements() as $place) {
            if ($place->getTechnicalName() === $fromStateName) {
                $currentStateJson = [
                    'name' => $place->getName(),
                    'technicalName' => $place->getTechnicalName(),
                ];
                break;
            }
        }

        $availableTransitions = $this->getAvailableTransitions($stateMachineName, $fromStateName, $context);
        $transitionsJson = [];
        foreach ($availableTransitions as $transition) {
            $transitionsJson[] = [
                'name' => $transition->getToStateMachineState()->getName(),
                'technicalName' => $transition->getToStateMachineState()->getTechnicalName(),
                'actionName' => $transition->getActionName(),
                'url' => $baseUrl . '/' . $transition->getActionName(),
            ];
        }

        return new JsonResponse([
            'currentState' => $currentStateJson,
            'transitions' => $transitionsJson,
        ]);
    }

    /**
     * @throws IllegalTransitionException
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     *
     * @return StateMachineStateEntity
     */
    public function getTransitionDestination(string $stateMachineName, string $fromStateName, string $transitionName, Context $context): StateMachineStateEntity
    {
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        /** @var StateMachineTransitionEntity $transition */
        foreach ($stateMachine->getTransitions() as $transition) {
            if ($transition->getActionName() === $transitionName && $transition->getFromStateMachineState()->getTechnicalName() === $fromStateName) {
                return $transition->getToStateMachineState();
            }
        }

        $transitions = $this->getAvailableTransitions($stateMachineName, $fromStateName, $context);
        $transitionNames = array_map(function (StateMachineTransitionEntity $transition) {
            return $transition->getActionName();
        }, $transitions);

        throw new IllegalTransitionException(
            $fromStateName,
            $transitionName,
            $transitionNames
        );
    }

    /**
     * @throws IllegalTransitionException
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     */
    public function transition(
        StateMachineEntity $stateMachine,
        StateMachineStateEntity $fromPlace,
        string $entityName,
        string $entityId,
        Context $context,
        string $transitionName = null
    ): StateMachineStateEntity {
        if (empty($transitionName)) {
            $transitions = $this->getAvailableTransitions($stateMachine->getTechnicalName(), $fromPlace->getTechnicalName(), $context);
            $transitionNames = array_map(function (StateMachineTransitionEntity $transition) {
                return $transition->getActionName();
            }, $transitions);

            throw new IllegalTransitionException($fromPlace->getName(), '', $transitionNames);
        }

        $toPlace = $this->getTransitionDestination($stateMachine->getTechnicalName(), $fromPlace->getTechnicalName(), $transitionName, $context);

        $this->writeStateHistory($entityName, $entityId, $transitionName, $fromPlace, $toPlace, $context);

        return $toPlace;
    }

    private function writeStateHistory(
        string $entityName,
        string $entityId,
        string $transitionName,
        StateMachineStateEntity $fromPlace,
        StateMachineStateEntity $toPlace,
        Context $context)
    {
        $this->stateMachineHistoryRepository->create([
            [
                'stateMachineId' => $toPlace->getStateMachineId(),
                'entityName' => $entityName,
                'entityId' => ['id' => $entityId, 'version_id' => $context->getVersionId()],
                'fromStateId' => $fromPlace->getId(),
                'toStateId' => $toPlace->getId(),
                'transitionActionName' => $transitionName,
                'userId' => $context->getSourceContext()->getUserId(),
            ],
        ], $context);
    }
}