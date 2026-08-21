type JsonResponse = Record<string, unknown>;

const csrfToken = (): string =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

export async function postJson(url: string, data: Record<string, unknown> = {}): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify(data),
    });
}

export async function getJson(url: string): Promise<Response> {
    return fetch(url, {
        headers: { Accept: 'application/json' },
    });
}

export type { JsonResponse };
