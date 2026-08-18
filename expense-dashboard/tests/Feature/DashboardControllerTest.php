<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\IncomeExpectation;
use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_shows_totals_income_and_savings_progress_for_the_current_month(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();

        Expense::factory()->for($user)->create(['category' => 'food', 'amount' => 300, 'date' => '2026-07-05']);
        Expense::factory()->for($user)->create(['category' => 'transport', 'amount' => 200, 'date' => '2026-07-10']);
        // Outside the current month - must not count toward the total.
        Expense::factory()->for($user)->create(['category' => 'food', 'amount' => 9999, 'date' => '2026-06-20']);

        IncomeExpectation::factory()->for($user)->create(['period_start' => '2026-07-01', 'expected_amount' => 1000]);
        SavingsGoal::factory()->for($user)->create(['period_start' => '2026-07-01', 'target_amount' => 400]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('500.00'); // total spent this period: 300 + 200, excludes the June expense
        $response->assertSee('1,000.00'); // expected income
        $response->assertSee('400.00'); // savings goal target
        // actual savings = 1000 - 500 = 500, which exceeds the 400 target,
        // so progress is clamped to 100% rather than reading 125%.
        $response->assertSee('100%');
        // The June expense legitimately still appears in the not-period-scoped
        // Recent Expenses list below (see RecentExpenses tests) - it's only
        // excluded from this period's *total*, which is verified above.
        $response->assertSee('9,999.00');
    }

    public function test_dashboard_does_not_divide_by_zero_when_the_savings_goal_target_is_zero(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();
        Expense::factory()->for($user)->create(['amount' => 100, 'date' => '2026-07-05']);
        IncomeExpectation::factory()->for($user)->create(['period_start' => '2026-07-01', 'expected_amount' => 500]);
        SavingsGoal::factory()->for($user)->create(['period_start' => '2026-07-01', 'target_amount' => 0]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }

    public function test_dashboard_clamps_progress_to_zero_when_overspent(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();
        Expense::factory()->for($user)->create(['amount' => 900, 'date' => '2026-07-05']);
        IncomeExpectation::factory()->for($user)->create(['period_start' => '2026-07-01', 'expected_amount' => 500]);
        SavingsGoal::factory()->for($user)->create(['period_start' => '2026-07-01', 'target_amount' => 400]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        // actual savings = 500 - 900 = -400, which is below zero, so
        // progress must clamp to 0% rather than a negative percentage.
        $response->assertSee('0%');
        $response->assertDontSee('-100%');
    }

    public function test_dashboard_prompts_for_missing_income_and_goal_instead_of_breaking(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();
        Expense::factory()->for($user)->create(['amount' => 50, 'date' => '2026-07-05']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee("You haven't set expected income for this period yet.", false);
        $response->assertSee("You haven't set a savings goal for this period yet.", false);
        $response->assertDontSee('%'); // no progress bar can be computed without both figures
    }

    public function test_category_totals_for_the_dashboard_are_grouped_and_summed_correctly(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();
        Expense::factory()->for($user)->create(['category' => 'food', 'amount' => 100, 'date' => '2026-07-01']);
        Expense::factory()->for($user)->create(['category' => 'food', 'amount' => 50, 'date' => '2026-07-02']);
        Expense::factory()->for($user)->create(['category' => 'transport', 'amount' => 75, 'date' => '2026-07-03']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        // food: 150, transport: 75, total spent: 225.
        $response->assertSee('225.00');
        $response->assertSee(json_encode(['Food', 'Transport']), false);
        $response->assertSee(json_encode([150, 75]), false);
    }

    public function test_recent_expenses_shows_the_5_most_recent_by_date_descending(): void
    {
        $user = User::factory()->create();

        Expense::factory()->for($user)->create(['date' => '2026-07-01', 'notes' => 'Newest']);
        Expense::factory()->for($user)->create(['date' => '2026-06-28', 'notes' => 'Sixth']);
        Expense::factory()->for($user)->create(['date' => '2026-06-25', 'notes' => 'Fifth']);
        Expense::factory()->for($user)->create(['date' => '2026-06-20', 'notes' => 'Fourth']);
        Expense::factory()->for($user)->create(['date' => '2026-06-15', 'notes' => 'Third']);
        Expense::factory()->for($user)->create(['date' => '2026-06-10', 'notes' => 'Second']);
        Expense::factory()->for($user)->create(['date' => '2026-06-01', 'notes' => 'Oldest']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        // Only the 5 most recent by date show, in descending order - the
        // 2 oldest are excluded from the list entirely.
        $response->assertSeeInOrder(['Newest', 'Sixth', 'Fifth', 'Fourth', 'Third']);
        $response->assertDontSee('Second');
        $response->assertDontSee('Oldest');
    }

    public function test_recent_expenses_uses_created_at_as_a_tiebreaker_for_the_same_date(): void
    {
        $user = User::factory()->create();

        $this->travelTo(Carbon::create(2026, 7, 1, 10, 0, 0));
        Expense::factory()->for($user)->create(['date' => '2026-07-01', 'notes' => 'CreatedFirst']);

        $this->travelTo(Carbon::create(2026, 7, 1, 10, 0, 5));
        Expense::factory()->for($user)->create(['date' => '2026-07-01', 'notes' => 'CreatedSecond']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        // Same `date` for both - the one created later (created_at desc)
        // must be listed first.
        $response->assertSeeInOrder(['CreatedSecond', 'CreatedFirst']);
    }

    public function test_recent_expenses_only_shows_the_authenticated_users_own(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Expense::factory()->for($user)->create(['date' => '2026-07-01', 'notes' => 'Mine']);
        Expense::factory()->for($other)->create(['date' => '2026-07-02', 'notes' => 'NotMine']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Mine');
        $response->assertDontSee('NotMine');
    }

    public function test_recent_expenses_shows_an_empty_state_with_zero_expenses(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee("You haven't logged any expenses yet.", false);
    }

    public function test_a_user_only_sees_their_own_data_on_the_dashboard(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();
        $other = User::factory()->create();

        Expense::factory()->for($user)->create(['amount' => 100, 'date' => '2026-07-05']);
        Expense::factory()->for($other)->create(['amount' => 99999, 'date' => '2026-07-05']);
        IncomeExpectation::factory()->for($other)->create(['period_start' => '2026-07-01', 'expected_amount' => 55555]);
        SavingsGoal::factory()->for($other)->create(['period_start' => '2026-07-01', 'target_amount' => 44444]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('100.00');
        $response->assertDontSee('99,999.00');
        $response->assertDontSee('55,555.00');
        $response->assertDontSee('44,444.00');
        // The user has no income expectation or savings goal of their own
        // for this period, so the prompts should show despite $other's rows existing.
        $response->assertSee("You haven't set expected income for this period yet.", false);
        $response->assertSee("You haven't set a savings goal for this period yet.", false);
    }

    public function test_an_expense_dated_on_the_last_day_of_the_period_counts_toward_the_total(): void
    {
        // Regression test: SQLite persists the `date`-cast column as a full
        // datetime string (e.g. "2026-07-31 00:00:00"), which sorts *after*
        // a bare "2026-07-31" upper bound in a string comparison - a
        // whereBetween() with plain date-string bounds would silently drop
        // this expense from the period total it actually belongs to.
        $this->travelTo(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();
        Expense::factory()->for($user)->create(['amount' => 100, 'date' => '2026-07-01']);
        Expense::factory()->for($user)->create(['amount' => 200, 'date' => '2026-07-31']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('300.00');
    }

    public function test_period_start_query_param_switches_the_dashboard_to_a_different_period(): void
    {
        // Mirrors a user with a 17th-to-16th custom cycle: on Aug 18 the
        // "current" period has already rolled to Aug 17 - Sep 16, so an
        // expense from the just-ended Jul 17 - Aug 16 period is no longer
        // visible by default and must be reachable via ?period_start=.
        $this->travelTo(Carbon::create(2026, 8, 18));

        $user = User::factory()->create(['cycle_start_day' => 17]);
        Expense::factory()->for($user)->create(['amount' => 250, 'date' => '2026-08-01']);

        // The "Recent Expenses" activity feed is intentionally not
        // period-scoped (see DashboardControllerTest's own coverage of
        // that), so this checks the period-scoped "Total Spent" figure
        // instead of asserting the raw amount is absent from the page.
        $default = $this->actingAs($user)->get('/dashboard');
        $default->assertOk();
        $default->assertSee('Aug 17 - Sep 16, 2026', false);
        $default->assertSee('Total Spent This Period');
        $default->assertSee('$0.00', false);

        $switched = $this->actingAs($user)->get('/dashboard?period_start=2026-08-01');
        $switched->assertOk();
        $switched->assertSee('Jul 17 - Aug 16, 2026', false);
        $switched->assertSee('$250.00', false);
    }

    public function test_an_unparseable_period_start_falls_back_to_the_current_period_instead_of_erroring(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();
        Expense::factory()->for($user)->create(['amount' => 100, 'date' => '2026-07-05']);

        $response = $this->actingAs($user)->get('/dashboard?period_start=not-a-date');

        $response->assertOk();
        $response->assertSee('July 2026', false);
        $response->assertSee('100.00');
    }

    public function test_period_switcher_prev_next_links_point_to_adjacent_periods(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee(route('dashboard', ['period_start' => '2026-06-01']), false);
        $response->assertSee(route('dashboard', ['period_start' => '2026-08-01']), false);
    }

    public function test_period_switcher_dropdown_marks_the_viewed_period_selected(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard?period_start=2026-06-10');

        $response->assertOk();
        $response->assertSee('value="'.route('dashboard', ['period_start' => '2026-06-01']).'" selected', false);
    }

    public function test_another_users_custom_cycle_start_day_does_not_affect_this_users_period(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 15));

        $user = User::factory()->create(['cycle_start_day' => 1]);
        $other = User::factory()->create(['cycle_start_day' => 20]);

        // On Jul 15, $other's 20th-of-the-month cycle is still in the
        // period that started Jun 20 - so this expense, dated Jul 10,
        // would fall in $other's *previous* period if their cycle_start_day
        // leaked into $user's calculation. It must only ever be evaluated
        // against $user's own (default, calendar-month) cycle.
        Expense::factory()->for($user)->create(['amount' => 300, 'date' => '2026-07-10']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('July 2026', false);
        $response->assertSee('300.00');
    }
}
