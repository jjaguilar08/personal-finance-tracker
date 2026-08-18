<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrendControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/trends')->assertRedirect('/login');
    }

    public function test_shows_correct_totals_per_month_for_the_last_6_months(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();

        Expense::factory()->for($user)->create(['amount' => 100, 'date' => '2026-02-10']);
        Expense::factory()->for($user)->create(['amount' => 50, 'date' => '2026-02-20']);
        Expense::factory()->for($user)->create(['amount' => 300, 'date' => '2026-05-05']);
        Expense::factory()->for($user)->create(['amount' => 400, 'date' => '2026-07-01']);
        // 8 months before "now" - outside the 6-month window, must not count.
        Expense::factory()->for($user)->create(['amount' => 99999, 'date' => '2025-11-01']);

        $response = $this->actingAs($user)->get('/trends');

        $response->assertOk();
        $response->assertSee('February 2026');
        $response->assertSee('150.00'); // Feb total: 100 + 50
        $response->assertSee('May 2026');
        $response->assertSee('300.00');
        $response->assertSee('July 2026');
        $response->assertSee('400.00');
        $response->assertDontSee('99,999.00');
    }

    public function test_zero_expense_months_show_as_zero_not_omitted(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();

        // Only the current month has any expenses - the other 5 of the
        // last 6 months have none.
        Expense::factory()->for($user)->create(['amount' => 500, 'date' => '2026-07-10']);

        $response = $this->actingAs($user)->get('/trends');

        $response->assertOk();
        // All 6 months appear, in order - a month with no expenses must
        // still show up rather than being skipped.
        $response->assertSeeInOrder(['February 2026', 'March 2026', 'April 2026', 'May 2026', 'June 2026', 'July 2026']);
        // The 5 zero-expense months render as an explicit $0.00, not blank.
        $response->assertSee('$0.00');
        $response->assertSee('$500.00');
    }

    public function test_an_expense_dated_on_the_last_day_of_the_window_counts_toward_its_month(): void
    {
        // Regression test: SQLite persists the `date`-cast column as a full
        // datetime string (e.g. "2026-07-31 00:00:00"), which sorts *after*
        // a bare "2026-07-31" upper bound in a string comparison - a
        // whereBetween() with plain date-string bounds would silently drop
        // this expense from the 6-month window entirely.
        $this->travelTo(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();
        Expense::factory()->for($user)->create(['amount' => 250, 'date' => '2026-07-31']);

        $response = $this->actingAs($user)->get('/trends');

        $response->assertOk();
        $response->assertSee('250.00');
    }

    public function test_a_user_only_sees_their_own_data(): void
    {
        $this->travelTo(Carbon::create(2026, 7, 15));

        $user = User::factory()->create();
        $other = User::factory()->create();

        Expense::factory()->for($user)->create(['amount' => 100, 'date' => '2026-07-05']);
        Expense::factory()->for($other)->create(['amount' => 99999, 'date' => '2026-07-05']);

        $response = $this->actingAs($user)->get('/trends');

        $response->assertOk();
        $response->assertSee('100.00');
        $response->assertDontSee('99,999.00');
    }
}
