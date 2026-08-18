<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Support\BudgetCycle;
use App\Support\DashboardAggregates;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Renders the authenticated user's finance overview for a budget-cycle
 * period (`GET /dashboard`, route name `dashboard`) - a calendar month for
 * a user with the default `cycle_start_day` of 1, otherwise whatever custom
 * period their `cycle_start_day` defines (see `App\Support\BudgetCycle`).
 *
 * Defaults to the period containing "now", but an optional `?period_start=`
 * query date (any date within the desired period, not necessarily its exact
 * start) switches to that period instead - e.g. so a user can still review
 * and generate an AI overview for a just-ended period after their cycle has
 * rolled over to a new one.
 */
class DashboardController extends Controller
{
    /**
     * @return View The `dashboard` view, given:
     *              - `periodStart`/`periodEnd`: the current period's bounds
     *              - `periodLabel`: a display label for the period (a month name if it's
     *              calendar-aligned, otherwise a date range)
     *              - `totalSpent`: sum of the user's expenses dated within this period
     *              - `categoryTotals`: Collection of category => summed amount for
     *              this period, sorted descending
     *              - `categories`: the fixed category list, for the quick-add form
     *              - `incomeExpectation`: this period's `IncomeExpectation`, or null if
     *              not set yet
     *              - `savingsGoal`: this period's `SavingsGoal`, or null if not set yet
     *              - `actualSavings`: expected income minus total spent, or null if no
     *              expected income is set (see the comment in the method body for
     *              why this can't be derived any other way)
     *              - `savingsProgress`: `actualSavings` as a percentage of the savings
     *              goal's target, clamped to [0, 100] for the progress bar, or null
     *              if either figure is missing
     *              - `recentExpenses`: the user's 5 most recent `Expense` models
     *              (by `date` desc, `created_at` desc as a tiebreaker), regardless
     *              of period - a recent-activity feed, not scoped to the current
     *              period like the totals above
     *              - `periodSummary`: the cached `PeriodSummary` (AI-generated spending
     *              overview) for this period, or null if one hasn't been generated yet
     *              (see `AiOverviewController`)
     *              - `periodOptions`: the last 12 periods (plus the currently viewed one,
     *              if it falls outside that window), newest first, for the period
     *              switcher dropdown - each `['start' => Carbon, 'label' => string]`
     *              - `previousPeriodStart`/`nextPeriodStart`: the start date of the
     *              adjacent period, for the switcher's prev/next arrows
     *              - `isCurrentPeriod`: whether the viewed period is the one containing
     *              "now", so the view can offer a way back to it when it isn't
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $cycleStartDay = $user->cycle_start_day;

        $referenceDate = self::parseDate($request->query('period_start'));
        $aggregates = DashboardAggregates::forUser($user, $referenceDate);
        $periodStart = $aggregates['periodStart'];
        $periodEnd = $aggregates['periodEnd'];

        $recentExpenses = $user->expenses()
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $periodSummary = $user->periodSummaries()
            ->whereDate('period_start', $periodStart->toDateString())
            ->first();

        return view('dashboard', [
            ...$aggregates,
            'categories' => Expense::CATEGORIES,
            'recentExpenses' => $recentExpenses,
            'periodSummary' => $periodSummary,
            'periodOptions' => self::periodOptions($cycleStartDay, $periodStart),
            'previousPeriodStart' => BudgetCycle::periodContaining($periodStart->copy()->subDay(), $cycleStartDay)['start'],
            'nextPeriodStart' => BudgetCycle::periodContaining($periodEnd->copy()->addDay(), $cycleStartDay)['start'],
            'isCurrentPeriod' => $periodStart->isSameDay(BudgetCycle::current($cycleStartDay)['start']),
        ]);
    }

    /**
     * The last 12 periods (oldest first from `recentPeriods()`, reversed to
     * newest first for the dropdown), with the viewed period spliced in if a
     * user has navigated further back than that window covers.
     *
     * @return Collection<int, array{start: Carbon, label: string}>
     */
    private static function periodOptions(int $cycleStartDay, Carbon $selectedStart): Collection
    {
        $periods = BudgetCycle::recentPeriods(12, $cycleStartDay)->reverse()->values();

        if (! $periods->contains(fn ($period) => $period['start']->isSameDay($selectedStart))) {
            $periods = $periods->push(BudgetCycle::periodContaining($selectedStart, $cycleStartDay))
                ->sortByDesc(fn ($period) => $period['start']->timestamp)
                ->values();
        }

        return $periods->map(fn ($period) => [
            'start' => $period['start'],
            'label' => BudgetCycle::label($period['start'], $period['end']),
        ]);
    }

    /**
     * Parses a `period_start` query/input value into a reference date for
     * `DashboardAggregates::forUser()`, or null (meaning "now") if it's
     * missing or unparseable - a hand-edited or stale URL shouldn't break
     * the page, it should just fall back to the current period.
     */
    private static function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return null;
        }
    }
}
