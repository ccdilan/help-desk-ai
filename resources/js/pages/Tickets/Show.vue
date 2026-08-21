<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, MessageSquare } from '@lucide/vue';
import { computed, ref } from 'vue';
import TicketChatController from '@/actions/App/Http/Controllers/Api/TicketChatController';
import Heading from '@/components/Heading.vue';
import MarkdownPreview from '@/components/MarkdownPreview.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { postJson } from '@/lib/api';
import { create as ticketsCreate } from '@/routes/tickets';

type ChatMessage = {
    id: number;
    role: string;
    content: string;
};

type TicketReview = {
    id: number;
    title: string;
    initial_prompt: string;
    status: string;
    status_label: string;
    generated_prd: string | null;
    created_at: string | null;
    messages: ChatMessage[];
};

const props = defineProps<{
    ticket: TicketReview;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'SRS review', href: '/tickets' },
        ],
    },
});

const page = usePage();
const signing = ref(false);
const status = ref(props.ticket.status);

const isSignedOff = computed(() => status.value === 'signed_off');

const createdAt = computed(() =>
    props.ticket.created_at
        ? new Date(props.ticket.created_at).toLocaleDateString(undefined, {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
          })
        : null,
);

const signOff = async (): Promise<void> => {
    if (signing.value) {
        return;
    }

    signing.value = true;

    try {
        const response = await postJson(TicketChatController.signOff(props.ticket.id).url);

        if (response.ok) {
            status.value = 'signed_off';
        }
    } finally {
        signing.value = false;
    }
};
</script>

<template>
    <Head :title="`SRS review — ${ticket.title}`" />

    <h1 class="sr-only">SRS review and sign-off</h1>

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="SRS review"
                description="Compare your original request with the AI-generated specification"
            />

            <Badge :variant="isSignedOff ? 'default' : 'secondary'">
                {{ isSignedOff ? 'Signed off' : ticket.status_label }}
            </Badge>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Your original request</CardTitle>
                    <CardDescription>
                        {{ ticket.title }}<template v-if="createdAt"> · {{ createdAt }}</template>
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="text-sm whitespace-pre-wrap">{{ ticket.initial_prompt }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Generated SRS</CardTitle>
                    <CardDescription>
                        {{ ticket.generated_prd ? 'Structured requirements drafted by the AI analyst' : 'Not generated yet' }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="ticket.generated_prd"
                        class="max-h-[32rem] overflow-y-auto"
                    >
                        <MarkdownPreview :content="ticket.generated_prd" />
                    </div>
                    <p v-else class="text-muted-foreground text-sm">
                        Finish the discovery chat and generate the SRS to review it here.
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card v-if="ticket.messages.length > 0">
            <CardHeader>
                <CardTitle>Discovery conversation</CardTitle>
                <CardDescription>The full Q&amp;A transcript behind this specification</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <div
                    v-for="(message, index) in ticket.messages"
                    :key="message.id ?? index"
                    :class="[
                        'max-w-[85%] rounded-lg px-3 py-2 text-sm whitespace-pre-wrap',
                        message.role === 'user'
                            ? 'ml-auto bg-primary text-primary-foreground'
                            : 'bg-muted',
                    ]"
                >
                    {{ message.content }}
                </div>
            </CardContent>
        </Card>

        <div class="flex items-center gap-3">
            <Button
                v-if="!isSignedOff"
                :disabled="signing || !ticket.generated_prd"
                @click="signOff"
            >
                <CheckCircle2 class="mr-2 h-4 w-4" />
                Approve &amp; sign off
            </Button>

            <Button
                variant="outline"
                as-child
            >
                <a :href="ticketsCreate(page.props.currentTeam?.slug ?? '').url">
                    <MessageSquare class="mr-2 h-4 w-4" />
                    Back to discovery
                </a>
            </Button>
        </div>
    </div>
</template>
