<?php declare(strict_types=1);

namespace LieferzeitenAdmin\Service\Notification;

use LieferzeitenAdmin\Entity\TaskAssignmentRuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class TaskAssignmentRuleResolver
{
    public function __construct(private readonly EntityRepository $taskAssignmentRuleRepository)
    {
    }

    /**
     * @param array<string, mixed> $businessContext
     *
     * @return array<string, mixed>|null
     */
    public function resolve(string $trigger, Context $context, array $businessContext = []): ?array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('triggerKey', $trigger));
        $criteria->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING));

        /** @var iterable<TaskAssignmentRuleEntity> $rules */
        $rules = $this->taskAssignmentRuleRepository->search($criteria, $context)->getEntities();

        foreach ($rules as $rule) {
            if (!$this->matchesRule($rule, $context, $businessContext)) {
                continue;
            }

            return [
                'id' => $rule->getUniqueIdentifier(),
                'name' => $rule->getName(),
                'ruleId' => $rule->getRuleId(),
                'assigneeType' => $rule->getAssigneeType(),
                'assigneeIdentifier' => $rule->getAssigneeIdentifier(),
                'conditions' => $rule->getConditions(),
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $businessContext
     */
    private function matchesRule(TaskAssignmentRuleEntity $rule, Context $context, array $businessContext): bool
    {
        $ruleIdMatches = $this->matchesShopwareRuleId($rule->getRuleId(), $context, $businessContext);
        $conditionsMatches = $this->matchesConditions($rule->getConditions(), $businessContext);

        $hasRuleId = is_string($rule->getRuleId()) && trim($rule->getRuleId()) !== '';
        $hasConditions = is_array($rule->getConditions()) && $rule->getConditions() !== [];

        if (!$hasRuleId && !$hasConditions) {
            return true;
        }

        if ($hasRuleId && $hasConditions) {
            return $ruleIdMatches && $conditionsMatches;
        }

        return $hasRuleId ? $ruleIdMatches : $conditionsMatches;
    }

    /**
     * @param array<string, mixed> $businessContext
     */
    private function matchesShopwareRuleId(?string $ruleId, Context $context, array $businessContext): bool
    {
        if (!is_string($ruleId) || trim($ruleId) === '') {
            return true;
        }

        $trimmedRuleId = trim($ruleId);

        if (isset($businessContext['ruleIds']) && is_array($businessContext['ruleIds'])) {
            $ruleIds = array_values(array_filter(
                $businessContext['ruleIds'],
                static fn ($id): bool => is_string($id) && $id !== '',
            ));

            return in_array($trimmedRuleId, $ruleIds, true);
        }

        if (!method_exists($context, 'getRuleIds')) {
            return false;
        }

        $contextRuleIds = $context->getRuleIds();

        return in_array($trimmedRuleId, $contextRuleIds, true);
    }

    /**
     * @param array<string, mixed>|null $conditions
     * @param array<string, mixed> $businessContext
     */
    private function matchesConditions(?array $conditions, array $businessContext): bool
    {
        if (!is_array($conditions) || $conditions === []) {
            return true;
        }

        if (array_key_exists('all', $conditions) && is_array($conditions['all'])) {
            foreach ($conditions['all'] as $condition) {
                if (!is_array($condition) || !$this->matchesConditionNode($condition, $businessContext)) {
                    return false;
                }
            }

            return true;
        }

        if (array_key_exists('any', $conditions) && is_array($conditions['any'])) {
            foreach ($conditions['any'] as $condition) {
                if (is_array($condition) && $this->matchesConditionNode($condition, $businessContext)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($conditions as $field => $expected) {
            if (!is_string($field) || $field === '') {
                continue;
            }

            $actual = $this->readBusinessValue($businessContext, $field);
            if (!$this->compareValue($actual, $expected, 'eq')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $businessContext
     */
    private function matchesConditionNode(array $node, array $businessContext): bool
    {
        $field = isset($node['field']) && is_string($node['field']) ? trim($node['field']) : '';
        $operator = isset($node['operator']) && is_string($node['operator']) ? strtolower(trim($node['operator'])) : 'eq';
        $expected = $node['value'] ?? null;

        if ($field === '') {
            return false;
        }

        $actual = $this->readBusinessValue($businessContext, $field);

        return $this->compareValue($actual, $expected, $operator);
    }

    /**
     * @param mixed $actual
     * @param mixed $expected
     */
    private function compareValue(mixed $actual, mixed $expected, string $operator): bool
    {
        return match ($operator) {
            'neq', '!=' => !$this->compareValue($actual, $expected, 'eq'),
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && !in_array($actual, $expected, true),
            'contains' => is_string($actual) && is_string($expected) && mb_stripos($actual, $expected) !== false,
            'empty' => $actual === null || $actual === '' || $actual === [],
            'not_empty' => !($actual === null || $actual === '' || $actual === []),
            default => $actual === $expected,
        };
    }

    /**
     * @param array<string, mixed> $businessContext
     */
    private function readBusinessValue(array $businessContext, string $path): mixed
    {
        if (array_key_exists($path, $businessContext)) {
            return $businessContext[$path];
        }

        $segments = explode('.', $path);
        $value = $businessContext;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
