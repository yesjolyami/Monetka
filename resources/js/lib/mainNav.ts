import {
    ChartColumn,
    Gauge,
    LayoutGrid,
    MessageCircle,
    QrCode,
    Receipt,
    Scale,
    ServerCog,
    Settings,
    Target,
    Wallet,
} from '@lucide/vue';
import type { NavItem } from '@/types';

export const mainNavItems: NavItem[] = [
    {
        title: 'Обзор',
        href: '/overview',
        icon: LayoutGrid,
    },
    {
        title: 'Операции',
        href: '/transactions',
        icon: Receipt,
    },
    {
        title: 'Счета',
        href: '/accounts',
        icon: Wallet,
    },
    {
        title: 'Цели',
        href: '/goals',
        icon: Target,
    },
    {
        title: 'Долги',
        href: '/debts',
        icon: Scale,
    },
    {
        title: 'Лимиты',
        href: '/limits',
        icon: Gauge,
    },
    {
        title: 'Чек ФНС',
        href: '/receipts',
        icon: QrCode,
    },
    {
        title: 'Помощник',
        href: '/assistant',
        icon: MessageCircle,
    },
    {
        title: 'Статистика',
        href: '/stats',
        icon: ChartColumn,
    },
    {
        title: 'Настройки бюджета',
        href: '/workspaces/settings',
        icon: Settings,
    },
];

export function mainNavItemsForUser(isAdmin: boolean): NavItem[] {
    if (!isAdmin) {
        return mainNavItems;
    }

    const items = [...mainNavItems];
    const settingsIndex = items.findIndex(
        (item) => item.href === '/workspaces/settings',
    );

    items.splice(settingsIndex + 1, 0, {
        title: 'AI-админка',
        href: '/admin/ai',
        icon: ServerCog,
    });

    return items;
}
