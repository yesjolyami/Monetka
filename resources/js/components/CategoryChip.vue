<script setup lang="ts">
defineProps<{
    name: string;
    emoji: string;
    color: string;
}>();
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium"
        :style="{ borderColor: color }"
    >
        <span aria-hidden="true">{{ emoji }}</span>
        <span>{{ name }}</span>
        <span
            class="size-2 shrink-0 rounded-full"
            :style="{ backgroundColor: color }"
        />
    </span>
</template>
