<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { toPng } from 'html-to-image';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type MonthBar = {
    label: string;
    amount: number;
};

type CategoryRow = {
    id: number;
    name: string;
    emoji: string;
    amount: number;
};

type Stats = {
    income: number;
    expense: number;
    previousIncome: number;
    previousExpense: number;
    expenseByMonth: MonthBar[];
    topExpenseCategories: CategoryRow[];
    forecast: number | null;
};

const { year, month, monthKey, stats } = defineProps<{
    year: number;
    month: number;
    monthKey: string;
    stats: Stats;
}>();

const page = usePage();
const currency = computed(() => {
    const workspace = page.props.workspace as { currency?: string } | null;

    return workspace?.currency ?? '';
});

const monthNames = [
    'январь',
    'февраль',
    'март',
    'апрель',
    'май',
    'июнь',
    'июль',
    'август',
    'сентябрь',
    'октябрь',
    'ноябрь',
    'декабрь',
];

const monthLabel = computed(() => `${monthNames[month - 1] ?? ''} ${year}`);

const barPeak = computed(() =>
    Math.max(...stats.expenseByMonth.map((bar) => bar.amount), 1),
);

const categoryPeak = computed(() =>
    Math.max(...stats.topExpenseCategories.map((row) => row.amount), 1),
);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Статистика',
                href: '/stats',
            },
        ],
    },
});

function formatAmount(minor: number): string {
    const negative = minor < 0;
    const abs = Math.abs(minor);
    const whole = Math.floor(abs / 100);
    const cents = String(abs % 100).padStart(2, '0');

    return `${negative ? '−' : ''}${whole}.${cents} ${currency.value}`;
}

function deltaLabel(current: number, previous: number): string {
    const delta = current - previous;

    if (delta === 0) {
        return 'как в прошлом месяце';
    }

    const verb = delta > 0 ? 'больше' : 'меньше';

    return `${formatAmount(Math.abs(delta))} ${verb}, чем в прошлом`;
}

function barHeight(amount: number): string {
    if (amount <= 0) {
        return '0%';
    }

    return `${Math.max(4, Math.round((amount / barPeak.value) * 100))}%`;
}

function categoryWidth(amount: number): string {
    return `${Math.round((amount / categoryPeak.value) * 100)}%`;
}

function monthTitle(label: string): string {
    const [rawYear, rawMonth] = label.split('-');
    const monthIndex = Number(rawMonth) - 1;

    return `${monthNames[monthIndex] ?? label} ${rawYear ?? ''}`.trim();
}

async function downloadPng(): Promise<void> {
    const node = document.getElementById('stats-report');

    if (!node) {
        return;
    }

    try {
        const dataUrl = await toPng(node, { pixelRatio: 2, cacheBust: true });
        const paddedMonth = String(month).padStart(2, '0');
        const link = document.createElement('a');
        link.download = `monetka-report-${year}-${paddedMonth}.png`;
        link.href = dataUrl;
        link.click();
    } catch (error) {
        console.error(error);
    }
}
</script>

<template>
    <Head title="Статистика" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div class="space-y-1">
                <h1 class="font-display text-2xl">Статистика</h1>
                <p class="text-muted-foreground text-sm">
                    Доходы и расходы за {{ monthLabel }}. Корректировки,
                    переводы, цели и долги в итоги не входят.
                </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <Form method="get" action="/stats" class="flex items-end gap-3">
                    <div class="grid min-w-40 gap-2">
                        <Label for="stats-month">Месяц</Label>
                        <Input
                            id="stats-month"
                            name="month"
                            type="month"
                            :default-value="monthKey"
                            required
                        />
                    </div>
                    <Button type="submit">Показать</Button>
                </Form>
                <Button type="button" variant="outline" @click="downloadPng">
                    Скачать PNG
                </Button>
            </div>
        </div>

        <div id="stats-report" class="flex flex-col gap-8">
            <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <article
                    class="bg-card flex flex-col gap-2 rounded-xl border p-4"
                >
                    <p class="text-muted-foreground text-sm">Доходы</p>
                    <p class="text-income font-display text-4xl tabular-nums">
                        {{ formatAmount(stats.income) }}
                    </p>
                    <p class="text-muted-foreground text-sm">
                        Прошлый месяц:
                        {{ formatAmount(stats.previousIncome) }}.
                        {{ deltaLabel(stats.income, stats.previousIncome) }}
                    </p>
                </article>
                <article
                    class="bg-card flex flex-col gap-2 rounded-xl border p-4"
                >
                    <p class="text-muted-foreground text-sm">Расходы</p>
                    <p class="text-expense font-display text-4xl tabular-nums">
                        {{ formatAmount(stats.expense) }}
                    </p>
                    <p class="text-muted-foreground text-sm">
                        Прошлый месяц:
                        {{ formatAmount(stats.previousExpense) }}.
                        {{ deltaLabel(stats.expense, stats.previousExpense) }}
                    </p>
                </article>
            </section>

            <section class="flex flex-col gap-4">
                <h2 class="font-medium">Динамика расходов за 6 месяцев</h2>
                <div
                    class="bg-card flex h-56 items-stretch gap-2 rounded-xl border p-4"
                    role="img"
                    :aria-label="`Расходы за 6 месяцев, включая ${monthLabel}`"
                >
                    <div
                        v-for="bar in stats.expenseByMonth"
                        :key="bar.label"
                        class="flex min-w-0 flex-1 flex-col items-center gap-2"
                    >
                        <p class="text-muted-foreground text-xs tabular-nums">
                            {{ formatAmount(bar.amount) }}
                        </p>
                        <div
                            class="flex min-h-0 w-full max-w-12 flex-1 items-end"
                        >
                            <div
                                class="bg-expense w-full rounded-t-md"
                                :style="{ height: barHeight(bar.amount) }"
                            />
                        </div>
                        <p class="text-muted-foreground text-center text-xs">
                            {{ monthTitle(bar.label) }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="flex flex-col gap-4">
                <h2 class="font-medium">Топ категорий расходов</h2>
                <p
                    v-if="stats.topExpenseCategories.length === 0"
                    class="text-muted-foreground text-sm"
                >
                    Расходов по категориям в этом месяце нет.
                </p>
                <ul class="flex flex-col gap-3">
                    <li
                        v-for="row in stats.topExpenseCategories"
                        :key="row.id"
                        class="flex flex-col gap-2"
                    >
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="min-w-0 truncate text-sm">
                                <span>{{ row.emoji }}</span>
                                {{ row.name }}
                            </p>
                            <p
                                class="text-muted-foreground shrink-0 text-sm tabular-nums"
                            >
                                {{ formatAmount(row.amount) }}
                            </p>
                        </div>
                        <div class="bg-muted h-px overflow-hidden">
                            <div
                                class="bg-expense h-full"
                                :style="{ width: categoryWidth(row.amount) }"
                            />
                        </div>
                    </li>
                </ul>
            </section>

            <section v-if="stats.forecast !== null" class="flex flex-col gap-2">
                <h2 class="font-medium">Прогноз расходов</h2>
                <article
                    class="bg-card flex flex-col gap-2 rounded-xl border p-4"
                >
                    <p class="font-display text-4xl tabular-nums">
                        {{ formatAmount(stats.forecast) }}
                    </p>
                    <p class="text-muted-foreground text-sm">
                        Уже потрачено {{ formatAmount(stats.expense) }}. Остаток
                        месяца оцениваем по среднесуточным расходам предыдущих
                        полных месяцев — или по текущему темпу, если истории
                        нет.
                    </p>
                </article>
            </section>
        </div>
    </div>
</template>
