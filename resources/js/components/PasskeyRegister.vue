<script setup lang="ts">
import { usePasskeyRegister } from '@laravel/passkeys/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const emit = defineEmits<{
    success: [];
}>();

const getDefaultPasskeyName = () => {
    const ua = navigator.userAgent;

    const browser = [
        { pattern: /Edg|Edge/, name: 'Edge' },
        { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
        { pattern: /Firefox|FxiOS/, name: 'Firefox' },
        { pattern: /Chrome|CriOS/, name: 'Chrome' },
        { pattern: /Safari/, name: 'Safari' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    const os = [
        { pattern: /iPhone/, name: 'iPhone' },
        { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
        { pattern: /Android/, name: 'Android' },
        { pattern: /Mac/, name: 'Mac' },
        { pattern: /Windows/, name: 'Windows' },
    ].find(({ pattern }) => pattern.test(ua))?.name;

    return [browser, os].filter(Boolean).join(' on ') || '';
};

const name = ref(getDefaultPasskeyName());
const showForm = ref(false);

const { register, isLoading, error, isSupported } = usePasskeyRegister({
    onSuccess: () => {
        name.value = '';
        showForm.value = false;
        emit('success');
    },
});

const handleSubmit = async (event: Event) => {
    event.preventDefault();

    if (!name.value.trim()) {
        return;
    }

    await register(name.value);
};

const handleCancel = () => {
    showForm.value = false;
    name.value = '';
};
</script>

<template>
    <div v-if="!isSupported" class="text-muted-foreground text-sm">
        Этот браузер не умеет ключи входа.
    </div>

    <Button v-else-if="!showForm" variant="outline" @click="showForm = true">
        Добавить ключ
    </Button>

    <form
        v-else
        @submit="handleSubmit"
        class="border-border bg-muted/50 space-y-4 rounded-lg border p-4"
    >
        <div class="grid gap-2">
            <Label for="passkey-name">Название ключа</Label>
            <Input
                id="passkey-name"
                type="text"
                v-model="name"
                placeholder="Например, iPhone"
                class="border-foreground/20 mt-1 block w-full"
                autofocus
            />
            <p class="text-muted-foreground text-xs">
                Чтобы потом узнать, какой это ключ.
            </p>
        </div>

        <InputError v-if="error" :message="error" />

        <div class="flex gap-2">
            <Button type="submit" :disabled="isLoading || !name.trim()">
                {{ isLoading ? 'Сохраняем…' : 'Сохранить ключ' }}
            </Button>
            <Button type="button" variant="ghost" @click="handleCancel">
                Отмена
            </Button>
        </div>
    </form>
</template>
