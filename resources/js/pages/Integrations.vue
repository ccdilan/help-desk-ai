<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { ref } from 'vue';
import InstallationSyncController from '@/actions/App/Http/Controllers/Api/InstallationSyncController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { connect as githubConnectUrl } from '@/routes/github';
import type { GithubInstallationSummary, InstallationStatus, ProjectIntegration } from '@/types';

defineProps<{
    projects: ProjectIntegration[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Integrations', href: '/integrations' },
        ],
    },
});

const syncingIds = ref<number[]>([]);

const statusVariant = (status: InstallationStatus): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'synced') {
        return 'default';
    }

    if (status === 'failed') {
        return 'destructive';
    }

    return status === 'syncing' ? 'secondary' : 'outline';
};

const isSyncing = (installation: GithubInstallationSummary): boolean =>
    installation.status === 'syncing' || syncingIds.value.includes(installation.id);

const resync = (installation: GithubInstallationSummary): void => {
    syncingIds.value = [...syncingIds.value, installation.id];

    router.post(
        InstallationSyncController.store(installation).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                syncingIds.value = syncingIds.value.filter((id) => id !== installation.id);
            },
        },
    );
};
</script>

<template>
    <Head title="Integrations" />

    <h1 class="sr-only">Integrations</h1>

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Integrations"
                description="Manage connected GitHub repositories and their sync status"
            />

            <Button as-child>
                <a :href="githubConnectUrl().url">
                    <RefreshCw class="mr-2 h-4 w-4" />
                    Connect GitHub
                </a>
            </Button>
        </div>

        <Card v-for="project in projects" :key="project.id">
            <CardHeader>
                <CardTitle>{{ project.name }}</CardTitle>
                <CardDescription>{{ project.description ?? 'No description' }}</CardDescription>
            </CardHeader>
            <CardContent>
                <ul
                    v-if="project.installations.length > 0"
                    class="divide-y rounded-md border"
                >
                    <li
                        v-for="installation in project.installations"
                        :key="installation.id"
                        class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ installation.repositoryFullName }}
                            </p>
                            <p class="text-muted-foreground text-sm">
                                {{
                                    installation.lastSyncedAt
                                        ? `Last synced ${new Date(installation.lastSyncedAt).toLocaleString()}`
                                        : 'Never synced'
                                }}
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <Badge :variant="statusVariant(installation.status)">
                                {{ isSyncing(installation) ? 'syncing' : installation.status }}
                            </Badge>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="isSyncing(installation)"
                                @click="resync(installation)"
                            >
                                <RefreshCw class="mr-2 h-4 w-4" />
                                Re-sync
                            </Button>
                        </div>
                    </li>
                </ul>
                <p v-else class="text-muted-foreground text-sm">
                    No repositories connected yet.
                </p>
            </CardContent>
        </Card>

        <p
            v-if="projects.length === 0"
            class="text-muted-foreground text-sm"
        >
            No projects yet. Create a project to connect repositories.
        </p>
    </div>
</template>
