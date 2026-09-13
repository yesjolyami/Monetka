<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type DebtRow = {
    id: number;
    direction: 'they_owe' | 'i_owe' | string;
    counterparty_name: string;
    original_amount: number;
    notes: string | null;
    remainder: number;
};

type AccountOption = {
    id: number;
    name: string;
};

const { debts, accounts } = defineProps<{
    debts: DebtRow[];
    accounts: AccountOption[];
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
                title: 'Долги',
                href: '/debts',
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

function directionLabel(direction: DebtRow['direction']): string {
    if (direction === 'they_owe') {
        return 'Мне должны';
    }

    if (direction === 'i_owe') {
        return 'Я должен';
    }

    return direction;
}

function repaidPercent(debt: DebtRow): number {
    if (debt.original_amount <= 0) {
        return 0;
    }

    const repaid = debt.original_amount - debt.remainder;

    return Math.min(100, Math.round((repaid / debt.original_amount) * 100));
}
</script>

<template>
    <Head title="Долги" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4 lg:flex-row">
        <div class="order-2 flex min-w-0 flex-1 flex-col gap-6 lg:order-1">
            <div class="space-y-1">
                <h1 class="font-display text-2xl">Долги</h1>
                <p class="text-muted-foreground text-sm">
                    Долг — не счёт. Возврат без счёта только уменьшает остаток;
                    со счётом ещё двигает баланс.
                </p>
            </div>

            <p v-if="debts.length === 0" class="text-muted-foreground text-sm">
                Пока нет долгов. Запишите, кому должны вы или кто должен вам.
            </p>

            <section
                v-for="debt in debts"
                :key="debt.id"
                class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            >
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0 space-y-1">
                        <h2 class="font-medium">
                            {{ debt.counterparty_name }}
                        </h2>
                        <p class="text-muted-foreground text-sm">
                            {{ directionLabel(debt.direction) }} · остаток
                            {{ formatAmount(debt.remainder) }} из
                            {{ formatAmount(debt.original_amount) }}
                        </p>
                    </div>
                    <p class="font-display text-xl tabular-nums">
                        {{ formatAmount(debt.remainder) }}
                    </p>
                </div>

                <div class="bg-muted h-2 overflow-hidden rounded-full">
                    <div
                        class="h-full rounded-full bg-[#1C6CFF]"
                        :style="{ width: `${repaidPercent(debt)}%` }"
                    />
                </div>

                <p v-if="debt.notes" class="text-muted-foreground text-sm">
                    {{ debt.notes }}
                </p>

                <Form
                    method="patch"
                    :action="`/debts/${debt.id}`"
                    v-slot="{ processing, errors }"
                    class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end"
                >
                    <div class="grid min-w-0 flex-1 gap-2">
                        <Label :for="`name-${debt.id}`">Кто</Label>
                        <Input
                            :id="`name-${debt.id}`"
                            name="counterparty_name"
                            type="text"
                            required
                            maxlength="80"
                            :default-value="debt.counterparty_name"
                        />
                        <InputError :message="errors.counterparty_name" />
                    </div>
                    <div class="grid gap-2 sm:w-44">
                        <Label :for="`direction-${debt.id}`">Направление</Label>
                        <select
                            :id="`direction-${debt.id}`"
                            name="direction"
                            required
                            :class="selectClass"
                        >
                            <option
                                value="they_owe"
                                :selected="debt.direction === 'they_owe'"
                            >
                                Мне должны
                            </option>
                            <option
                                value="i_owe"
                                :selected="debt.direction === 'i_owe'"
                            >
                                Я должен
                            </option>
                        </select>
                        <InputError :message="errors.direction" />
                    </div>
                    <div class="grid gap-2 sm:w-36">
                        <Label :for="`original-${debt.id}`">Сумма</Label>
                        <Input
                            :id="`original-${debt.id}`"
                            name="original_amount"
                            type="text"
                            inputmode="decimal"
                            required
                            :default-value="
                                formatMinorInput(debt.original_amount)
                            "
                        />
                        <InputError :message="errors.original_amount" />
                    </div>
                    <div class="grid min-w-0 flex-1 gap-2">
                        <Label :for="`notes-${debt.id}`">Заметка</Label>
                        <Input
                            :id="`notes-${debt.id}`"
                            name="notes"
                            type="text"
                            maxlength="2000"
                            :default-value="debt.notes ?? ''"
                        />
                        <InputError :message="errors.notes" />
                    </div>
                    <Button type="submit" size="sm" :disabled="processing">
                        <Spinner v-if="processing" />
                        Сохранить
                    </Button>
                </Form>

                <Form
                    v-if="debt.remainder > 0"
                    method="post"
                    :action="`/debts/${debt.id}/repay`"
                    v-slot="{ processing, errors }"
                    class="flex flex-col gap-3 rounded-lg border p-3"
                >
                    <h3 class="text-sm font-medium">Возврат</h3>
                    <div class="grid gap-2">
                        <Label :for="`repay-account-${debt.id}`">Счёт</Label>
                        <select
                            :id="`repay-account-${debt.id}`"
                            name="account_id"
                            :class="selectClass"
                        >
                            <option value="">Без счёта</option>
                            <option
                                v-for="account in accounts"
                                :key="account.id"
                                :value="account.id"
                            >
                                {{ account.name }}
                            </option>
                        </select>
                        <InputError
                            :message="errors.account_id ?? errors.account"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`repay-amount-${debt.id}`">Сумма</Label>
                        <Input
                            :id="`repay-amount-${debt.id}`"
                            name="amount"
                            type="text"
                            inputmode="decimal"
                            required
                            placeholder="0.00"
                        />
                        <InputError :message="errors.amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`repay-date-${debt.id}`">Дата</Label>
                        <Input
                            :id="`repay-date-${debt.id}`"
                            name="occurred_on"
                            type="date"
                            required
                            :default-value="today"
                        />
                        <InputError :message="errors.occurred_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`repay-description-${debt.id}`"
                            >Описание</Label
                        >
                        <Input
                            :id="`repay-description-${debt.id}`"
                            name="description"
                            type="text"
                            maxlength="255"
                        />
                        <InputError :message="errors.description" />
                    </div>
                    <Button type="submit" size="sm" :disabled="processing">
                        <Spinner v-if="processing" />
                        Записать возврат
                    </Button>
                </Form>

                <Form
                    method="delete"
                    :action="`/debts/${debt.id}`"
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
                    <InputError :message="errors.debt" />
                </Form>
            </section>
        </div>

        <div class="order-1 flex w-full flex-col gap-6 lg:order-2 lg:max-w-md">
            <Form
                method="post"
                action="/debts"
                v-slot="{ errors, processing }"
                class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            >
                <div class="space-y-1">
                    <h2 class="font-medium">Новый долг</h2>
                    <p class="text-muted-foreground text-sm">
                        Имя контрагента — обычная строка, не обязательно
                        участник бюджета.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="counterparty_name">Кто</Label>
                    <Input
                        id="counterparty_name"
                        name="counterparty_name"
                        type="text"
                        required
                        maxlength="80"
                        placeholder="Вася"
                    />
                    <InputError :message="errors.counterparty_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="direction">Направление</Label>
                    <select
                        id="direction"
                        name="direction"
                        required
                        :class="selectClass"
                    >
                        <option value="they_owe">Мне должны</option>
                        <option value="i_owe">Я должен</option>
                    </select>
                    <InputError :message="errors.direction" />
                </div>

                <div class="grid gap-2">
                    <Label for="original_amount">Сумма</Label>
                    <Input
                        id="original_amount"
                        name="original_amount"
                        type="text"
                        inputmode="decimal"
                        required
                        placeholder="30.00"
                    />
                    <InputError :message="errors.original_amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Заметка</Label>
                    <Input
                        id="notes"
                        name="notes"
                        type="text"
                        maxlength="2000"
                        placeholder="За обед"
                    />
                    <InputError :message="errors.notes" />
                </div>

                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Создать долг
                </Button>
            </Form>
        </div>
    </div>
</template>
