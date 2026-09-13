<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type AccountRow = {
    id: number;
    name: string;
    type: 'checking' | 'savings';
    user_id: number | null;
    owner_name: string | null;
    balance: number;
};

type BankGroup = {
    id: number | null;
    name: string;
    color: string | null;
    total: number;
    shared: AccountRow[];
    personal: AccountRow[];
};

type BankOption = {
    id: number;
    name: string;
    color: string | null;
};

type MemberOption = {
    id: number;
    name: string;
};

const { groups, banks, members } = defineProps<{
    groups: BankGroup[];
    banks: BankOption[];
    members: MemberOption[];
}>();

const page = usePage();
const currency = computed(() => {
    const workspace = page.props.workspace as { currency?: string } | null;

    return workspace?.currency ?? '';
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Счета',
                href: '/accounts',
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

function typeLabel(type: AccountRow['type']): string {
    return type === 'savings' ? 'Накопительный' : 'Расчётный';
}

function hasAccounts(group: BankGroup): boolean {
    return group.shared.length > 0 || group.personal.length > 0;
}
</script>

<template>
    <Head title="Счета" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4 lg:flex-row">
        <div class="order-2 flex min-w-0 flex-1 flex-col gap-6 lg:order-1">
            <div class="space-y-1">
                <h1 class="font-display text-2xl">Счета</h1>
                <p class="text-muted-foreground text-sm">
                    Остаток считается по журналу. Архивные счета скрыты.
                </p>
            </div>

            <p
                v-if="groups.every((group) => !hasAccounts(group))"
                class="text-muted-foreground text-sm"
            >
                Пока нет счетов. Создайте первый — можно указать стартовый
                баланс.
            </p>

            <section
                v-for="group in groups"
                :key="group.id ?? 'unbanked'"
                class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <span
                            v-if="group.color"
                            class="size-3 rounded-full"
                            :style="{ backgroundColor: group.color }"
                        />
                        <h2 class="font-medium">{{ group.name }}</h2>
                    </div>
                    <p class="font-display text-xl tabular-nums">
                        {{ formatAmount(group.total) }}
                    </p>
                </div>

                <div v-if="group.shared.length" class="flex flex-col gap-2">
                    <h3
                        class="text-muted-foreground text-xs font-medium uppercase"
                    >
                        Общие
                    </h3>
                    <div
                        v-for="account in group.shared"
                        :key="account.id"
                        class="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <Link
                                :href="`/accounts/${account.id}`"
                                class="truncate font-medium hover:underline"
                            >
                                {{ account.name }}
                            </Link>
                            <p class="text-muted-foreground text-xs">
                                {{ typeLabel(account.type) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <p class="text-lg tabular-nums">
                                {{ formatAmount(account.balance) }}
                            </p>
                            <Form
                                method="post"
                                :action="`/accounts/${account.id}/archive`"
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    :disabled="processing"
                                >
                                    В архив
                                </Button>
                            </Form>
                            <Form
                                method="delete"
                                :action="`/accounts/${account.id}`"
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
                                <InputError :message="errors.account" />
                            </Form>
                        </div>
                    </div>
                </div>

                <div v-if="group.personal.length" class="flex flex-col gap-2">
                    <h3
                        class="text-muted-foreground text-xs font-medium uppercase"
                    >
                        Личные
                    </h3>
                    <div
                        v-for="account in group.personal"
                        :key="account.id"
                        class="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <Link
                                :href="`/accounts/${account.id}`"
                                class="truncate font-medium hover:underline"
                            >
                                {{ account.name }}
                            </Link>
                            <p class="text-muted-foreground text-xs">
                                {{ typeLabel(account.type) }}
                                <span v-if="account.owner_name">
                                    · {{ account.owner_name }}
                                </span>
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <p class="text-lg tabular-nums">
                                {{ formatAmount(account.balance) }}
                            </p>
                            <Form
                                method="post"
                                :action="`/accounts/${account.id}/archive`"
                                v-slot="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    :disabled="processing"
                                >
                                    В архив
                                </Button>
                            </Form>
                            <Form
                                method="delete"
                                :action="`/accounts/${account.id}`"
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
                                <InputError :message="errors.account" />
                            </Form>
                        </div>
                    </div>
                </div>

                <Form
                    v-if="group.id !== null"
                    method="delete"
                    :action="`/banks/${group.id}`"
                    v-slot="{ processing, errors }"
                    class="flex flex-col gap-2"
                >
                    <Button
                        type="submit"
                        variant="ghost"
                        size="sm"
                        class="self-start"
                        :disabled="processing"
                    >
                        Удалить банк
                    </Button>
                    <InputError :message="errors.bank" />
                </Form>
            </section>
        </div>

        <div class="order-1 flex w-full flex-col gap-6 lg:order-2 lg:max-w-md">
            <Form
                method="post"
                action="/accounts"
                v-slot="{ errors, processing }"
                class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            >
                <div class="space-y-1">
                    <h2 class="font-medium">Новый счёт</h2>
                    <p class="text-muted-foreground text-sm">
                        Стартовый баланс станет корректировкой на сегодня.
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
                        placeholder="Карта"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="type">Тип</Label>
                    <select id="type" name="type" required :class="selectClass">
                        <option value="checking">Расчётный</option>
                        <option value="savings">Накопительный</option>
                    </select>
                    <InputError :message="errors.type" />
                </div>

                <div class="grid gap-2">
                    <Label for="bank_id">Банк</Label>
                    <select id="bank_id" name="bank_id" :class="selectClass">
                        <option value="">Без банка</option>
                        <option
                            v-for="bank in banks"
                            :key="bank.id"
                            :value="bank.id"
                        >
                            {{ bank.name }}
                        </option>
                    </select>
                    <InputError :message="errors.bank_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="user_id">Чей счёт</Label>
                    <select id="user_id" name="user_id" :class="selectClass">
                        <option value="">Общий</option>
                        <option
                            v-for="member in members"
                            :key="member.id"
                            :value="member.id"
                        >
                            {{ member.name }}
                        </option>
                    </select>
                    <InputError :message="errors.user_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="opening_balance">Стартовый баланс</Label>
                    <Input
                        id="opening_balance"
                        name="opening_balance"
                        type="text"
                        inputmode="decimal"
                        placeholder="0.00"
                    />
                    <InputError :message="errors.opening_balance" />
                </div>

                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Создать счёт
                </Button>
            </Form>

            <Form
                method="post"
                action="/banks"
                v-slot="{ errors, processing }"
                class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            >
                <div class="space-y-1">
                    <h2 class="font-medium">Новый банк</h2>
                    <p class="text-muted-foreground text-sm">
                        Для группировки карт. Наличные можно оставить без банка.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="bank_name">Название</Label>
                    <Input
                        id="bank_name"
                        name="name"
                        type="text"
                        required
                        maxlength="80"
                        placeholder="Тинькофф"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="bank_color">Цвет</Label>
                    <Input
                        id="bank_color"
                        name="color"
                        type="text"
                        maxlength="20"
                        placeholder="#1C6CFF"
                    />
                    <InputError :message="errors.color" />
                </div>

                <Button type="submit" variant="outline" :disabled="processing">
                    <Spinner v-if="processing" />
                    Добавить банк
                </Button>
            </Form>
        </div>
    </div>
</template>
