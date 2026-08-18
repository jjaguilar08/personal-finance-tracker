<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiOverviewControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeAnthropicSuccess(string $text): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => $text]],
            ], 200),
        ]);
    }

    public function test_guests_cannot_generate_an_overview(): void
    {
        $this->post(route('ai-overview.store'))->assertRedirect('/login');
    }

    public function test_generating_an_overview_saves_it_and_shows_it_on_the_dashboard(): void
    {
        $this->fakeAnthropicSuccess('This period you spent mostly on food and stayed under budget.');

        $user = User::factory()->create();
        Expense::factory()->for($user)->create(['amount' => 100, 'date' => now()->toDateString()]);

        $response = $this->actingAs($user)->post(route('ai-overview.store'));
        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('period_summaries', [
            'user_id' => $user->id,
            'summary' => 'This period you spent mostly on food and stayed under budget.',
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertSee('This period you spent mostly on food and stayed under budget.');
    }

    public function test_generating_an_overview_for_a_non_current_period_targets_that_period(): void
    {
        // Aug 18: the calendar-month "current" period is August, so a July
        // expense is only reachable through the period switcher - the
        // overview generated for it must be scoped to July, not "now".
        $this->travelTo(Carbon::create(2026, 8, 18));

        $this->fakeAnthropicSuccess('You spent mostly on food in July.');

        $user = User::factory()->create();
        Expense::factory()->for($user)->create(['amount' => 100, 'date' => '2026-07-10']);

        $response = $this->actingAs($user)->post(route('ai-overview.store'), ['period_start' => '2026-07-10']);

        $response->assertRedirect(route('dashboard', ['period_start' => '2026-07-10']));

        $this->assertDatabaseHas('period_summaries', [
            'user_id' => $user->id,
            'summary' => 'You spent mostly on food in July.',
        ]);

        // Viewing July shows the new summary; viewing the current (August)
        // period, which has no expenses or summary of its own, must not.
        $this->actingAs($user)->get('/dashboard?period_start=2026-07-10')
            ->assertSee('You spent mostly on food in July.');
        $this->actingAs($user)->get('/dashboard')
            ->assertDontSee('You spent mostly on food in July.');
    }

    public function test_dashboard_includes_the_mobile_overview_toggle_once_a_summary_exists(): void
    {
        $this->fakeAnthropicSuccess('This period you spent mostly on food.');

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('ai-overview.store'));

        $response = $this->actingAs($user)->get('/dashboard');

        // The Alpine-driven mobile toggle (sm:hidden, collapsed by default)
        // must be present alongside the always-expanded sm:+ paragraph -
        // actual show/hide behavior needs a browser and isn't checked here.
        $response->assertSee('x-data="{ showOverview: false }"', false);
        $response->assertSee('Show overview', false);
        $response->assertSee('Hide overview', false);
        $response->assertSee('showOverview = ! showOverview', false);
    }

    public function test_dashboard_does_not_show_the_mobile_toggle_before_a_summary_exists(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertDontSee('Show overview', false);
        $response->assertDontSee('showOverview', false);
    }

    public function test_dashboard_reads_the_cached_summary_without_calling_the_api_again(): void
    {
        $this->fakeAnthropicSuccess('You spent $500 this period, mostly on food.');

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('ai-overview.store'));
        Http::assertSentCount(1);

        $this->actingAs($user)->get('/dashboard')
            ->assertSee('You spent $500 this period, mostly on food.');
        $this->actingAs($user)->get('/dashboard');

        // Loading the dashboard - even repeatedly - must only ever read the
        // cached period_summaries row, never call the API again.
        Http::assertSentCount(1);
    }

    public function test_regenerating_overwrites_the_cached_summary_and_calls_the_api_again(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['type' => 'text', 'text' => 'First version.']]], 200)
                ->push(['content' => [['type' => 'text', 'text' => 'Second version.']]], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('ai-overview.store'));
        $this->actingAs($user)->get('/dashboard')->assertSee('First version.');

        $this->actingAs($user)->post(route('ai-overview.store'));
        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertSee('Second version.');
        $response->assertDontSee('First version.');
        Http::assertSentCount(2);

        // Regenerating updates the one row in place rather than
        // accumulating a history of past overviews.
        $this->assertDatabaseCount('period_summaries', 1);
    }

    public function test_regeneration_is_rate_limited_to_a_few_per_day(): void
    {
        $this->fakeAnthropicSuccess('Overview text.');

        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->post(route('ai-overview.store'))->assertRedirect(route('dashboard'));
        }

        Http::assertSentCount(5);

        // The 6th attempt in the same day must be blocked before ever
        // reaching the API - the rate limit is a hard ceiling independent
        // of whether a cached summary already exists.
        $response = $this->actingAs($user)->post(route('ai-overview.store'));

        Http::assertSentCount(5);
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('aiOverviewError');
    }

    public function test_a_users_cached_summary_and_rate_limit_are_isolated_from_another_user(): void
    {
        // Two stubbed responses, consumed in call order: $user's generate
        // first, then $other's - Http::fake() merges rather than replacing
        // stubs, so a single sequence (not two separate fakeAnthropicSuccess()
        // calls) is what actually controls per-call responses here.
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['type' => 'text', 'text' => 'User A overview.']]], 200)
                ->push(['content' => [['type' => 'text', 'text' => 'User B overview.']]], 200),
        ]);

        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post(route('ai-overview.store'));

        // $other has no cached summary of their own and must never see $user's.
        $response = $this->actingAs($other)->get('/dashboard');
        $response->assertDontSee('User A overview.');
        $response->assertSee('Generate AI Overview', false);

        // $other's own daily rate limit must be untouched by $user's usage.
        $this->actingAs($other)->post(route('ai-overview.store'))->assertRedirect(route('dashboard'));
        $this->actingAs($other)->get('/dashboard')->assertSee('User B overview.');
    }

    public function test_a_failed_api_call_shows_a_friendly_message_and_does_not_cache_anything(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['type' => 'error'], 500),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('ai-overview.store'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('aiOverviewError');
        $this->assertDatabaseCount('period_summaries', 0);

        $this->actingAs($user)->get('/dashboard')
            ->assertSee('Generate AI Overview', false);
    }

    public function test_a_connection_timeout_shows_the_same_friendly_fallback_message(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('ai-overview.store'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('aiOverviewError');
        $this->assertDatabaseCount('period_summaries', 0);
    }
}
