<script setup>
import { computed } from 'vue';
import {
    AlertTriangle,
    Bell,
    Blocks,
    CalendarDays,
    ChartColumn,
    CheckCircle,
    Clock,
    Gauge,
    Images,
    Inbox,
    Kanban,
    LayoutDashboard,
    LineChart,
    ListTodo,
    Megaphone,
    MessageCircle,
    Package,
    Timer,
    UserRoundSearch,
    Users,
    Wallet,
    Workflow,
} from 'lucide-vue-next';

const icons = {
    dashboard: LayoutDashboard,
    clients: Users,
    leads: UserRoundSearch,
    pipeline: Kanban,
    campaigns: Megaphone,
    requests: Inbox,
    content: Images,
    calendar: CalendarDays,
    tasks: ListTodo,
    followups: Clock,
    time: Timer,
    workload: Gauge,
    delays: AlertTriangle,
    reports: ChartColumn,
    performance: LineChart,
    finance: Wallet,
    chat: MessageCircle,
    alerts: Bell,
    integrations: Blocks,
    automations: Workflow,
    reviews: CheckCircle,
    packages: Package,
};

const props = defineProps({
    name: { type: String, default: 'dashboard' },
    size: { type: [Number, String], default: 18 },
});

const icon = computed(() => icons[props.name] || LayoutDashboard);
</script>

<template>
    <component :is="icon" :size="Number(size)" :stroke-width="1.75" aria-hidden="true" />
</template>
