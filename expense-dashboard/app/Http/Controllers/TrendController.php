<?php

namespace App\Http\Controllers;

use App\Support\BudgetCycle;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Renders the authenticated user's total spend per budget-cycle period for
 * the last 6 periods, including the current one (`GET /trends`, route name
 * `trends`). A period is a calendar month for a user with the default
 * `cycle_start_day` of 1, otherwise whatever custom period their
 * `cycle_start_day` defines (see `App\Support\BudgetCycle`).
 */
class TrendController extends Controller
{
    /**
     * @return View The `trends` view, given `periodTotals`: a Collection of
     *              6 entries, oldest to newest, ending with the current
     *              period - each `['start' => Carbon, 'end' => Carbon,
     *              'label' => string, 'shortLabel' => string, 'total' =>
     *              int|string]`. A period with no expenses still gets an
     *              entry with a `0` total rather than being omitted, so the
     *              chart/table stay continuous across all 6 periods.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $cycleStartDay = $user->cycle_start_day;

        $periods = BudgetCycle::recentPeriods(6, $cycleStartDay);
        $start = $periods->first()['start'];
        $end = $periods->last()['end'];

        // whereDate(), not whereBetween() with plain date strings - see the
        // comment in DashboardAggregates::forUser() for why the latter
        // silently drops expenses dated on $end.
        $totalsByPeriodStart = $user->expenses()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()
            ->groupBy(fn ($expense) => BudgetCycle::periodContaining($expense->date, $cycleStartDay)['start']->toDateString())
            ->map(fn ($group) => $group->sum('amount'));

        $periodTotals = $periods->map(fn ($period) => [
            'start' => $period['start'],
            'end' => $period['end'],
            'label' => BudgetCycle::label($period['start'], $period['end']),
            'shortLabel' => BudgetCycle::shortLabel($period['start'], $period['end']),
            'total' => $totalsByPeriodStart->get($period['start']->toDateString(), 0),
        ]);

        return view('trends', ['periodTotals' => $periodTotals]);
    }
}
