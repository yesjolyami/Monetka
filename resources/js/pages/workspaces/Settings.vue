<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type MemberRow = {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'member';
};

type InvitationRow = {
    id: number;
    email: string;
    expires_at: string;
};

const props = defineProps<{
    workspaceName: string;
    currency: string;
    hasTransactions: boolean;
    members: MemberRow[];
    invitations: InvitationRow[];
}>();

const page = usePage();
const isOwner = computed(() => {
    const workspace = page.props.workspace as { role?: string } | null;

    return workspace?.role === 'owner';
});

const transferCandidates = computed(() =>
    props.members.filter((member) => member.role !== 'owner'),
);

const selectClass =
    'border-input bg-background ring-offset-background focus-visible:ring-ring h-9 rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Настройки',
                href: '/workspaces/settings',
            },
        ],
    },
});

function roleLabel(role: MemberRow['role']): string {
    return role === 'owner' ? 'Владелец' : 'Участник';
}

function confirmReplace(event: MouseEvent): void {
    const form = (event.currentTarget as HTMLElement).closest('form');
    const checkbox = form?.querySelector<HTMLInputElement>('input[name="replace"]');

    if (
        checkbox?.checked &&
        !window.confirm(
            'Текущие данные этого бюджета будут уничтожены. Продолжить?',
        )
    ) {
        event.preventDefault();
    }
}

function confirmLeave(event: MouseEvent): void {
    if (!window.confirm('Покинуть этот бюджет?')) {
        event.preventDefault();
    }
}

function confirmRemove(event: MouseEvent, memberName: string): void {
    if (!window.confirm(`Исключить ${memberName} из бюджета?`)) {
        event.preventDefault();
    }
}

function confirmDelete(event: MouseEvent): void {
    const form = (event.currentTarget as HTMLElement).closest('form');
    const checkbox = form?.querySelector<HTMLInputElement>('input[name="confirm"]');

    if (
        !checkbox?.checked ||
        !window.confirm(
            'Удалить этот бюджет и все его данные? Это действие нельзя отменить.',
        )
    ) {
        event.preventDefault();
    }
}

function invitationDate(iso: string): string {
    return iso.slice(0, 10);
}
</script>

<template>
    <Head title="Настройки" />

    <div class="mx-auto flex w-full max-w-lg flex-col gap-8 p-4">
        <div class="space-y-1">
            <h1 class="font-display text-2xl">Настройки</h1>
            <p class="text-muted-foreground text-sm">
                Имя и валюта бюджета, люди и JSON-бэкап.
            </p>
        </div>

        <section class="flex flex-col gap-3">
            <h2 class="font-medium">Справочники</h2>
            <div class="flex flex-wrap gap-3">
                <Button as-child variant="outline">
                    <Link href="/categories">Категории</Link>
                </Button>
                <Button as-child variant="outline">
                    <Link href="/recurrences">Шаблоны</Link>
                </Button>
            </div>
        </section>

        <Form
            method="patch"
            action="/workspaces/settings"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-4"
        >
            <div class="grid gap-2">
                <Label for="name">Название</Label>
                <Input
                    id="name"
                    name="name"
                    type="text"
                    required
                    maxlength="80"
                    :default-value="workspaceName"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="currency">Валюта</Label>
                <select
                    id="currency"
                    name="currency"
                    required
                    :disabled="hasTransactions"
                    :class="selectClass"
                    :value="currency"
                >
                    <option value="RUB">₽ Рубль</option>
                    <option value="USD">$ Доллар</option>
                    <option value="EUR">€ Евро</option>
                </select>
                <input
                    v-if="hasTransactions"
                    type="hidden"
                    name="currency"
                    :value="currency"
                />
                <p v-if="hasTransactions" class="text-muted-foreground text-sm">
                    Валюту нельзя сменить: в бюджете уже есть операции.
                </p>
                <InputError :message="errors.currency" />
            </div>

            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                Сохранить
            </Button>
        </Form>

        <section class="flex flex-col gap-4">
            <div class="space-y-1">
                <h2 class="font-medium">Участники</h2>
                <p class="text-muted-foreground text-sm">
                    Владелец один. Участник может покинуть бюджет.
                </p>
            </div>

            <ul class="flex flex-col gap-3">
                <li
                    v-for="member in members"
                    :key="member.id"
                    class="flex items-start justify-between gap-3"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ member.name }}</p>
                        <p class="text-muted-foreground truncate text-sm">
                            {{ member.email }} · {{ roleLabel(member.role) }}
                        </p>
                    </div>
                    <Form
                        v-if="isOwner && member.role !== 'owner'"
                        method="delete"
                        :action="`/workspaces/members/${member.id}`"
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="ghost"
                            size="sm"
                            :disabled="processing"
                            @click="confirmRemove($event, member.name)"
                        >
                            Исключить
                        </Button>
                    </Form>
                </li>
            </ul>
        </section>

        <Form
            v-if="isOwner"
            method="post"
            action="/workspaces/invitations"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-4"
        >
            <div class="space-y-1">
                <h2 class="font-medium">Пригласить</h2>
                <p class="text-muted-foreground text-sm">
                    Ссылка на email действует 7 дней.
                </p>
            </div>
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    required
                    maxlength="255"
                    placeholder="anna@example.com"
                />
                <InputError :message="errors.email" />
            </div>
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                Отправить приглашение
            </Button>
        </Form>

        <section v-if="isOwner && invitations.length > 0" class="flex flex-col gap-3">
            <h2 class="font-medium">Ожидают ответа</h2>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="invitation in invitations"
                    :key="invitation.id"
                    class="text-sm"
                >
                    <span>{{ invitation.email }}</span>
                    <span class="text-muted-foreground">
                        · до {{ invitationDate(invitation.expires_at) }}
                    </span>
                </li>
            </ul>
        </section>

        <Form
            v-if="isOwner && transferCandidates.length > 0"
            method="post"
            action="/workspaces/transfer"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-4"
        >
            <div class="space-y-1">
                <h2 class="font-medium">Передать владение</h2>
                <p class="text-muted-foreground text-sm">
                    Вы станете участником. Владелец останется один.
                </p>
            </div>
            <div class="grid gap-2">
                <Label for="user_id">Новый владелец</Label>
                <select
                    id="user_id"
                    name="user_id"
                    required
                    :class="selectClass"
                >
                    <option
                        v-for="candidate in transferCandidates"
                        :key="candidate.id"
                        :value="candidate.id"
                    >
                        {{ candidate.name }} ({{ candidate.email }})
                    </option>
                </select>
                <InputError :message="errors.user_id" />
            </div>
            <Button type="submit" variant="outline" :disabled="processing">
                <Spinner v-if="processing" />
                Передать владение
            </Button>
        </Form>

        <Form
            v-if="!isOwner"
            method="post"
            action="/workspaces/leave"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-3"
        >
            <div class="space-y-1">
                <h2 class="font-medium">Покинуть бюджет</h2>
                <p class="text-muted-foreground text-sm">
                    Вы потеряете доступ к этому бюджету.
                </p>
            </div>
            <InputError :message="errors.workspace" />
            <Button
                type="submit"
                variant="outline"
                :disabled="processing"
                @click="confirmLeave"
            >
                <Spinner v-if="processing" />
                Покинуть бюджет
            </Button>
        </Form>

        <Form
            v-if="isOwner"
            method="delete"
            action="/workspaces"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-4"
        >
            <div class="space-y-1">
                <h2 class="font-medium">Удалить бюджет</h2>
                <p class="text-muted-foreground text-sm">
                    Все счета, операции и остальные данные этого бюджета будут
                    уничтожены. Другие бюджеты не затронуты.
                </p>
            </div>
            <label class="flex items-start gap-2 text-sm">
                <input
                    id="confirm"
                    name="confirm"
                    type="checkbox"
                    value="1"
                    required
                    class="border-input mt-0.5 size-4 rounded border"
                />
                <span>Подтверждаю удаление этого бюджета.</span>
            </label>
            <InputError :message="errors.confirm" />
            <Button
                type="submit"
                variant="destructive"
                :disabled="processing"
                @click="confirmDelete"
            >
                <Spinner v-if="processing" />
                Удалить бюджет
            </Button>
        </Form>

        <section class="flex flex-col gap-6">
            <div class="space-y-1">
                <h2 class="font-medium">Резервная копия</h2>
                <p class="text-muted-foreground text-sm">
                    Скачайте JSON-бэкап этого бюджета или восстановите данные из
                    файла.
                </p>
            </div>

            <Button
                as="a"
                variant="outline"
                href="/export/json"
                download="monetka-backup.json"
            >
                Скачать JSON
            </Button>

            <Form
                method="post"
                action="/workspaces/import"
                enctype="multipart/form-data"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
            >
                <div class="grid gap-2">
                    <Label for="file">Файл бэкапа</Label>
                    <input
                        id="file"
                        name="file"
                        type="file"
                        accept="application/json,.json"
                        required
                        class="border-input bg-background ring-offset-background focus-visible:ring-ring h-9 rounded-md border px-3 text-sm file:mr-3 file:border-0 file:bg-transparent file:text-sm file:font-medium focus-visible:ring-2 focus-visible:outline-none"
                    />
                    <InputError :message="errors.file" />
                </div>

                <label
                    v-if="isOwner"
                    class="flex items-start gap-2 text-sm"
                >
                    <input
                        id="replace"
                        name="replace"
                        type="checkbox"
                        value="1"
                        class="border-input mt-0.5 size-4 rounded border"
                    />
                    <span>
                        Заменить этот бюджет. Текущие счета, операции и остальные
                        данные будут уничтожены.
                    </span>
                </label>

                <Button type="submit" :disabled="processing" @click="confirmReplace">
                    <Spinner v-if="processing" />
                    Импортировать
                </Button>
            </Form>
        </section>
    </div>
</template>
