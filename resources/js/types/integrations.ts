export type InstallationStatus = 'pending' | 'syncing' | 'synced' | 'failed';

export type GithubInstallationSummary = {
    id: number;
    repositoryFullName: string;
    status: InstallationStatus;
    lastSyncedAt: string | null;
};

export type ProjectIntegration = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    installations: GithubInstallationSummary[];
};
