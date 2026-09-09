<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Новый бюджет',
                href: '/workspaces/create',
            },
        ],
    },
});
</script>

<template>
    <Head title="Новый бюджет" />

    <div class="mx-auto flex w-full max-w-md flex-col gap-6 p-4">
        <div class="space-y-1">
            <h1 class="font-display text-2xl">Новый бюджет</h1>
            <p class="text-muted-foreground text-sm">
                Название и валюта. Курсов внутри бюджета не будет.
            </p>
        </div>

        <Form
            method="post"
            action="/workspaces"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-2">
                <Label for="name">Название</Label>
                <Input
                    id="name"
                    name="name"
                    type="text"
                    required
                    autofocus
                    maxlength="80"
                    placeholder="Семья"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="currency">Валюта</Label>
                <select
                    id="currency"
                    name="currency"
                    required
                    class="border-input bg-background ring-offset-background focus-visible:ring-ring h-9 rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none"
                >
                    <option value="RUB">₽ Рубль</option>
                    <option value="USD">$ Доллар</option>
                    <option value="EUR">€ Евро</option>
                </select>
                <InputError :message="errors.currency" />
            </div>

            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                Создать бюджет
            </Button>
        </Form>
    </div>
</template>
