import {
    ChartColumn,
    Gauge,
    LayoutGrid,
    Receipt,
    Scale,
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
