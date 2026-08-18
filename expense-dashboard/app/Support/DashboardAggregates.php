<?php

namespace App\Support;

use App\Models\IncomeExpectation;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The current-period spending aggregates shown on `/dashboard` - factored
 * out of `DashboardController` so the AI overview feature
 * (`AiOverviewController`/`AiOverviewService`) can build its prompt from
 * the exact same figures instead of recomputing them, and so the two never
 * drift out of sync.
 */
class DashboardAggregates
{
    /**
     * @return array{
     *     periodStart: Carbon,
     *     periodEnd: Carbon,
     *     periodLabel: string,
     *     totalSpent: string,
     *     categoryTotals: Collection<string, string>,
     *     incomeExpectation: ?IncomeExpectation,
     *     savingsGoal: ?SavingsGoal,
     *     actualSavings: ?float,
     *     savingsProgress: ?int,
     * }
     */
    public static function forUser(User $user, ?Carbon $referenceDate = null): array
    {
        $period = BudgetCycle::current($user->cycle_start_day, $referenceDate);
        $periodStart = $period['start'];
        $periodEnd = $period['end'];

        $periodExpenses = $user->expenses()
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get();

        $totalSpent = $periodExpenses->sum('amount');

        $categoryTotals = $periodExpenses->groupBy('category')
            ->map(fn ($group) => $group->sum('amount'))
            ->sortDesc();

        $incomeExpectation = $user->incomeExpectations()->whereDate('period_start', $periodStart->toDateString())->first();
        $savingsGoal = $user->savingsGoals()->whereDate('period_start', $periodStart->toDateString())->first();

        // "Actual savings" for the period is expected income minus what's
        // actually been spent so far - i.e. the money left over that could
        // go toward the goal. This needs expected income as a baseline: with
        // no income set we have no way to know what "leftover" even means,
        // so we leave this null (prompting the user to set one) rather than
        // treating unset income as $0, which would misleadingly read as
        // "you overspent" before the user has entered anything.
        $actualSavings = $incomeExpectation
            ? $incomeExpectation->expected_amount - $totalSpent
            : null;

        $savingsProgress = ($savingsGoal && $actualSavings !== null && $savingsGoal->target_amount > 0)
            ? max(0, min(100, round(($actualSavings / $savingsGoal->target_amount) * 100)))
            : null;

        return [
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'periodLabel' => BudgetCycle::label($periodStart, $periodEnd),
            'totalSpent' => $totalSpent,
            'categoryTotals' => $categoryTotals,
            'incomeExpectation' => $incomeExpectation,
            'savingsGoal' => $savingsGoal,
            'actualSavings' => $actualSavings,
            'savingsProgress' => $savingsProgress,
        ];
    }
}
