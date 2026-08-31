<script setup lang="ts">
import { computed } from 'vue';
import { sanitizeRichText } from '@/lib/sanitize';
import { cn } from '@/lib/utils';

/**
 * Renders a post body as formatted HTML.
 *
 * This exists as its own component rather than as a `v-html` binding in the
 * page for one reason: it makes sanitation impossible to forget. `v-html` is
 * the sink OWASP A03 is about, and the one on the next page — added in a hurry,
 * six months from now — is the one that ships unsanitized. There is no way to
 * render post content in this module without going through here.
 *
 * The `rich-text-content` utility is the same one the editor's writing surface
 * wears, so the draft and the published article agree on what an H2 looks like.
 */

const { content, class: className } = defineProps<{
    content: string | null;
    class?: string;
}>();

const html = computed(() => sanitizeRichText(content));
</script>

<template>
    <!-- eslint-disable-next-line vue/no-v-html -- sanitized above; see the docblock. -->
    <div :class="cn('rich-text-content', className)" v-html="html" />
</template>
