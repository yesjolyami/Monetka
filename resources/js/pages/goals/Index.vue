<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type GoalRow = {
    id: number;
    name: string;
    target_amount: number;
    target_date: string | null;
    notes: string | null;
    progress: number;
};

type AccountOption = {
    id: number;
    name: string;
};

const { goals, accounts } = defineProps<{
    goals: GoalRow[];
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
                title: 'Цели',
                href: '/goals',
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

function progressPercent(goal: GoalRow): number {
    if (goal.target_amount <= 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.round((goal.progress / goal.target_amount) * 100),
    );
}
</script>

<template>
    <Head title="Цели" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4 lg:flex-row">
        <div class="order-2 flex min-w-0 flex-1 flex-col gap-6 lg:order-1">
            <div class="space-y-1">
                <h1 class="font-display text-2xl">Цели</h1>
                <p class="text-muted-foreground text-sm">
                    Накопления со счёта. Цель — не счёт: пополнение и снятие
                    всегда идут через журнал.
                </p>
            </div>

            <p v-if="goals.length === 0" class="text-muted-foreground text-sm">
                Пока нет целей. Создайте первую — например отпуск или ремонт.
            </p>

            <section
                v-for="goal in goals"
                :key="goal.id"
                class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            >
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="min-w-0 space-y-1">
                        <h2 class="font-medium">{{ goal.name }}</h2>
                        <p class="text-muted-foreground text-sm">
                            <span v-if="goal.target_date"
                                >До {{ goal.target_date }} ·
                            </span>
                            {{ formatAmount(goal.progress) }} из
                            {{ formatAmount(goal.target_amount) }}
                        </p>
                    </div>
                    <p class="font-display text-xl tabular-nums">
                        {{ progressPercent(goal) }}%
                    </p>
                </div>

                <div class="bg-muted h-2 overflow-hidden rounded-full">
                    <div
                        class="h-full rounded-full bg-[#1C6CFF]"
                        :style="{ width: `${progressPercent(goal)}%` }"
                    />
                </div>

                <p v-if="goal.notes" class="text-muted-foreground text-sm">
                    {{ goal.notes }}
                </p>

                <Form
                    method="patch"
                    :action="`/goals/${goal.id}`"
                    v-slot="{ processing, errors }"
                    class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end"
                >
                    <div class="grid min-w-0 flex-1 gap-2">
                        <Label :for="`name-${goal.id}`">Название</Label>
                        <Input
                            :id="`name-${goal.id}`"
                            name="name"
                            type="text"
                            required
                            maxlength="80"
                            :default-value="goal.name"
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2 sm:w-36">
                        <Label :for="`target-${goal.id}`">Цель</Label>
                        <Input
                            :id="`target-${goal.id}`"
                            name="target_amount"
                            type="text"
                            inputmode="decimal"
                            required
                            :default-value="
                                formatMinorInput(goal.target_amount)
                            "
                        />
                        <InputError :message="errors.target_amount" />
                    </div>
                    <div class="grid gap-2 sm:w-40">
                        <Label :for="`date-${goal.id}`">Срок</Label>
                        <Input
                            :id="`date-${goal.id}`"
                            name="target_date"
                            type="date"
                            :default-value="goal.target_date ?? ''"
                        />
                        <InputError :message="errors.target_date" />
                    </div>
                    <div class="grid min-w-0 flex-1 gap-2">
                        <Label :for="`notes-${goal.id}`">Заметка</Label>
                        <Input
                            :id="`notes-${goal.id}`"
                            name="notes"
                            type="text"
                            maxlength="2000"
                            :default-value="goal.notes ?? ''"
                        />
                        <InputError :message="errors.notes" />
                    </div>
                    <Button type="submit" size="sm" :disabled="processing">
                        <Spinner v-if="processing" />
                        Сохранить
                    </Button>
                </Form>

                <div class="grid gap-3 sm:grid-cols-2">
                    <Form
                        method="post"
                        :action="`/goals/${goal.id}/contribute`"
                        v-slot="{ processing, errors }"
                        class="flex flex-col gap-3 rounded-lg border p-3"
                    >
                        <h3 class="text-sm font-medium">Пополнить</h3>
                        <div class="grid gap-2">
                            <Label :for="`contribute-account-${goal.id}`"
                                >Счёт</Label
                            >
                            <select
                                :id="`contribute-account-${goal.id}`"
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
                            <InputError
                                :message="errors.account_id ?? errors.account"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`contribute-amount-${goal.id}`"
                                >Сумма</Label
                            >
                            <Input
                                :id="`contribute-amount-${goal.id}`"
                                name="amount"
                                type="text"
                                inputmode="decimal"
                                required
                                placeholder="0.00"
                            />
                            <InputError :message="errors.amount" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`contribute-date-${goal.id}`"
                                >Дата</Label
                            >
                            <Input
                                :id="`contribute-date-${goal.id}`"
                                name="occurred_on"
                                type="date"
                                required
                                :default-value="today"
                            />
                            <InputError :message="errors.occurred_on" />
                        </div>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="processing || accounts.length === 0"
                        >
                            <Spinner v-if="processing" />
                            Пополнить
                        </Button>
                    </Form>

                    <Form
                        method="post"
                        :action="`/goals/${goal.id}/withdraw`"
                        v-slot="{ processing, errors }"
                        class="flex flex-col gap-3 rounded-lg border p-3"
                    >
                        <h3 class="text-sm font-medium">Снять</h3>
                        <div class="grid gap-2">
                            <Label :for="`withdraw-account-${goal.id}`"
                                >Счёт</Label
                            >
                            <select
                                :id="`withdraw-account-${goal.id}`"
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
                            <InputError
                                :message="errors.account_id ?? errors.account"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`withdraw-amount-${goal.id}`"
                                >Сумма</Label
                            >
                            <Input
                                :id="`withdraw-amount-${goal.id}`"
                                name="amount"
                                type="text"
                                inputmode="decimal"
                                required
                                placeholder="0.00"
                            />
                            <InputError :message="errors.amount" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`withdraw-date-${goal.id}`"
                                >Дата</Label
                            >
                            <Input
                                :id="`withdraw-date-${goal.id}`"
                                name="occurred_on"
                                type="date"
                                required
                                :default-value="today"
                            />
                            <InputError :message="errors.occurred_on" />
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            size="sm"
                            :disabled="processing || accounts.length === 0"
                        >
                            <Spinner v-if="processing" />
                            Снять
                        </Button>
                    </Form>
                </div>

                <Form
                    method="delete"
                    :action="`/goals/${goal.id}`"
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
                    <InputError :message="errors.goal" />
                </Form>
            </section>
        </div>

        <div class="order-1 flex w-full flex-col gap-6 lg:order-2 lg:max-w-md">
            <Form
                method="post"
                action="/goals"
                v-slot="{ errors, processing }"
                class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            >
                <div class="space-y-1">
                    <h2 class="font-medium">Новая цель</h2>
                    <p class="text-muted-foreground text-sm">
                        Сумма в деньгах бюджета. Срок можно не указывать.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="name">Название</Label>
                    <Input
                        id="name"
                        name="name"
                        type="text"
                        required
                        maxlength="80"
                        placeholder="Отпуск"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="target_amount">Сумма цели</Label>
                    <Input
                        id="target_amount"
                        name="target_amount"
                        type="text"
                        inputmode="decimal"
                        required
                        placeholder="500.00"
                    />
                    <InputError :message="errors.target_amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="target_date">Срок</Label>
                    <Input id="target_date" name="target_date" type="date" />
                    <InputError :message="errors.target_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Заметка</Label>
                    <Input
                        id="notes"
                        name="notes"
                        type="text"
                        maxlength="2000"
                        placeholder="Море"
                    />
                    <InputError :message="errors.notes" />
                </div>

                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Создать цель
                </Button>
            </Form>
        </div>
    </div>
</template>
