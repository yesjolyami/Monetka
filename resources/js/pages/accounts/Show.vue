<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type CategoryOption = {
    id: number;
    name: string;
    emoji: string;
    color: string;
};

type AccountOption = {
    id: number;
    name: string;
};

type TransactionRow = {
    id: number;
    type: 'income' | 'expense' | 'transfer' | 'adjustment' | string;
    amount: number;
    effect: number;
    occurred_on: string;
    description: string | null;
    account: AccountOption | null;
    counterparty_account: AccountOption | null;
    category: CategoryOption | null;
};

const { account, transactions, filters } = defineProps<{
    account: {
        id: number;
        name: string;
        type: 'checking' | 'savings' | string;
        balance: number;
    };
    transactions: TransactionRow[];
    filters: {
        month: string;
    };
}>();

const page = usePage();
const currency = computed(() => {
    const workspace = page.props.workspace as { currency?: string } | null;

    return workspace?.currency ?? '';
});

const today = computed(() => localDateString());

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Счета',
                href: '/accounts',
            },
            {
                title: account.name,
                href: `/accounts/${account.id}`,
            },
        ],
    },
});

function localDateString(date = new Date()): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function formatAmount(minor: number): string {
    const negative = minor < 0;
    const abs = Math.abs(minor);
    const whole = Math.floor(abs / 100);
    const cents = String(abs % 100).padStart(2, '0');

    return `${negative ? '−' : ''}${whole}.${cents} ${currency.value}`;
}

function toDecimal(minor: number): string {
    const negative = minor < 0;
    const abs = Math.abs(minor);
    const whole = Math.floor(abs / 100);
    const cents = String(abs % 100).padStart(2, '0');

    return `${negative ? '-' : ''}${whole}.${cents}`;
}

function signedEffect(effect: number): string {
    const formatted = formatAmount(Math.abs(effect));

    if (effect > 0) {
        return `+${formatted}`;
    }

    if (effect < 0) {
        return `−${formatted}`;
    }

    return formatAmount(0);
}

function effectClass(effect: number): string {
    if (effect > 0) {
        return 'text-income';
    }

    if (effect < 0) {
        return 'text-expense';
    }

    return '';
}

function typeLabel(type: TransactionRow['type']): string {
    if (type === 'income') {
        return 'Доход';
    }

    if (type === 'expense') {
        return 'Расход';
    }

    if (type === 'transfer') {
        return 'Перевод';
    }

    if (type === 'adjustment') {
        return 'корректировка';
    }

    if (type === 'goal_contribution') {
        return 'Пополнение цели';
    }

    if (type === 'goal_withdrawal') {
        return 'Снятие с цели';
    }

    if (type === 'debt_repayment') {
        return 'Возврат долга';
    }

    return type;
}

function formatDate(iso: string): string {
    const [year, month, day] = iso.split('-');

    if (!year || !month || !day) {
        return iso;
    }

    return `${day}.${month}.${year}`;
}
</script>

<template>
    <Head :title="account.name" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4 lg:flex-row">
        <div class="order-2 flex min-w-0 flex-1 flex-col gap-6 lg:order-1">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="space-y-1">
                    <h1 class="font-display text-2xl">{{ account.name }}</h1>
                    <p class="text-muted-foreground text-sm">
                        История операций по счёту. Остаток считается по журналу.
                    </p>
                </div>
                <p class="font-display text-2xl tabular-nums">
                    {{ formatAmount(account.balance) }}
                </p>
            </div>

            <Form
                method="get"
                :action="`/accounts/${account.id}`"
                class="bg-card flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-end"
            >
                <div class="grid min-w-40 flex-1 gap-2">
                    <Label for="filter-month">Месяц</Label>
                    <Input
                        id="filter-month"
                        name="month"
                        type="month"
                        :default-value="filters.month"
                        required
                    />
                </div>
                <Button type="submit" variant="outline">Показать</Button>
            </Form>

            <p
                v-if="transactions.length === 0"
                class="text-muted-foreground text-sm"
            >
                Пока нет операций за этот месяц.
            </p>

            <section
                v-else
                class="bg-card flex flex-col gap-2 rounded-xl border p-4"
            >
                <div
                    v-for="row in transactions"
                    :key="row.id"
                    class="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex min-w-0 flex-col gap-1">
                        <p class="font-medium">
                            {{ row.description || 'Без описания' }}
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-muted-foreground text-xs">
                                {{ typeLabel(row.type) }}
                            </span>
                            <CategoryChip
                                v-if="row.category"
                                :name="row.category.name"
                                :emoji="row.category.emoji"
                                :color="row.category.color"
                            />
                            <span class="text-muted-foreground text-xs">
                                <template v-if="row.type === 'transfer'">
                                    {{ row.account?.name ?? 'Без счёта' }}
                                    →
                                    {{
                                        row.counterparty_account?.name ??
                                        'Без счёта'
                                    }}
                                </template>
                                <template v-else>
                                    {{ row.account?.name ?? 'Без счёта' }}
                                </template>
                            </span>
                            <span class="text-muted-foreground text-xs">
                                {{ formatDate(row.occurred_on) }}
                            </span>
                        </div>
                    </div>
                    <p
                        class="font-display text-lg tabular-nums"
                        :class="effectClass(row.effect)"
                    >
                        {{ signedEffect(row.effect) }}
                    </p>
                </div>
            </section>
        </div>

        <div class="order-1 flex w-full flex-col gap-6 lg:order-2 lg:max-w-md">
            <Form
                method="post"
                :action="`/accounts/${account.id}/balance`"
                v-slot="{ errors, processing }"
                class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            >
                <div class="space-y-1">
                    <h2 class="font-medium">Выставить баланс</h2>
                    <p class="text-muted-foreground text-sm">
                        Разница станет корректировкой на выбранную дату.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="amount">Новый остаток</Label>
                    <Input
                        id="amount"
                        name="amount"
                        type="text"
                        inputmode="decimal"
                        required
                        :default-value="toDecimal(account.balance)"
                    />
                    <InputError :message="errors.amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="occurred_on">Дата</Label>
                    <Input
                        id="occurred_on"
                        name="occurred_on"
                        type="date"
                        required
                        :default-value="today"
                    />
                    <InputError :message="errors.occurred_on" />
                </div>

                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Выставить баланс
                </Button>
            </Form>

            <p class="text-muted-foreground text-sm">
                <Link
                    href="/accounts"
                    class="underline-offset-4 hover:underline"
                >
                    Ко всем счетам
                </Link>
            </p>
        </div>
    </div>
</template>
