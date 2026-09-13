<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
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

type Variant = 'income' | 'expense' | 'transfer';

const {
    variant,
    accounts,
    categories = [],
    today,
} = defineProps<{
    variant: Variant;
    accounts: AccountOption[];
    categories?: CategoryOption[];
    today: string;
}>();

const selectClass =
    'border-input bg-background ring-offset-background focus-visible:ring-ring h-9 rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none';

const copy: Record<
    Variant,
    {
        action: string;
        title: string;
        hint: string;
        submit: string;
        descriptionPlaceholder: string;
    }
> = {
    income: {
        action: '/transactions/income',
        title: 'Доход',
        hint: 'Поступление на счёт. Категория только вида «доход».',
        submit: 'Записать доход',
        descriptionPlaceholder: 'Зарплата',
    },
    expense: {
        action: '/transactions/expense',
        title: 'Расход',
        hint: 'Списание со счёта. Категория только вида «расход».',
        submit: 'Записать расход',
        descriptionPlaceholder: 'Продукты',
    },
    transfer: {
        action: '/transactions/transfer',
        title: 'Перевод',
        hint: 'Списание с одного счёта на другой. Без категории.',
        submit: 'Записать перевод',
        descriptionPlaceholder: 'На копилку',
    },
};

const fields = copy[variant];
</script>

<template>
    <Form
        method="post"
        :action="fields.action"
        v-slot="{ errors, processing }"
        class="bg-card flex flex-col gap-4 rounded-xl border p-4"
    >
        <div class="space-y-1">
            <h2 class="font-medium">{{ fields.title }}</h2>
            <p class="text-muted-foreground text-sm">{{ fields.hint }}</p>
        </div>

        <div class="grid gap-2">
            <Label :for="`${variant}-account`">
                {{ variant === 'transfer' ? 'Со счёта' : 'Счёт' }}
            </Label>
            <select
                :id="`${variant}-account`"
                name="account_id"
                required
                :class="selectClass"
            >
                <option value="" disabled selected>Выберите счёт</option>
                <option
                    v-for="account in accounts"
                    :key="account.id"
                    :value="account.id"
                >
                    {{ account.name }}
                </option>
            </select>
            <InputError :message="errors.account_id" />
            <InputError :message="errors.account" />
        </div>

        <div v-if="variant === 'transfer'" class="grid gap-2">
            <Label :for="`${variant}-counterparty`">На счёт</Label>
            <select
                :id="`${variant}-counterparty`"
                name="counterparty_account_id"
                required
                :class="selectClass"
            >
                <option value="" disabled selected>Выберите счёт</option>
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
            <Label :for="`${variant}-category`">Категория</Label>
            <select
                :id="`${variant}-category`"
                name="category_id"
                required
                :class="selectClass"
            >
                <option value="" disabled selected>Выберите категорию</option>
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
            <Label :for="`${variant}-amount`">Сумма</Label>
            <Input
                :id="`${variant}-amount`"
                name="amount"
                type="text"
                inputmode="decimal"
                required
                placeholder="0.00"
            />
            <InputError :message="errors.amount" />
        </div>

        <div class="grid gap-2">
            <Label :for="`${variant}-date`">Дата</Label>
            <Input
                :id="`${variant}-date`"
                name="occurred_on"
                type="date"
                required
                :default-value="today"
            />
            <InputError :message="errors.occurred_on" />
        </div>

        <div class="grid gap-2">
            <Label :for="`${variant}-description`">Описание</Label>
            <Input
                :id="`${variant}-description`"
                name="description"
                type="text"
                maxlength="255"
                :placeholder="fields.descriptionPlaceholder"
            />
            <InputError :message="errors.description" />
        </div>

        <Button type="submit" :disabled="processing">
            <Spinner v-if="processing" />
            {{ fields.submit }}
        </Button>
    </Form>
</template>
