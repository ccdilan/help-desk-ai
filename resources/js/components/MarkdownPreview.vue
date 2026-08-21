<script setup lang="ts">
import DOMPurify from 'dompurify';
import { marked } from 'marked';
import { computed } from 'vue';

const props = defineProps<{
    content: string;
}>();

marked.setOptions({
    gfm: true,
    breaks: true,
});

const html = computed(() =>
    DOMPurify.sanitize(marked.parse(props.content, { async: false })),
);
</script>

<template>
    <div class="markdown-preview text-sm" v-html="html" />
</template>

<style scoped>
.markdown-preview :deep(h1) {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 1rem 0 0.5rem;
}

.markdown-preview :deep(h1:first-child),
.markdown-preview :deep(h2:first-child),
.markdown-preview :deep(h3:first-child) {
    margin-top: 0;
}

.markdown-preview :deep(h2) {
    font-size: 1.0625rem;
    font-weight: 600;
    margin: 1rem 0 0.5rem;
}

.markdown-preview :deep(h3),
.markdown-preview :deep(h4) {
    font-size: 0.9375rem;
    font-weight: 600;
    margin: 0.875rem 0 0.375rem;
}

.markdown-preview :deep(p) {
    margin: 0.5rem 0;
}

.markdown-preview :deep(ul),
.markdown-preview :deep(ol) {
    margin: 0.5rem 0;
    padding-left: 1.25rem;
}

.markdown-preview :deep(ul) {
    list-style: disc;
}

.markdown-preview :deep(ol) {
    list-style: decimal;
}

.markdown-preview :deep(li) {
    margin: 0.25rem 0;
}

.markdown-preview :deep(li > ul),
.markdown-preview :deep(li > ol) {
    margin: 0.25rem 0;
}

.markdown-preview :deep(code) {
    background-color: var(--muted);
    border-radius: 0.25rem;
    padding: 0.125rem 0.375rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.8125rem;
}

.markdown-preview :deep(pre) {
    background-color: var(--muted);
    border-radius: 0.375rem;
    overflow-x: auto;
    padding: 0.75rem 1rem;
    margin: 0.5rem 0;
}

.markdown-preview :deep(pre code) {
    background-color: transparent;
    padding: 0;
}

.markdown-preview :deep(blockquote) {
    border-left: 3px solid var(--border);
    color: var(--muted-foreground);
    margin: 0.5rem 0;
    padding-left: 0.75rem;
}

.markdown-preview :deep(hr) {
    border-color: var(--border);
    margin: 1rem 0;
}

.markdown-preview :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin: 0.5rem 0;
    font-size: 0.8125rem;
}

.markdown-preview :deep(th),
.markdown-preview :deep(td) {
    border: 1px solid var(--border);
    padding: 0.375rem 0.625rem;
    text-align: left;
}

.markdown-preview :deep(a) {
    color: var(--primary);
    text-decoration: underline;
}
</style>
