<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import CategoryChip from '@/components/CategoryChip.vue';
import InputError from '@/components/InputError.vue';
import TransactionMovementForm from '@/components/TransactionMovementForm.vue';
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
    type: 'income' | 'expense' | 'transfer' | string;
    amount: number;
    occurred_on: string;
    description: string | null;
    account: AccountOption | null;
    counterparty_account: AccountOption | null;
    category: CategoryOption | null;
};

type Filters = {
    month: string;
    account_id: number | null;
    category_id: number | null;
    type: string | null;
};

const { transactions, filters, accounts, incomeCategories, expenseCategories } =
    defineProps<{
        transactions: TransactionRow[];
        filters: Filters;
        accounts: AccountOption[];
        incomeCategories: CategoryOption[];
        expenseCategories: CategoryOption[];
    }>();

const page = usePage();
const currency = computed(() => {
    const workspace = page.props.workspace as { currency?: string } | null;

    return workspace?.currency ?? '';
});

const today = computed(() => localDateString());

const allMonths = computed(() => filters.month === 'all');

const allCategories = computed(() => [
    ...expenseCategories,
    ...incomeCategories,
]);

function queryString(
    params: Record<string, string | number | null | undefined>,
): string {
    const search = new URLSearchParams();

    for (const [key, value] of Object.entries(params)) {
        if (value === null || value === undefined || value === '') {
            continue;
        }

        search.set(key, String(value));
    }

    const encoded = search.toString();

    return encoded === '' ? '' : `?${encoded}`;
}

const csvHref = computed(
    () => `/export/csv${queryString({ month: filters.month })}`,
);

const allMonthsHref = computed(
    () =>
        `/transactions${queryString({
            month: 'all',
            account_id: filters.account_id,
            category_id: filters.category_id,
            type: filters.type,
        })}`,
);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Операции',
                href: '/transactions',
            },
        ],
    },
});

const selectClass =
    'border-input bg-background ring-offset-background focus-visible:ring-ring h-9 rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none';

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

function signedAmount(row: TransactionRow): string {
    const formatted = formatAmount(row.amount);

    if (row.type === 'expense') {
        return `−${formatted}`;
    }

    if (row.type === 'income') {
        return `+${formatted}`;
    }

    return formatted;
}

function amountClass(type: TransactionRow['type']): string {
    if (type === 'income') {
        return 'text-income';
    }

    if (type === 'expense') {
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

function isLedgerLocked(type: TransactionRow['type']): boolean {
    return (
        type === 'adjustment' ||
        type === 'debt_repayment' ||
        type === 'goal_contribution' ||
        type === 'goal_withdrawal'
    );
}

function formatDate(iso: string): string {
    const [year, month, day] = iso.split('-');

    if (!year || !month || !day) {
        return iso;
    }

    return `${day}.${month}.${year}`;
}

function accountOptionsFor(row: TransactionRow): AccountOption[] {
    const options = [...accounts];

    for (const extra of [row.account, row.counterparty_account]) {
        if (extra && !options.some((account) => account.id === extra.id)) {
            options.push(extra);
        }
    }

    return options;
}

function categoriesFor(row: TransactionRow): CategoryOption[] {
    if (row.type === 'income') {
        return incomeCategories;
    }

    if (row.type === 'expense') {
        return expenseCategories;
    }

    return [];
}

function confirmDeletion(event: Event): void {
    if (!confirm('Удалить эту операцию?')) {
        event.preventDefault();
    }
}
</script>

<template>
    <Head title="Операции" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4 lg:flex-row">
        <div class="order-2 flex min-w-0 flex-1 flex-col gap-6 lg:order-1">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="space-y-1">
                    <h1 class="font-display text-2xl">Операции</h1>
                    <p class="text-muted-foreground text-sm">
                        Доходы, расходы и переводы за выбранный месяц. Суммы в
                        журнале — целые копейки.
                    </p>
                </div>
                <Button as="a" variant="outline" :href="csvHref">
                    Скачать CSV
                </Button>
            </div>

            <Form
                method="get"
                action="/transactions"
                class="bg-card flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:flex-wrap sm:items-end"
            >
                <div class="grid min-w-40 flex-1 gap-2">
                    <Label for="filter-month">Месяц</Label>
                    <Input
                        id="filter-month"
                        name="month"
                        type="month"
                        :default-value="allMonths ? '' : filters.month"
                        :required="!allMonths"
                    />
                </div>
                <div class="grid min-w-40 flex-1 gap-2">
                    <Label for="filter-account">Счёт</Label>
                    <select
                        id="filter-account"
                        name="account_id"
                        :class="selectClass"
                    >
                        <option value="">Все счета</option>
                        <option
                            v-for="account in accounts"
                            :key="account.id"
                            :value="account.id"
                            :selected="filters.account_id === account.id"
                        >
                            {{ account.name }}
                        </option>
                    </select>
                </div>
                <div class="grid min-w-40 flex-1 gap-2">
                    <Label for="filter-category">Категория</Label>
                    <select
                        id="filter-category"
                        name="category_id"
                        :class="selectClass"
                    >
                        <option value="">Все категории</option>
                        <option
                            v-for="category in allCategories"
                            :key="category.id"
                            :value="category.id"
                            :selected="filters.category_id === category.id"
                        >
                            {{ category.emoji }} {{ category.name }}
                        </option>
                    </select>
                </div>
                <div class="grid min-w-36 gap-2">
                    <Label for="filter-type">Тип</Label>
                    <select id="filter-type" name="type" :class="selectClass">
                        <option value="" :selected="!filters.type">
                            Все типы
                        </option>
                        <option
                            value="income"
                            :selected="filters.type === 'income'"
                        >
                            Доход
                        </option>
                        <option
                            value="expense"
                            :selected="filters.type === 'expense'"
                        >
                            Расход
                        </option>
                        <option
                            value="transfer"
                            :selected="filters.type === 'transfer'"
                        >
                            Перевод
                        </option>
                    </select>
                </div>
                <Button type="submit" variant="outline">Показать</Button>
                <Button as="a" variant="ghost" :href="allMonthsHref">
                    Все месяцы
                </Button>
            </Form>

            <p
                v-if="transactions.length === 0"
                class="text-muted-foreground text-sm"
            >
                {{
                    allMonths
                        ? 'Пока нет операций.'
                        : 'Пока нет операций за этот месяц.'
                }}
            </p>

            <section
                v-else
                class="bg-card flex flex-col gap-2 rounded-xl border p-4"
            >
                <div
                    v-for="row in transactions"
                    :key="row.id"
                    class="flex flex-col gap-3 rounded-lg border p-3"
                >
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
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
                            :class="amountClass(row.type)"
                        >
                            {{ signedAmount(row) }}
                        </p>
                    </div>

                    <Form
                        v-if="!isLedgerLocked(row.type)"
                        method="patch"
                        :action="`/transactions/${row.id}`"
                        v-slot="{ processing, errors }"
                        class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end"
                    >
                        <div class="grid min-w-32 flex-1 gap-2">
                            <Label :for="`edit-account-${row.id}`">
                                {{
                                    row.type === 'transfer'
                                        ? 'Со счёта'
                                        : 'Счёт'
                                }}
                            </Label>
                            <select
                                :id="`edit-account-${row.id}`"
                                name="account_id"
                                required
                                :class="selectClass"
                            >
                                <option
                                    v-for="account in accountOptionsFor(row)"
                                    :key="account.id"
                                    :value="account.id"
                                    :selected="row.account?.id === account.id"
                                >
                                    {{ account.name }}
                                </option>
                            </select>
                            <InputError :message="errors.account_id" />
                            <InputError :message="errors.account" />
                        </div>
                        <div
                            v-if="row.type === 'transfer'"
                            class="grid min-w-32 flex-1 gap-2"
                        >
                            <Label :for="`edit-counterparty-${row.id}`">
                                На счёт
                            </Label>
                            <select
                                :id="`edit-counterparty-${row.id}`"
                                name="counterparty_account_id"
                                required
                                :class="selectClass"
                            >
                                <option
                                    v-for="account in accountOptionsFor(row)"
                                    :key="account.id"
                                    :value="account.id"
                                    :selected="
                                        row.counterparty_account?.id ===
                                        account.id
                                    "
                                >
                                    {{ account.name }}
                                </option>
                            </select>
                            <InputError
                                :message="errors.counterparty_account_id"
                            />
                        </div>
                        <div
                            v-else-if="
                                row.type === 'income' || row.type === 'expense'
                            "
                            class="grid min-w-32 flex-1 gap-2"
                        >
                            <Label :for="`edit-category-${row.id}`">
                                Категория
                            </Label>
                            <select
                                :id="`edit-category-${row.id}`"
                                name="category_id"
                                required
                                :class="selectClass"
                            >
                                <option
                                    v-for="category in categoriesFor(row)"
                                    :key="category.id"
                                    :value="category.id"
                                    :selected="row.category?.id === category.id"
                                >
                                    {{ category.emoji }} {{ category.name }}
                                </option>
                            </select>
                            <InputError :message="errors.category_id" />
                        </div>
                        <div class="grid min-w-24 gap-2">
                            <Label :for="`edit-amount-${row.id}`">Сумма</Label>
                            <Input
                                :id="`edit-amount-${row.id}`"
                                name="amount"
                                type="text"
                                inputmode="decimal"
                                required
                                :default-value="toDecimal(row.amount)"
                            />
                            <InputError :message="errors.amount" />
                        </div>
                        <div class="grid min-w-36 gap-2">
                            <Label :for="`edit-date-${row.id}`">Дата</Label>
                            <Input
                                :id="`edit-date-${row.id}`"
                                name="occurred_on"
                                type="date"
                                required
                                :default-value="row.occurred_on"
                            />
                            <InputError :message="errors.occurred_on" />
                        </div>
                        <div class="grid min-w-40 flex-1 gap-2">
                            <Label :for="`edit-description-${row.id}`">
                                Описание
                            </Label>
                            <Input
                                :id="`edit-description-${row.id}`"
                                name="description"
                                type="text"
                                maxlength="255"
                                :default-value="row.description ?? ''"
                            />
                            <InputError :message="errors.description" />
                        </div>
                        <Button type="submit" size="sm" :disabled="processing">
                            <Spinner v-if="processing" />
                            Сохранить
                        </Button>
                    </Form>
                    <Form
                        v-if="!isLedgerLocked(row.type)"
                        method="delete"
                        :action="`/transactions/${row.id}`"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="ghost"
                            size="sm"
                            :disabled="processing"
                            @click="confirmDeletion"
                        >
                            Удалить
                        </Button>
                    </Form>
                </div>
            </section>
        </div>

        <div class="order-1 flex w-full flex-col gap-6 lg:order-2 lg:max-w-md">
            <TransactionMovementForm
                variant="income"
                :accounts="accounts"
                :categories="incomeCategories"
                :today="today"
            />

            <Form
                method="post"
                action="/categories"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-3 rounded-xl border border-dashed p-4"
            >
                <div class="space-y-1">
                    <h3 class="text-sm font-medium">
                        Быстрая категория дохода
                    </h3>
                    <p class="text-muted-foreground text-xs">
                        Создаётся тем же действием, что на странице категорий.
                    </p>
                </div>
                <input type="hidden" name="kind" value="income" />
                <div class="grid grid-cols-[3rem_minmax(0,1fr)] gap-2">
                    <Input
                        name="emoji"
                        type="text"
                        required
                        maxlength="16"
                        placeholder="💼"
                    />
                    <Input
                        name="name"
                        type="text"
                        required
                        maxlength="80"
                        placeholder="Название"
                    />
                </div>
                <Input
                    name="color"
                    type="text"
                    required
                    maxlength="20"
                    default-value="#00CC4B"
                />
                <InputError :message="errors.name" />
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Добавить категорию
                </Button>
            </Form>

            <TransactionMovementForm
                variant="expense"
                :accounts="accounts"
                :categories="expenseCategories"
                :today="today"
            />

            <Form
                method="post"
                action="/categories"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-3 rounded-xl border border-dashed p-4"
            >
                <div class="space-y-1">
                    <h3 class="text-sm font-medium">
                        Быстрая категория расхода
                    </h3>
                    <p class="text-muted-foreground text-xs">
                        Создаётся тем же действием, что на странице категорий.
                    </p>
                </div>
                <input type="hidden" name="kind" value="expense" />
                <div class="grid grid-cols-[3rem_minmax(0,1fr)] gap-2">
                    <Input
                        name="emoji"
                        type="text"
                        required
                        maxlength="16"
                        placeholder="🥑"
                    />
                    <Input
                        name="name"
                        type="text"
                        required
                        maxlength="80"
                        placeholder="Название"
                    />
                </div>
                <Input
                    name="color"
                    type="text"
                    required
                    maxlength="20"
                    default-value="#FF4433"
                />
                <InputError :message="errors.name" />
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Добавить категорию
                </Button>
            </Form>

            <TransactionMovementForm
                variant="transfer"
                :accounts="accounts"
                :today="today"
            />
        </div>
    </div>
</template>
