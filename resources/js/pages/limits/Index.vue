<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type LimitRow = {
    id: number;
    category_id: number;
    category_name: string;
    category_emoji: string;
    category_color: string;
    amount: number;
    spent: number;
    exceeded: boolean;
};

type CategoryOption = {
    id: number;
    name: string;
    emoji: string;
};

const { limits, categories, year, month } = defineProps<{
    limits: LimitRow[];
    categories: CategoryOption[];
    year: number;
    month: number;
}>();

const page = usePage();
const currency = computed(() => {
    const workspace = page.props.workspace as { currency?: string } | null;

    return workspace?.currency ?? '';
});

const monthLabel = computed(() => {
    const names = [
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

    return `${names[month - 1] ?? ''} ${year}`;
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Лимиты',
                href: '/limits',
            },
        ],
    },
});

const selectClass =
    'border-input bg-background ring-offset-background focus-visible:ring-ring h-9 rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none';

function formatAmount(minor: number): string {
    const negative = minor < 0;
    const abs = Math.abs(minor);
    const whole = Math.floor(abs / 100);
    const cents = String(abs % 100).padStart(2, '0');

    return `${negative ? '−' : ''}${whole}.${cents} ${currency.value}`;
}

function formatMinorInput(minor: number): string {
    const negative = minor < 0;
    const abs = Math.abs(minor);
    const whole = Math.floor(abs / 100);
    const cents = String(abs % 100).padStart(2, '0');

    return `${negative ? '-' : ''}${whole}.${cents}`;
}

function spentPercent(limit: LimitRow): number {
    if (limit.amount <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((limit.spent / limit.amount) * 100));
}
</script>

<template>
    <Head title="Лимиты" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4 lg:flex-row">
        <div class="order-2 flex min-w-0 flex-1 flex-col gap-6 lg:order-1">
            <div class="space-y-1">
                <h1 class="font-display text-2xl">Лимиты</h1>
                <p class="text-muted-foreground text-sm">
                    Потолок расходов по категории на календарный месяц ({{
                        monthLabel
                    }}). Сверх лимита расход всё равно можно сохранить — покажем
                    предупреждение.
                </p>
            </div>

            <p v-if="limits.length === 0" class="text-muted-foreground text-sm">
                Пока нет лимитов. Выберите категорию расходов и сумму на месяц.
            </p>

            <section
                v-for="limit in limits"
                :key="limit.id"
                class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            >
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0 space-y-1">
                        <h2 class="flex items-center gap-2 font-medium">
                            <span
                                class="inline-flex size-8 items-center justify-center rounded-full text-base"
                                :style="{
                                    backgroundColor: `${limit.category_color}33`,
                                }"
                            >
                                {{ limit.category_emoji }}
                            </span>
                            {{ limit.category_name }}
                        </h2>
                        <p class="text-muted-foreground text-sm">
                            {{ formatAmount(limit.spent) }} из
                            {{ formatAmount(limit.amount) }}
                        </p>
                    </div>
                    <p class="font-display text-xl tabular-nums">
                        {{ spentPercent(limit) }}%
                    </p>
                </div>

                <div class="bg-muted h-2 overflow-hidden rounded-full">
                    <div
                        class="h-full rounded-full"
                        :class="
                            limit.exceeded ? 'bg-[#FECE4C]' : 'bg-[#1C6CFF]'
                        "
                        :style="{ width: `${spentPercent(limit)}%` }"
                    />
                </div>

                <p
                    v-if="limit.exceeded"
                    class="rounded-lg bg-[#FECE4C]/20 px-3 py-2 text-sm font-medium text-[#FECE4C]"
                >
                    Лимит превышен: потрачено
                    {{ formatAmount(limit.spent) }} при лимите
                    {{ formatAmount(limit.amount) }}.
                </p>

                <Form
                    method="patch"
                    :action="`/limits/${limit.id}`"
                    v-slot="{ processing, errors }"
                    class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end"
                >
                    <div class="grid gap-2 sm:w-36">
                        <Label :for="`amount-${limit.id}`">Лимит</Label>
                        <Input
                            :id="`amount-${limit.id}`"
                            name="amount"
                            type="text"
                            inputmode="decimal"
                            required
                            :default-value="formatMinorInput(limit.amount)"
                        />
                        <InputError :message="errors.amount" />
                    </div>
                    <Button type="submit" size="sm" :disabled="processing">
                        <Spinner v-if="processing" />
                        Сохранить
                    </Button>
                </Form>

                <Form
                    method="delete"
                    :action="`/limits/${limit.id}`"
                    v-slot="{ processing, errors }"
                >
                    <Button
                        type="submit"
                        variant="ghost"
                        size="sm"
                        :disabled="processing"
                    >
                        Удалить
                    </Button>
                    <InputError :message="errors.limit" />
                </Form>
            </section>
        </div>

        <div class="order-1 flex w-full flex-col gap-6 lg:order-2 lg:max-w-md">
            <Form
                method="post"
                action="/limits"
                v-slot="{ errors, processing }"
                class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            >
                <div class="space-y-1">
                    <h2 class="font-medium">Новый лимит</h2>
                    <p class="text-muted-foreground text-sm">
                        Только категории расходов. Если лимит уже есть — сумма
                        обновится.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="category_id">Категория</Label>
                    <select
                        id="category_id"
                        name="category_id"
                        required
                        :class="selectClass"
                    >
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.emoji }} {{ category.name }}
                        </option>
                    </select>
                    <InputError :message="errors.category_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="amount">Сумма на месяц</Label>
                    <Input
                        id="amount"
                        name="amount"
                        type="text"
                        inputmode="decimal"
                        required
                        placeholder="10.00"
                    />
                    <InputError :message="errors.amount" />
                </div>

                <Button
                    type="submit"
                    :disabled="processing || categories.length === 0"
                >
                    <Spinner v-if="processing" />
                    Сохранить лимит
                </Button>
            </Form>
        </div>
    </div>
</template>
