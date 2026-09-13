<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type WorkspaceOption = {
    id: number;
    name: string;
    currency: string;
};

const page = usePage();

const current = computed(
    () => page.props.workspace as WorkspaceOption | null,
);
const workspaces = computed(
    () => (page.props.workspaces as WorkspaceOption[]) ?? [],
);

const switchTo = (id: number): void => {
    if (id === current.value?.id) {
        return;
    }

    router.post(`/workspaces/${id}/switch`);
};
</script>

<template>
    <div>
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="outline"
                    size="sm"
                    class="max-w-52 justify-between gap-2"
                >
                    <span class="truncate">{{ current?.name ?? 'Бюджет' }}</span>
                    <ChevronsUpDown class="size-4 shrink-0 opacity-60" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-56">
                <DropdownMenuItem
                    v-for="workspace in workspaces"
                    :key="workspace.id"
                    class="cursor-pointer"
                    @click="switchTo(workspace.id)"
                >
                    <span class="flex-1 truncate">{{ workspace.name }}</span>
                    <Check
                        v-if="workspace.id === current?.id"
                        class="size-4"
                    />
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem as-child>
                    <Link href="/workspaces/create" class="cursor-pointer">
                        Создать бюджет
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
