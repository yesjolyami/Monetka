<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

const { invitation } = defineProps<{
    invitation: {
        token: string;
        email: string;
        workspace: {
            name: string;
        };
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Приглашение',
                href: '/invitations',
            },
        ],
    },
});
</script>

<template>
    <Head title="Приглашение" />

    <div class="mx-auto flex w-full max-w-md flex-col gap-6 p-4">
        <div class="space-y-1">
            <h1 class="font-display text-2xl">Приглашение</h1>
            <p class="text-muted-foreground text-sm">
                Вас пригласили в бюджет «{{ invitation.workspace.name }}».
            </p>
        </div>

        <div class="flex flex-col gap-3">
            <Form
                method="post"
                :action="`/invitations/${invitation.token}/accept`"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-2"
            >
                <Button type="submit" class="w-full" :disabled="processing">
                    <Spinner v-if="processing" />
                    Принять
                </Button>
                <InputError :message="errors.invitation" />
            </Form>

            <Form
                method="post"
                :action="`/invitations/${invitation.token}/decline`"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-2"
            >
                <Button
                    type="submit"
                    variant="outline"
                    class="w-full"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    Отклонить
                </Button>
                <InputError :message="errors.invitation" />
            </Form>
        </div>
    </div>
</template>
