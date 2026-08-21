<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, Loader2, Send, Sparkles } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import TicketChatController from '@/actions/App/Http/Controllers/Api/TicketChatController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postJson } from '@/lib/api';

type InstallationOption = {
    id: number;
    repositoryFullName: string;
};

type ProjectOption = {
    id: number;
    name: string;
    installations: InstallationOption[];
};

type ChatMessage = {
    id: number | null;
    role: string;
    content: string;
};

type Ticket = {
    id: number;
    title: string;
    status: string;
    status_label: string;
    discovery_complete: boolean;
    generated_prd: string | null;
    messages: ChatMessage[];
};

const props = defineProps<{
    projects: ProjectOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'New ticket', href: '/tickets/create' },
        ],
    },
});

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam);

const step = ref<'details' | 'chat'>('details');
const projectId = ref<number | null>(null);
const installationId = ref<number | null>(null);
const title = ref('');
const initialPrompt = ref('');
const ticket = ref<Ticket | null>(null);
const draft = ref('');
const sending = ref(false);
const generating = ref(false);
const error = ref<string | null>(null);

const messagesEl = ref<HTMLElement | null>(null);

const selectedProject = computed(
    () => props.projects.find((project) => project.id === projectId.value) ?? null,
);

watch(projectId, () => {
    installationId.value = null;
});

const scrollToBottom = async (): Promise<void> => {
    await nextTick();

    messagesEl.value?.scrollTo({ top: messagesEl.value.scrollHeight });
};

const startDiscovery = async (): Promise<void> => {
    if (!projectId.value || !title.value.trim() || !initialPrompt.value.trim()) {
        return;
    }

    sending.value = true;
    error.value = null;

    try {
        const response = await postJson(TicketChatController.store(projectId.value).url, {
            title: title.value,
            initial_prompt: initialPrompt.value,
            ...(installationId.value ? { github_installation_id: installationId.value } : {}),
        });

        if (!response.ok) {
            error.value = 'Could not start the discovery session. Please try again.';

            return;
        }

        ticket.value = (await response.json()) as Ticket;
        step.value = 'chat';
        await scrollToBottom();
    } finally {
        sending.value = false;
    }
};

const sendMessage = async (): Promise<void> => {
    const content = draft.value.trim();

    if (!ticket.value || !content || sending.value) {
        return;
    }

    draft.value = '';
    ticket.value.messages.push({ id: null, role: 'user', content });
    sending.value = true;

    try {
        const response = await postJson(
            TicketChatController.addMessage(ticket.value.id).url,
            { content },
        );

        if (response.ok) {
            ticket.value = (await response.json()) as Ticket;
        }
    } finally {
        sending.value = false;
        await scrollToBottom();
    }
};

const generatePrd = async (): Promise<void> => {
    if (!ticket.value || generating.value) {
        return;
    }

    generating.value = true;

    try {
        const response = await postJson(TicketChatController.generatePrd(ticket.value.id).url);

        if (response.ok) {
            ticket.value = (await response.json()) as Ticket;
            router.visit(`/${currentTeam.value?.slug}/tickets/${ticket.value.id}`);
        }
    } finally {
        generating.value = false;
    }
};
</script>

<template>
    <Head title="New ticket" />

    <h1 class="sr-only">Create a new ticket</h1>

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <Heading
            title="New ticket"
            description="Describe your request and our AI analyst will ask clarifying questions before drafting an SRS"
        />

        <template v-if="step === 'details'">
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label for="project">Project</Label>
                    <select
                        id="project"
                        v-model.number="projectId"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        <option :value="null" disabled>Select a project</option>
                        <option v-for="project in projects" :key="project.id" :value="project.id">
                            {{ project.name }}
                        </option>
                    </select>
                </div>

                <div v-if="selectedProject && selectedProject.installations.length > 0" class="grid gap-2">
                    <Label for="repository">Repository</Label>
                    <select
                        id="repository"
                        v-model.number="installationId"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        <option :value="null">All repositories</option>
                        <option
                            v-for="installation in selectedProject.installations"
                            :key="installation.id"
                            :value="installation.id"
                        >
                            {{ installation.repositoryFullName }}
                        </option>
                    </select>
                    <p class="text-muted-foreground text-xs">
                        The AI analyst will only use the selected repository's code as context.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="title">Title</Label>
                    <Input
                        id="title"
                        v-model="title"
                        placeholder="Short summary of the request"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="prompt">What do you need?</Label>
                    <textarea
                        id="prompt"
                        v-model="initialPrompt"
                        rows="5"
                        class="border-input bg-background rounded-md border px-3 py-2 text-sm"
                        placeholder="Describe the problem or feature in your own words..."
                    />
                </div>

                <p v-if="error" class="text-destructive text-sm">{{ error }}</p>

                <Button class="w-fit" :disabled="sending || !projectId || !title || !initialPrompt" @click="startDiscovery">
                    <Loader2 v-if="sending" class="mr-2 h-4 w-4 animate-spin" />
                    Start discovery
                    <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
            </div>
        </template>

        <template v-else-if="ticket">
            <Card class="flex h-[32rem] flex-col">
                <CardHeader>
                    <CardTitle>{{ ticket.title }}</CardTitle>
                    <CardDescription>
                        {{
                            ticket.discovery_complete
                                ? 'Discovery complete — review the summary and generate the SRS'
                                : "AI discovery chat — answer the analyst's questions"
                        }}
                    </CardDescription>
                </CardHeader>

                <div ref="messagesEl" class="flex-1 space-y-3 overflow-y-auto">
                    <div
                        v-for="(message, index) in ticket.messages"
                        :key="message.id ?? index"
                        :class="[
                            'max-w-[80%] rounded-lg px-3 py-2 text-sm',
                            message.role === 'user'
                                ? 'ml-auto bg-primary text-primary-foreground'
                                : 'bg-muted',
                        ]"
                    >
                        {{ message.content }}
                    </div>
                    <div v-if="sending" class="text-muted-foreground text-sm">Analyst is typing…</div>
                </div>

                <div class="flex items-center gap-2 border-t p-3">
                    <Input
                        v-model="draft"
                        placeholder="Type your reply…"
                        @keydown.enter.prevent="sendMessage"
                    />
                    <Button size="icon" :disabled="sending || !draft.trim()" @click="sendMessage">
                        <Send class="h-4 w-4" />
                    </Button>
                </div>
            </Card>

            <div v-if="ticket.discovery_complete" class="flex flex-col gap-3">
                <div
                    class="border-emerald-500/40 bg-emerald-500/10 flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                >
                    <CheckCircle2 class="text-emerald-600 h-4 w-4" />
                    Requirements captured — the analyst has stopped asking questions.
                </div>
                <Button size="lg" :disabled="generating" @click="generatePrd">
                    <Loader2 v-if="generating" class="mr-2 h-4 w-4 animate-spin" />
                    <Sparkles v-else class="mr-2 h-4 w-4" />
                    Generate SRS &amp; submit
                </Button>
            </div>
            <Button v-else class="w-fit" :disabled="generating" @click="generatePrd">
                <Sparkles class="mr-2 h-4 w-4" />
                Generate SRS
            </Button>
        </template>
    </div>
</template>
