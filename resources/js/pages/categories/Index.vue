<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type CategoryRow = {
    id: number;
    kind: 'income' | 'expense';
    name: string;
    emoji: string;
    color: string;
};

const props = defineProps<{
    expense: CategoryRow[];
    income: CategoryRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Категории',
                href: '/categories',
            },
        ],
    },
});

const selectClass =
    'border-input bg-background ring-offset-background focus-visible:ring-ring h-9 rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none';

const groups = computed(() => [
    {
        kind: 'expense' as const,
        title: 'Расходы',
        empty: 'Пока нет категорий расходов.',
        items: props.expense,
    },
    {
        kind: 'income' as const,
        title: 'Доходы',
        empty: 'Пока нет категорий доходов.',
        items: props.income,
    },
]);
</script>

<template>
    <Head title="Категории" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4 lg:flex-row">
        <div class="order-2 flex min-w-0 flex-1 flex-col gap-6 lg:order-1">
            <div class="space-y-1">
                <h1 class="font-display text-2xl">Категории</h1>
                <p class="text-muted-foreground text-sm">
                    Свои метки для доходов и расходов. Удаление недоступно, если
                    по категории уже есть операции.
                </p>
            </div>

            <section
                v-for="group in groups"
                :key="group.kind"
                class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            >
                <h2 class="font-medium">{{ group.title }}</h2>
                <p
                    v-if="group.items.length === 0"
                    class="text-muted-foreground text-sm"
                >
                    {{ group.empty }}
                </p>
                <div
                    v-for="category in group.items"
                    :key="category.id"
                    class="flex flex-col gap-3 rounded-lg border p-3"
                >
                    <Form
                        method="patch"
                        :action="`/categories/${category.id}`"
                        v-slot="{ processing, errors }"
                        class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end"
                    >
                        <div class="grid gap-2 sm:w-16">
                            <Label :for="`${group.kind}-emoji-${category.id}`">Эмодзи</Label>
                            <Input
                                :id="`${group.kind}-emoji-${category.id}`"
                                name="emoji"
                                type="text"
                                required
                                maxlength="16"
                                :default-value="category.emoji"
                            />
                            <InputError :message="errors.emoji" />
                        </div>
                        <div class="grid min-w-0 flex-1 gap-2">
                            <Label :for="`${group.kind}-name-${category.id}`">Название</Label>
                            <Input
                                :id="`${group.kind}-name-${category.id}`"
                                name="name"
                                type="text"
                                required
                                maxlength="80"
                                :default-value="category.name"
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-2 sm:w-28">
                            <Label :for="`${group.kind}-kind-${category.id}`">Тип</Label>
                            <select
                                :id="`${group.kind}-kind-${category.id}`"
                                name="kind"
                                required
                                :class="selectClass"
                            >
                                <option
                                    value="expense"
                                    :selected="category.kind === 'expense'"
                                >
                                    Расход
                                </option>
                                <option
                                    value="income"
                                    :selected="category.kind === 'income'"
                                >
                                    Доход
                                </option>
                            </select>
                            <InputError :message="errors.kind" />
                        </div>
                        <div class="grid gap-2 sm:w-28">
                            <Label :for="`${group.kind}-color-${category.id}`">Цвет</Label>
                            <Input
                                :id="`${group.kind}-color-${category.id}`"
                                name="color"
                                type="text"
                                required
                                maxlength="20"
                                :default-value="category.color"
                            />
                            <InputError :message="errors.color" />
                        </div>
                        <Button type="submit" size="sm" :disabled="processing">
                            <Spinner v-if="processing" />
                            Сохранить
                        </Button>
                    </Form>
                    <Form
                        method="delete"
                        :action="`/categories/${category.id}`"
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
                        <InputError :message="errors.category" />
                    </Form>
                </div>
            </section>
        </div>

        <div class="order-1 flex w-full flex-col gap-6 lg:order-2 lg:max-w-md">
            <Form
                method="post"
                action="/categories"
                v-slot="{ errors, processing }"
                class="bg-card flex flex-col gap-4 rounded-xl border p-4"
            >
                <div class="space-y-1">
                    <h2 class="font-medium">Новая категория</h2>
                    <p class="text-muted-foreground text-sm">
                        То же создание, что из формы операции.
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
                        placeholder="Продукты"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="kind">Тип</Label>
                    <select id="kind" name="kind" required :class="selectClass">
                        <option value="expense">Расход</option>
                        <option value="income">Доход</option>
                    </select>
                    <InputError :message="errors.kind" />
                </div>

                <div class="grid gap-2">
                    <Label for="emoji">Эмодзи</Label>
                    <Input
                        id="emoji"
                        name="emoji"
                        type="text"
                        required
                        maxlength="16"
                        placeholder="🥑"
                    />
                    <InputError :message="errors.emoji" />
                </div>

                <div class="grid gap-2">
                    <Label for="color">Цвет</Label>
                    <Input
                        id="color"
                        name="color"
                        type="text"
                        required
                        maxlength="20"
                        placeholder="#1C6CFF"
                    />
                    <InputError :message="errors.color" />
                </div>

                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Создать категорию
                </Button>
            </Form>
        </div>
    </div>
</template>
