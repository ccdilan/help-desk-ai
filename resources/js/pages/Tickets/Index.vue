<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, FileText, MessageSquarePlus, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { create as ticketsCreate, show as ticketsShow } from '@/routes/tickets';

type TicketListItem = {
    id: number;
    title: string;
    status: string;
    status_label: string;
    project_name: string;
    has_srs: boolean;
    created_at: string | null;
};

const props = defineProps<{
    tickets: TicketListItem[];
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Tickets', href: '/tickets' },
        ],
    },
});

const formatDate = (value: string | null): string =>
    value
        ? new Date(value).toLocaleDateString(undefined, {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
          })
        : '';

const badgeVariant = (status: string): 'default' | 'secondary' | 'outline' => {
    if (status === 'review') {
        return 'default';
    }

    return status === 'signed_off' ? 'outline' : 'secondary';
};
</script>

<template>
    <Head title="Tickets" />

    <h1 class="sr-only">Tickets</h1>

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading
                title="Tickets"
                description="All requests raised by your team, with their AI-drafted specifications"
            />

            <Button as-child variant="outline">
                <a :href="ticketsCreate(teamSlug ?? '').url">
                    <MessageSquarePlus class="mr-2 h-4 w-4" />
                    New ticket
                </a>
            </Button>
        </div>

        <Card
            v-for="ticket in props.tickets"
            :key="ticket.id"
            class="transition-colors hover:border-foreground/20"
        >
            <CardHeader>
                <Link
                    :href="teamSlug ? ticketsShow({ current_team: teamSlug, ticket: ticket.id }).url : '#'"
                    class="flex items-start justify-between gap-4"
                >
                    <div class="flex flex-col gap-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <CardTitle class="hover:underline">{{ ticket.title }}</CardTitle>
                            <Badge :variant="badgeVariant(ticket.status)">
                                {{ ticket.status_label }}
                            </Badge>
                            <Badge v-if="ticket.has_srs" variant="outline">
                                <FileText class="mr-1 h-3 w-3" />
                                SRS ready
                            </Badge>
                        </div>
                        <CardDescription>
                            {{ ticket.project_name }}<template v-if="ticket.created_at"> · {{ formatDate(ticket.created_at) }}</template>
                        </CardDescription>
                    </div>
                    <ArrowRight class="text-muted-foreground mt-1 h-4 w-4 shrink-0" />
                </Link>
            </CardHeader>
        </Card>

        <div
            v-if="props.tickets.length === 0"
            class="text-muted-foreground flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center"
        >
            <Sparkles class="h-8 w-8" />
            <p class="text-sm">No tickets yet. Describe a request and the AI analyst will draft an SRS for you.</p>
            <Button as-child>
                <a :href="ticketsCreate(teamSlug ?? '').url">
                    Create your first ticket
                </a>
            </Button>
        </div>
    </div>
</template>
