<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Оформление',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Оформление" />

    <h1 class="sr-only">Оформление</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Оформление"
            description="Светлая, тёмная или системная тема"
        />
        <AppearanceTabs />
    </div>
</template>
