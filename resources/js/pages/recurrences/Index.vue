<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type RecurrenceType = 'income' | 'expense' | 'transfer';
type RecurrenceFrequency = 'weekly' | 'monthly' | 'yearly';

type RecurrenceRow = {
    id: number;
    type: RecurrenceType;
    account_id: number;
    counterparty_account_id: number | null;
    category_id: number | null;
    amount: number;
    description: string | null;
    frequency: RecurrenceFrequency;
    next_occurred_on: string;
    is_active: boolean;
    account_name: string;
    counterparty_account_name: string | null;
    category_name: string | null;
};

type AccountOption = {
    id: number;
    name: string;
};

type CategoryOption = {
    id: number;
    name: string;
    emoji: string;
};

const { recurrences, accounts, incomeCategories, expenseCategories } =
    defineProps<{
        recurrences: RecurrenceRow[];
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
const createType = ref<RecurrenceType>('expense');
const editTypes = reactive<Record<number, RecurrenceType>>({});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Шаблоны',
                href: '/recurrences',
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

function formatMinorInput(minor: number): string {
    const negative = minor < 0;
    const abs = Math.abs(minor);
    const whole = Math.floor(abs / 100);
    const cents = String(abs % 100).padStart(2, '0');

    return `${negative ? '-' : ''}${whole}.${cents}`;
}

function typeLabel(type: RecurrenceType): string {
    if (type === 'income') {
        return 'Доход';
    }

    if (type === 'transfer') {
        return 'Перевод';
    }

    return 'Расход';
}

function frequencyLabel(frequency: RecurrenceFrequency): string {
    if (frequency === 'weekly') {
        return 'каждую неделю';
    }

    if (frequency === 'yearly') {
        return 'каждый год';
    }

    return 'каждый месяц';
}

function typeOf(recurrence: RecurrenceRow): RecurrenceType {
    return editTypes[recurrence.id] ?? recurrence.type;
}

function categoriesFor(type: RecurrenceType): CategoryOption[] {
    if (type === 'income') {
        return incomeCategories;
    }

    return expenseCategories;
}
</script>

<template>
    <Head title="Шаблоны" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4 lg:flex-row">
        <div class="order-2 flex min-w-0 flex-1 flex-col gap-6 lg:order-1">
            <div class="space-y-1">
                <h1 class="font-display text-2xl">Шаблоны</h1>
                <p class="text-muted-foreground text-sm">
                    Регулярные доходы, расходы и переводы. Проводите ближайшую
                    дату вручную — расписания нет.
                </p>
            </div>

            <p
                v-if="recurrences.length === 0"
                class="text-muted-foreground text-sm"
            >
                Пока нет шаблонов. Создайте, например, ежемесячную подписку.
            </p>

            <section
                v-for="recurrence in recurrences"
                :key="recurrence.id"
                class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            >
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0 space-y-1">
                        <h2 class="font-medium">
                            {{
                                recurrence.description ||
                                typeLabel(recurrence.type)
                            }}
                        </h2>
                        <p class="text-muted-foreground text-sm">
                            {{ typeLabel(recurrence.type) }} ·
                            {{ formatAmount(recurrence.amount) }} ·
                            {{ frequencyLabel(recurrence.frequency) }} ·
                            ближайшая {{ recurrence.next_occurred_on }}
                        </p>
                        <p class="text-muted-foreground text-sm">
                            {{ recurrence.account_name }}
                            <span v-if="recurrence.counterparty_account_name">
                                → {{ recurrence.counterparty_account_name }}
                            </span>
                            <span v-if="recurrence.category_name">
                                · {{ recurrence.category_name }}
                            </span>
                        </p>
                    </div>
                    <p
                        v-if="!recurrence.is_active"
                        class="text-muted-foreground text-sm"
                    >
                        Неактивен
                    </p>
                </div>

                <div v-if="recurrence.is_active" class="flex flex-wrap gap-2">
                    <Form
                        method="post"
                        :action="`/recurrences/${recurrence.id}/post`"
                        v-slot="{ processing, errors }"
                    >
                        <Button type="submit" size="sm" :disabled="processing">
                            <Spinner v-if="processing" />
                            Провести ближайшее
                        </Button>
                        <InputError :message="errors.recurrence" />
                    </Form>
                    <Form
                        method="post"
                        :action="`/recurrences/${recurrence.id}/deactivate`"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="ghost"
                            size="sm"
                            :disabled="processing"
                        >
                            Отключить
                        </Button>
                    </Form>
                </div>

                <Form
                    method="patch"
                    :action="`/recurrences/${recurrence.id}`"
                    :key="`${recurrence.id}-${recurrence.next_occurred_on}`"
                    v-slot="{ processing, errors }"
                    class="flex flex-col gap-3"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label :for="`type-${recurrence.id}`">Тип</Label>
                            <select
                                :id="`type-${recurrence.id}`"
                                name="type"
                                required
                                :class="selectClass"
                                :value="typeOf(recurrence)"
                                @change="
                                    editTypes[recurrence.id] = (
                                        $event.target as HTMLSelectElement
                                    ).value as RecurrenceType
                                "
                            >
                                <option value="expense">Расход</option>
                                <option value="income">Доход</option>
                                <option value="transfer">Перевод</option>
                            </select>
                            <InputError :message="errors.type" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`account-${recurrence.id}`"
                                >Счёт</Label
                            >
                            <select
                                :id="`account-${recurrence.id}`"
                                name="account_id"
                                required
                                :class="selectClass"
                            >
                                <option
                                    v-for="account in accounts"
                                    :key="account.id"
                                    :value="account.id"
                                    :selected="
                                        account.id === recurrence.account_id
                                    "
                                >
                                    {{ account.name }}
                                </option>
                            </select>
                            <InputError :message="errors.account_id" />
                        </div>
                        <div
                            v-if="typeOf(recurrence) === 'transfer'"
                            class="grid gap-2"
                        >
                            <Label :for="`counterparty-${recurrence.id}`"
                                >На счёт</Label
                            >
                            <select
                                :id="`counterparty-${recurrence.id}`"
                                name="counterparty_account_id"
                                required
                                :class="selectClass"
                            >
                                <option
                                    v-for="account in accounts"
                                    :key="account.id"
                                    :value="account.id"
                                    :selected="
                                        account.id ===
                                        recurrence.counterparty_account_id
                                    "
                                >
                                    {{ account.name }}
                                </option>
                            </select>
                            <InputError
                                :message="errors.counterparty_account_id"
                            />
                        </div>
                        <div v-else class="grid gap-2">
                            <Label :for="`category-${recurrence.id}`"
                                >Категория</Label
                            >
                            <select
                                :id="`category-${recurrence.id}`"
                                name="category_id"
                                required
                                :class="selectClass"
                            >
                                <option
                                    v-for="category in categoriesFor(
                                        typeOf(recurrence),
                                    )"
                                    :key="category.id"
                                    :value="category.id"
                                    :selected="
                                        category.id === recurrence.category_id
                                    "
                                >
                                    {{ category.emoji }} {{ category.name }}
                                </option>
                            </select>
                            <InputError :message="errors.category_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`amount-${recurrence.id}`"
                                >Сумма</Label
                            >
                            <Input
                                :id="`amount-${recurrence.id}`"
                                name="amount"
                                type="text"
                                inputmode="decimal"
                                required
                                :default-value="
                                    formatMinorInput(recurrence.amount)
                                "
                            />
                            <InputError :message="errors.amount" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`frequency-${recurrence.id}`"
                                >Периодичность</Label
                            >
                            <select
                                :id="`frequency-${recurrence.id}`"
                                name="frequency"
                                required
                                :class="selectClass"
                            >
                                <option
                                    value="weekly"
                                    :selected="
                                        recurrence.frequency === 'weekly'
                                    "
                                >
                                    Каждую неделю
                                </option>
                                <option
                                    value="monthly"
                                    :selected="
                                        recurrence.frequency === 'monthly'
                                    "
                                >
                                    Каждый месяц
                                </option>
                                <option
                                    value="yearly"
                                    :selected="
                                        recurrence.frequency === 'yearly'
                                    "
                                >
                                    Каждый год
                                </option>
                            </select>
                            <InputError :message="errors.frequency" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`next-${recurrence.id}`"
                                >Ближайшая дата</Label
                            >
                            <Input
                                :id="`next-${recurrence.id}`"
                                name="next_occurred_on"
                                type="date"
                                required
                                :default-value="recurrence.next_occurred_on"
                            />
                            <InputError :message="errors.next_occurred_on" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label :for="`description-${recurrence.id}`"
                                >Описание</Label
                            >
                            <Input
                                :id="`description-${recurrence.id}`"
                                name="description"
                                type="text"
                                maxlength="255"
                                :default-value="recurrence.description ?? ''"
                            />
                            <InputError :message="errors.description" />
                        </div>
                    </div>
                    <Button type="submit" size="sm" :disabled="processing">
                        <Spinner v-if="processing" />
                        Сохранить
                    </Button>
                </Form>
            </section>
        </div>

        <div class="order-1 flex w-full flex-col gap-6 lg:order-2 lg:max-w-md">
            <Form
                method="post"
                action="/recurrences"
                v-slot="{ errors, processing }"
                class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            >
                <div class="space-y-1">
                    <h2 class="font-medium">Новый шаблон</h2>
                    <p class="text-muted-foreground text-sm">
                        Сумма в деньгах бюджета. Проведение — отдельной кнопкой.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="type">Тип</Label>
                    <select
                        id="type"
                        name="type"
                        required
                        :class="selectClass"
                        v-model="createType"
                    >
                        <option value="expense">Расход</option>
                        <option value="income">Доход</option>
                        <option value="transfer">Перевод</option>
                    </select>
                    <InputError :message="errors.type" />
                </div>

                <div class="grid gap-2">
                    <Label for="account_id">Счёт</Label>
                    <select
                        id="account_id"
                        name="account_id"
                        required
                        :class="selectClass"
                    >
                        <option
                            v-for="account in accounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.name }}
                        </option>
                    </select>
                    <InputError :message="errors.account_id" />
                </div>

                <div v-if="createType === 'transfer'" class="grid gap-2">
                    <Label for="counterparty_account_id">На счёт</Label>
                    <select
                        id="counterparty_account_id"
                        name="counterparty_account_id"
                        required
                        :class="selectClass"
                    >
                        <option
                            v-for="account in accounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.name }}
                        </option>
                    </select>
                    <InputError :message="errors.counterparty_account_id" />
                </div>

                <div v-else class="grid gap-2">
                    <Label for="category_id">Категория</Label>
                    <select
                        id="category_id"
                        name="category_id"
                        required
                        :class="selectClass"
                    >
                        <option
                            v-for="category in categoriesFor(createType)"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.emoji }} {{ category.name }}
                        </option>
                    </select>
                    <InputError :message="errors.category_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="amount">Сумма</Label>
                    <Input
                        id="amount"
                        name="amount"
                        type="text"
                        inputmode="decimal"
                        required
                        placeholder="5.00"
                    />
                    <InputError :message="errors.amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="frequency">Периодичность</Label>
                    <select
                        id="frequency"
                        name="frequency"
                        required
                        :class="selectClass"
                    >
                        <option value="weekly">Каждую неделю</option>
                        <option value="monthly" selected>Каждый месяц</option>
                        <option value="yearly">Каждый год</option>
                    </select>
                    <InputError :message="errors.frequency" />
                </div>

                <div class="grid gap-2">
                    <Label for="next_occurred_on">Ближайшая дата</Label>
                    <Input
                        id="next_occurred_on"
                        name="next_occurred_on"
                        type="date"
                        required
                        :default-value="today"
                    />
                    <InputError :message="errors.next_occurred_on" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Описание</Label>
                    <Input
                        id="description"
                        name="description"
                        type="text"
                        maxlength="255"
                        placeholder="Подписка"
                    />
                    <InputError :message="errors.description" />
                </div>

                <Button
                    type="submit"
                    :disabled="processing || accounts.length === 0"
                >
                    <Spinner v-if="processing" />
                    Создать шаблон
                </Button>
            </Form>
        </div>
    </div>
</template>
