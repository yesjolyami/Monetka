<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login, register } from '@/routes';
</script>

<template>
    <Head />

    <div
        class="bg-background text-foreground flex min-h-screen flex-col items-center px-6 py-8"
    >
        <header class="flex w-full max-w-5xl items-center justify-between">
            <p class="font-display text-lg tracking-tight">Monetka</p>
            <nav class="flex items-center gap-3 text-sm">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="bg-primary text-primary-foreground rounded-md px-4 py-2"
                >
                    Обзор
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="hover:bg-secondary rounded-md px-4 py-2"
                    >
                        Вход
                    </Link>
                    <Link
                        :href="register()"
                        class="bg-primary text-primary-foreground rounded-md px-4 py-2"
                    >
                        Регистрация
                    </Link>
                </template>
            </nav>
        </header>

        <main
            class="flex w-full max-w-5xl flex-1 flex-col justify-center gap-6 py-16"
        >
            <h1
                class="font-display max-w-3xl text-5xl leading-none tracking-tight sm:text-7xl"
            >
                Семейный бюджет без лишнего шума
            </h1>
            <p class="text-muted-foreground max-w-xl text-lg">
                Счета, операции, цели, долги и лимиты — в одном месте, в рублях
                и копейках.
            </p>
            <div class="flex flex-wrap gap-3">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="bg-primary text-primary-foreground rounded-md px-5 py-2.5"
                >
                    К обзору
                </Link>
                <template v-else>
                    <Link
                        :href="register()"
                        class="bg-primary text-primary-foreground rounded-md px-5 py-2.5"
                    >
                        Создать бюджет
                    </Link>
                    <Link
                        :href="login()"
                        class="border-border hover:bg-secondary rounded-md border px-5 py-2.5"
                    >
                        У меня уже есть аккаунт
                    </Link>
                </template>
            </div>
        </main>
    </div>
</template>
