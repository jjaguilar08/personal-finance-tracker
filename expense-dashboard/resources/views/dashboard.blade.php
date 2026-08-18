<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-[#f9f4ed] leading-tight">
            {{ __('Dashboard') }} — {{ $periodLabel }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-[32px] bg-[#201e1d] p-5 sm:p-8 space-y-6">

                @if (session('status'))
                    <div class="rounded-full border border-[#7a8a5e]/30 bg-[#f0fae1] px-4 py-3 text-sm text-[#56633f]">
                        {{ session('status') }}
                    </div>
                @endif

                {{-- Period switcher --}}
                <div class="flex items-center gap-3">
                    <a href="{{ route('dashboard', ['period_start' => $previousPeriodStart->toDateString()]) }}"
                       class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#474238] text-[#f9f4ed]/70 transition hover:bg-[#544e42] hover:text-[#f6a06b] focus:outline-none focus:ring-2 focus:ring-[#f6a06b]/40"
                       aria-label="Previous period">
                        <svg class="h-4 w-4" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>

                    <select onchange="if (this.value) window.location.href = this.value"
                        aria-label="Select period"
                        class="min-w-0 flex-1 rounded-full border border-[#f9f4ed]/15 bg-[#474238] px-4 py-2 text-sm text-[#f9f4ed] focus:border-[#f6a06b] focus:outline-none focus:ring-2 focus:ring-[#f6a06b]/30 sm:flex-none">
                        @foreach ($periodOptions as $option)
                            <option value="{{ route('dashboard', ['period_start' => $option['start']->toDateString()]) }}" @selected($option['start']->isSameDay($periodStart))>{{ $option['label'] }}</option>
                        @endforeach
                    </select>

                    <a href="{{ route('dashboard', ['period_start' => $nextPeriodStart->toDateString()]) }}"
                       class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#474238] text-[#f9f4ed]/70 transition hover:bg-[#544e42] hover:text-[#f6a06b] focus:outline-none focus:ring-2 focus:ring-[#f6a06b]/40"
                       aria-label="Next period">
                        <svg class="h-4 w-4" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 5l7 7-7 7" />
                        </svg>
                    </a>

                    @unless ($isCurrentPeriod)
                        <a href="{{ route('dashboard') }}" class="hidden shrink-0 text-sm font-semibold text-[#f6a06b] hover:text-[#ffc6a5] hover:underline sm:inline">
                            Back to current period
                        </a>
                    @endunless
                </div>

                {{-- Summary cards --}}
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <div class="rounded-[28px] bg-[#474238] p-6 shadow-[0_1px_2px_rgba(46,43,37,0.14)]">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-[#f6a06b]">Total Spent This Period</p>
                        <p class="mt-1 font-['Caprasimo'] text-[34px] leading-tight text-[#ffc6a5]">{{ money($totalSpent) }}</p>
                    </div>

                    <div class="rounded-[28px] bg-[#474238] p-6 shadow-[0_1px_2px_rgba(46,43,37,0.14)]">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-[#aebf92]">Expected Income</p>
                        @if ($incomeExpectation)
                            <p class="mt-1 font-['Caprasimo'] text-[34px] leading-tight text-[#ccdbb2]">{{ money($incomeExpectation->expected_amount) }}</p>
                        @else
                            <p class="mt-2 text-sm text-[#f9f4ed]/60">
                                You haven't set expected income for this period yet.
                            </p>
                            <a href="{{ route('income-expectations.create') }}" class="mt-2 inline-block text-sm font-semibold text-[#f6a06b] hover:text-[#ffc6a5] hover:underline">
                                Set expected income &rarr;
                            </a>
                        @endif
                    </div>

                    <div class="rounded-[28px] bg-[#474238] p-6 shadow-[0_1px_2px_rgba(46,43,37,0.14)]">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-[#f9f4ed]/60">Savings Goal</p>
                        @if ($savingsGoal)
                            <p class="mt-1 font-['Caprasimo'] text-[34px] leading-tight text-[#f9f4ed]">{{ money($savingsGoal->target_amount) }}</p>

                            @if ($savingsProgress !== null)
                                <div class="mt-3">
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-[#645c50]">
                                        <div class="h-2 rounded-full {{ $actualSavings < 0 ? 'bg-[#f6a06b]' : 'bg-[#aebf92]' }}" style="width: {{ min(100, $savingsProgress) }}%"></div>
                                    </div>
                                    <p class="mt-2 text-xs text-[#f9f4ed]/70">
                                        {{ $savingsProgress }}% of goal &middot;
                                        <span class="font-semibold {{ $actualSavings < 0 ? 'text-[#ffc6a5]' : 'text-[#ccdbb2]' }}">
                                            {{ $actualSavings < 0 ? '-' : '' }}{{ money(abs($actualSavings)) }}
                                            {{ $actualSavings < 0 ? 'over budget' : 'saved so far' }}
                                        </span>
                                    </p>
                                </div>
                            @else
                                <p class="mt-2 text-sm text-[#f9f4ed]/60">
                                    Set your expected income to see progress toward this goal.
                                </p>
                                <a href="{{ route('income-expectations.create') }}" class="mt-1 inline-block text-sm font-semibold text-[#f6a06b] hover:text-[#ffc6a5] hover:underline">
                                    Set expected income &rarr;
                                </a>
                            @endif
                        @else
                            <p class="mt-2 text-sm text-[#f9f4ed]/60">
                                You haven't set a savings goal for this period yet.
                            </p>
                            <a href="{{ route('savings-goals.create') }}" class="mt-2 inline-block text-sm font-semibold text-[#f6a06b] hover:text-[#ffc6a5] hover:underline">
                                Set a savings goal &rarr;
                            </a>
                        @endif
                    </div>
                </div>

                {{-- AI Overview --}}
                <div class="rounded-[28px] bg-[#474238] p-6 shadow-[0_1px_2px_rgba(46,43,37,0.14)]">
                    <div class="mb-3 flex items-center justify-between gap-4">
                        <p class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.1em] text-[#f9f4ed]/60">
                            <svg class="h-3.5 w-3.5 text-[#f6a06b]" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />
                            </svg>
                            AI Overview
                        </p>
                        <form method="POST" action="{{ route('ai-overview.store') }}">
                            @csrf
                            <input type="hidden" name="period_start" value="{{ $periodStart->toDateString() }}">
                            <button type="submit"
                                class="inline-flex items-center rounded-full bg-[#f6a06b] px-4 py-2 font-['Caprasimo'] text-xs uppercase tracking-widest text-[#2e2b25] transition hover:bg-[#ffc6a5] active:bg-[#d67f48] focus:outline-none focus:ring-2 focus:ring-[#f6a06b] focus:ring-offset-2 focus:ring-offset-[#474238]">
                                {{ $periodSummary ? 'Regenerate' : 'Generate AI Overview' }}
                            </button>
                        </form>
                    </div>

                    @if (session('aiOverviewError'))
                        <div class="mb-3 rounded-full border border-red-400/30 bg-red-950/60 px-4 py-3 text-sm text-red-200">
                            {{ session('aiOverviewError') }}
                        </div>
                    @endif

                    @if ($periodSummary)
                        {{-- sm and up: always expanded, no toggle needed. --}}
                        <p class="hidden text-sm leading-relaxed text-[#f9f4ed]/80 sm:block">{{ $periodSummary->summary }}</p>

                        {{-- Mobile: collapsed behind a toggle so the card doesn't force
                             a long scroll past a paragraph on every dashboard visit. --}}
                        <div class="sm:hidden" x-data="{ showOverview: false }">
                            <button type="button" @click="showOverview = ! showOverview"
                                class="text-sm font-semibold text-[#f6a06b] hover:text-[#ffc6a5] hover:underline">
                                <span x-show="! showOverview">Show overview</span>
                                <span x-show="showOverview" style="display: none;">Hide overview</span>
                            </button>
                            <p x-show="showOverview" style="display: none;" class="mt-2 text-sm leading-relaxed text-[#f9f4ed]/80">{{ $periodSummary->summary }}</p>
                        </div>
                    @else
                        <p class="text-sm text-[#f9f4ed]/60">Generate a short AI-written summary of your spending this period.</p>
                    @endif
                </div>

                {{-- Chart + quick add --}}
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <div class="rounded-[28px] bg-[#474238] p-6 shadow-[0_1px_2px_rgba(46,43,37,0.14)]">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-[#f9f4ed]/60">Spending by Category</p>
                        @if ($categoryTotals->isNotEmpty())
                            <canvas id="categoryChart" class="mt-4"></canvas>
                        @else
                            <p class="mt-3 text-sm text-[#f9f4ed]/45">No expenses logged this period yet.</p>
                        @endif
                    </div>

                    <div id="quick-add-expense" class="rounded-[28px] bg-[#474238] p-6 shadow-[0_1px_2px_rgba(46,43,37,0.14)]">
                        <p class="mb-4 text-[10px] font-semibold uppercase tracking-[0.1em] text-[#f9f4ed]/60">Quick Add Expense</p>

                        <form method="POST" action="{{ route('expenses.store') }}" class="space-y-4">
                            @csrf

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="amount" class="mb-1 block text-xs text-[#f9f4ed]/70">Amount</label>
                                    <input id="amount" name="amount" type="number" step="0.01" min="0.01"
                                        value="{{ old('amount') }}" required
                                        class="block w-full rounded-full border border-[#f9f4ed]/15 bg-[#2e2b25] px-4 py-2.5 text-sm text-[#f9f4ed] focus:border-[#f6a06b] focus:outline-none focus:ring-2 focus:ring-[#f6a06b]/30">
                                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                                </div>

                                <div>
                                    <label for="date" class="mb-1 block text-xs text-[#f9f4ed]/70">Date</label>
                                    <input id="date" name="date" type="date" value="{{ old('date', now()->toDateString()) }}" required
                                        class="block w-full rounded-full border border-[#f9f4ed]/15 bg-[#2e2b25] px-4 py-2.5 text-sm text-[#f9f4ed] focus:border-[#f6a06b] focus:outline-none focus:ring-2 focus:ring-[#f6a06b]/30">
                                    <x-input-error :messages="$errors->get('date')" class="mt-2" />
                                </div>
                            </div>

                            <div>
                                <label for="category" class="mb-1 block text-xs text-[#f9f4ed]/70">Category</label>
                                <select id="category" name="category" required
                                    class="block w-full rounded-full border border-[#f9f4ed]/15 bg-[#2e2b25] px-4 py-2.5 text-sm text-[#f9f4ed] focus:border-[#f6a06b] focus:outline-none focus:ring-2 focus:ring-[#f6a06b]/30">
                                    <option value="">Select a category</option>
                                    @foreach ($categories as $option)
                                        <option value="{{ $option }}" @selected(old('category') === $option)>
                                            {{ ucfirst($option) }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('category')" class="mt-2" />
                            </div>

                            <div>
                                <label for="notes" class="mb-1 block text-xs text-[#f9f4ed]/70">Notes (optional)</label>
                                <input id="notes" name="notes" type="text" value="{{ old('notes') }}"
                                    class="block w-full rounded-full border border-[#f9f4ed]/15 bg-[#2e2b25] px-4 py-2.5 text-sm text-[#f9f4ed] focus:border-[#f6a06b] focus:outline-none focus:ring-2 focus:ring-[#f6a06b]/30">
                                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                            </div>

                            <div class="flex items-center justify-between pt-1">
                                <a href="{{ route('expenses.index') }}" class="text-sm text-[#f9f4ed]/60 hover:text-[#f6a06b] hover:underline">View all expenses &rarr;</a>
                                <button type="submit"
                                    class="inline-flex items-center rounded-full bg-[#f6a06b] px-5 py-2.5 font-['Caprasimo'] text-xs uppercase tracking-widest text-[#2e2b25] transition hover:bg-[#ffc6a5] active:bg-[#d67f48] focus:outline-none focus:ring-2 focus:ring-[#f6a06b] focus:ring-offset-2 focus:ring-offset-[#474238]">
                                    Add Expense
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Recent expenses --}}
                <div class="rounded-[28px] bg-[#474238] p-6 shadow-[0_1px_2px_rgba(46,43,37,0.14)]">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.1em] text-[#f9f4ed]/60">Recent Expenses</p>
                        <a href="{{ route('expenses.index') }}" class="text-sm font-semibold text-[#f6a06b] hover:text-[#ffc6a5] hover:underline">View all &rarr;</a>
                    </div>

                    @if ($recentExpenses->isEmpty())
                        <p class="text-sm text-[#f9f4ed]/60">
                            You haven't logged any expenses yet.
                        </p>
                        <a href="{{ route('expenses.create') }}" class="mt-2 inline-block text-sm font-semibold text-[#f6a06b] hover:text-[#ffc6a5] hover:underline">
                            Add an expense &rarr;
                        </a>
                    @else
                        {{-- Mobile: stacked cards --}}
                        <div class="space-y-3 sm:hidden">
                            @foreach ($recentExpenses as $expense)
                                <div class="rounded-2xl border border-[#f9f4ed]/10 bg-[#2e2b25] p-4 text-sm">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium text-[#f9f4ed]">{{ $expense->date->format('Y-m-d') }}</span>
                                        <span class="font-semibold text-[#ffc6a5]">{{ money($expense->amount) }}</span>
                                    </div>
                                    <dl class="mt-2 space-y-1">
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-[#f9f4ed]/50">Category</dt>
                                            <dd class="capitalize text-[#f9f4ed]/70">{{ $expense->category }}</dd>
                                        </div>
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-[#f9f4ed]/50">Notes</dt>
                                            <dd class="text-right text-[#f9f4ed]/70">{{ $expense->notes }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            @endforeach
                        </div>

                        {{-- sm and up: table --}}
                        <div class="hidden overflow-hidden rounded-2xl border border-[#f9f4ed]/10 sm:block">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-[#f9f4ed]/50">Date</th>
                                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-[#f9f4ed]/50">Category</th>
                                            <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wider text-[#f9f4ed]/50">Amount</th>
                                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-[#f9f4ed]/50">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#f9f4ed]/8">
                                        @foreach ($recentExpenses as $expense)
                                            <tr class="hover:bg-[#f9f4ed]/[0.06]">
                                                <td class="whitespace-nowrap px-4 py-3 text-[#f9f4ed]/70">{{ $expense->date->format('Y-m-d') }}</td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex items-center rounded-full bg-[#402310] px-2.5 py-0.5 text-xs capitalize text-[#ffc6a5]">
                                                        {{ $expense->category }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-right font-semibold text-[#ffc6a5]">{{ money($expense->amount) }}</td>
                                                <td class="px-4 py-3 text-[#f9f4ed]/60">{{ $expense->notes }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Mobile-only quick-access FAB: jumps to the Quick Add Expense form
         already on the page instead of duplicating it in a modal - desktop
         shows that form inline without scrolling, so it has no FAB. --}}
    <a href="#quick-add-expense"
       class="fixed bottom-6 right-6 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-[#f6a06b] text-[#2e2b25] shadow-lg hover:bg-[#ffc6a5] focus:outline-none focus:ring-2 focus:ring-[#f6a06b] focus:ring-offset-2 sm:hidden"
       aria-label="Jump to quick add expense form">
        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" stroke-width="2.75">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
        </svg>
    </a>

    @if ($categoryTotals->isNotEmpty())
        @php
            $chartPalette = ['#f6a06b', '#aebf92', '#d67f48', '#8fa073', '#ffc6a5', '#ccdbb2'];
            $chartBarColors = $categoryTotals->keys()->values()
                ->map(fn ($category, $index) => $chartPalette[$index % count($chartPalette)]);
        @endphp
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
        <script>
            new Chart(document.getElementById('categoryChart'), {
                type: 'bar',
                data: {
                    labels: @json($categoryTotals->keys()->map(fn ($category) => ucfirst($category))),
                    datasets: [{
                        label: 'Spend by Category',
                        data: @json($categoryTotals->values()),
                        backgroundColor: @json($chartBarColors),
                        borderRadius: 8,
                        maxBarThickness: 36,
                    }],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(249,244,237,0.12)' } },
                        x: { grid: { display: false } },
                    },
                },
            });
        </script>
    @endif
</x-app-layout>
