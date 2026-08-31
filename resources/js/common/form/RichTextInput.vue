<script setup lang="ts">
import {
    BoldIcon,
    CodeIcon,
    Heading2Icon,
    Heading3Icon,
    ItalicIcon,
    LinkIcon,
    ListIcon,
    ListOrderedIcon,
    MinusIcon,
    QuoteIcon,
    Redo2Icon,
    RemoveFormattingIcon,
    SquareCodeIcon,
    StrikethroughIcon,
    UnderlineIcon,
    Undo2Icon,
    UnlinkIcon,
} from '@lucide/vue';
import { Placeholder } from '@tiptap/extensions';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import type { Component } from 'vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { Toggle } from '@/components/ui/toggle';
import { sanitizeRichText } from '@/lib/sanitize';
import { cn } from '@/lib/utils';

/**
 * The long-form writing surface — Tiptap v3 behind the same prop shape as every
 * other control in the form kit, so it drops into `AppField` beside
 * `TextField` and `DatePickerInput` without special-casing.
 *
 * ## Why a real editor rather than a textarea
 *
 * The value is HTML, and it is HTML the author has to be able to see while
 * writing: a blog body is headings, emphasis and links, and judging those from
 * raw markup is the thing a WYSIWYG exists to stop. Tiptap is headless, so the
 * chrome below is this project's own shadcn primitives on this project's
 * tokens — no vendor stylesheet lands in the bundle.
 *
 * ## The two directions of the model
 *
 * `onUpdate` pushes the editor's HTML into the model on every transaction. The
 * watcher going the other way exists for the AI assist panel, which replaces
 * the whole body at once; it compares against `getHTML()` first so a
 * keystroke's own echo never round-trips back through `setContent` and eats the
 * cursor. `emitUpdate: false` keeps that write from re-entering `onUpdate`.
 *
 * ## Trust
 *
 * Content coming IN is sanitized; content going OUT is not, and the asymmetry
 * is the point. What the editor emits is already constrained by ProseMirror's
 * schema — the extensions below are the only nodes and marks it can build — and
 * the server validates it again regardless. What it is *seeded* with is a
 * different story: that HTML comes from a database row or an AI provider's
 * response, and loading it is the moment a payload would get its chance.
 *
 * ProseMirror does drop unknown nodes when it converts the parsed DOM into a
 * document, but it has to build real elements to parse them, and a browser
 * starts fetching `<img src=x onerror=…>` the moment such an element exists —
 * detached or not. Dropping the node afterwards is too late. So every inbound
 * write goes through `sanitizeRichText` first, and `onUpdate` never does (it
 * would fight the editor's own output on every keystroke).
 */

const {
    id,
    ariaDescribedby,
    ariaInvalid = false,
    ariaLabel = 'Content',
    placeholder = 'Write the post…',
    disabled = false,
    minHeight = '18rem',
    class: className,
} = defineProps<{
    /** Wire to `AppField`'s `control.id` so the field label points here. */
    id?: string;
    ariaDescribedby?: string;
    ariaInvalid?: boolean;
    ariaLabel?: string;
    placeholder?: string;
    disabled?: boolean;
    /** Any CSS length. The surface still grows past it as the draft does. */
    minHeight?: string;
    class?: string;
}>();

const model = defineModel<string>({ default: '' });

const editor = useEditor({
    content: sanitizeRichText(model.value),
    editable: !disabled,
    extensions: [
        StarterKit.configure({
            // H1 belongs to the post title field; letting the body mint a
            // second one is the most common way a page ends up with two.
            heading: { levels: [2, 3, 4] },
        }),
        Placeholder.configure({ placeholder: () => placeholder }),
    ],
    editorProps: {
        attributes: {
            class: 'rich-text-content rich-text-editable px-3.5 py-3',
            role: 'textbox',
            'aria-multiline': 'true',
        },
    },
    onUpdate({ editor: instance }) {
        model.value = instance.getHTML();
    },
});

watch(model, (value) => {
    const instance = editor.value;

    if (!instance || value === instance.getHTML()) {
        return;
    }

    instance.commands.setContent(sanitizeRichText(value), {
        emitUpdate: false,
    });
});

watch(
    () => disabled,
    (isDisabled) => editor.value?.setEditable(!isDisabled),
);

/**
 * ARIA wiring is applied to the ProseMirror node directly rather than through
 * `editorProps`, which is only read when the view is built. `class` is left out
 * on purpose — ProseMirror owns that attribute and rewrites it.
 */
watch(
    [
        editor,
        () => id,
        () => ariaDescribedby,
        () => ariaInvalid,
        () => ariaLabel,
    ],
    ([instance, controlId, describedBy, invalid, label]) => {
        const dom = instance?.view.dom;

        if (!dom) {
            return;
        }

        const attributes: Record<string, string | undefined> = {
            id: controlId,
            'aria-describedby': describedBy,
            'aria-invalid': String(invalid),
            'aria-label': label,
        };

        for (const [name, value] of Object.entries(attributes)) {
            if (value === undefined) {
                dom.removeAttribute(name);
            } else {
                dom.setAttribute(name, value);
            }
        }
    },
    { immediate: true },
);

/** Every command focuses first, so the caret never gets left in the toolbar. */
function chain() {
    return editor.value?.chain().focus();
}

type ToolbarButton = {
    id: string;
    label: string;
    icon: Component;
    run: () => void;
    isDisabled?: () => boolean;
};

type ToolbarToggle = ToolbarButton & { isActive: () => boolean };

const historyButtons: ToolbarButton[] = [
    {
        id: 'undo',
        label: 'Undo',
        icon: Undo2Icon,
        run: () => chain()?.undo().run(),
        isDisabled: () => !editor.value?.can().undo(),
    },
    {
        id: 'redo',
        label: 'Redo',
        icon: Redo2Icon,
        run: () => chain()?.redo().run(),
        isDisabled: () => !editor.value?.can().redo(),
    },
];

const markToggles: ToolbarToggle[] = [
    {
        id: 'bold',
        label: 'Bold',
        icon: BoldIcon,
        isActive: () => editor.value?.isActive('bold') ?? false,
        run: () => chain()?.toggleBold().run(),
    },
    {
        id: 'italic',
        label: 'Italic',
        icon: ItalicIcon,
        isActive: () => editor.value?.isActive('italic') ?? false,
        run: () => chain()?.toggleItalic().run(),
    },
    {
        id: 'underline',
        label: 'Underline',
        icon: UnderlineIcon,
        isActive: () => editor.value?.isActive('underline') ?? false,
        run: () => chain()?.toggleUnderline().run(),
    },
    {
        id: 'strike',
        label: 'Strikethrough',
        icon: StrikethroughIcon,
        isActive: () => editor.value?.isActive('strike') ?? false,
        run: () => chain()?.toggleStrike().run(),
    },
    {
        id: 'code',
        label: 'Inline code',
        icon: CodeIcon,
        isActive: () => editor.value?.isActive('code') ?? false,
        run: () => chain()?.toggleCode().run(),
    },
];

const blockToggles: ToolbarToggle[] = [
    {
        id: 'h2',
        label: 'Heading 2',
        icon: Heading2Icon,
        isActive: () =>
            editor.value?.isActive('heading', { level: 2 }) ?? false,
        run: () => chain()?.toggleHeading({ level: 2 }).run(),
    },
    {
        id: 'h3',
        label: 'Heading 3',
        icon: Heading3Icon,
        isActive: () =>
            editor.value?.isActive('heading', { level: 3 }) ?? false,
        run: () => chain()?.toggleHeading({ level: 3 }).run(),
    },
    {
        id: 'bullet-list',
        label: 'Bullet list',
        icon: ListIcon,
        isActive: () => editor.value?.isActive('bulletList') ?? false,
        run: () => chain()?.toggleBulletList().run(),
    },
    {
        id: 'ordered-list',
        label: 'Numbered list',
        icon: ListOrderedIcon,
        isActive: () => editor.value?.isActive('orderedList') ?? false,
        run: () => chain()?.toggleOrderedList().run(),
    },
    {
        id: 'blockquote',
        label: 'Quote',
        icon: QuoteIcon,
        isActive: () => editor.value?.isActive('blockquote') ?? false,
        run: () => chain()?.toggleBlockquote().run(),
    },
    {
        id: 'code-block',
        label: 'Code block',
        icon: SquareCodeIcon,
        isActive: () => editor.value?.isActive('codeBlock') ?? false,
        run: () => chain()?.toggleCodeBlock().run(),
    },
];

const trailingButtons: ToolbarButton[] = [
    {
        id: 'horizontal-rule',
        label: 'Divider',
        icon: MinusIcon,
        run: () => chain()?.setHorizontalRule().run(),
    },
    {
        id: 'clear-formatting',
        label: 'Clear formatting',
        icon: RemoveFormattingIcon,
        run: () => chain()?.unsetAllMarks().clearNodes().run(),
    },
];

// ---- links -----------------------------------------------------------------

const linkOpen = ref(false);
const linkDraft = ref('');
const linkError = ref<string | null>(null);

const hasLink = computed(() => editor.value?.isActive('link') ?? false);

/**
 * Accepts what people actually paste — `example.com`, `/blog/x`, `#section`, a
 * full URL — and rejects the schemes that turn a link into a script sink.
 *
 * A bare host is prefixed with `https://` rather than guessed at, which is what
 * keeps `example.com:8080/path` from being misread as a custom scheme.
 */
function normalizeUrl(raw: string): string | null {
    const value = raw.trim();

    if (!value) {
        return null;
    }

    if (/^(?:javascript|data|vbscript|file|blob):/i.test(value)) {
        return null;
    }

    if (value.startsWith('/') || value.startsWith('#')) {
        return value;
    }

    const hasScheme = /^(?:[a-z][a-z0-9+.-]*:\/\/|mailto:)/i.test(value);

    try {
        const url = new URL(hasScheme ? value : `https://${value}`);

        return ['http:', 'https:', 'mailto:'].includes(url.protocol)
            ? url.toString()
            : null;
    } catch {
        return null;
    }
}

watch(linkOpen, (isOpen) => {
    linkError.value = null;

    if (isOpen) {
        linkDraft.value = String(
            editor.value?.getAttributes('link').href ?? '',
        );
    }
});

function applyLink(): void {
    const href = normalizeUrl(linkDraft.value);

    if (!href) {
        linkError.value = 'Enter a valid http(s) or mailto link.';

        return;
    }

    // `extendMarkRange` is what makes this work with the caret merely inside a
    // link, instead of requiring the whole anchor to be selected first.
    chain()?.extendMarkRange('link').setLink({ href }).run();
    linkOpen.value = false;
}

function removeLink(): void {
    chain()?.extendMarkRange('link').unsetLink().run();
    linkOpen.value = false;
}

// ---- reading stats ---------------------------------------------------------

/** 225 wpm — the midpoint of the usual adult silent-reading range. */
const WORDS_PER_MINUTE = 225;

const stats = computed(() => {
    const text = editor.value?.getText().trim() ?? '';
    const words = text ? text.split(/\s+/).length : 0;

    return {
        words,
        minutes:
            words === 0 ? 0 : Math.max(1, Math.round(words / WORDS_PER_MINUTE)),
    };
});
</script>

<template>
    <div
        :class="
            cn(
                'rounded-md border border-input bg-transparent shadow-xs transition-[color,box-shadow]',
                'focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50',
                ariaInvalid &&
                    'border-destructive ring-destructive/20 dark:ring-destructive/40',
                disabled && 'pointer-events-none opacity-50',
                className,
            )
        "
    >
        <div
            role="toolbar"
            :aria-label="`${ariaLabel} formatting`"
            class="flex flex-wrap items-center gap-0.5 border-b border-border px-1.5 py-1"
        >
            <Button
                v-for="button in historyButtons"
                :key="button.id"
                type="button"
                variant="ghost"
                size="icon"
                class="size-8"
                :disabled="disabled || button.isDisabled?.()"
                :aria-label="button.label"
                :title="button.label"
                @click="button.run()"
            >
                <component
                    :is="button.icon"
                    class="size-4"
                    aria-hidden="true"
                />
            </Button>

            <Separator orientation="vertical" class="mx-1 !h-5" />

            <Toggle
                v-for="toggle in markToggles"
                :key="toggle.id"
                size="sm"
                :pressed="toggle.isActive()"
                :disabled="disabled"
                :aria-label="toggle.label"
                :title="toggle.label"
                @update:pressed="toggle.run()"
            >
                <component
                    :is="toggle.icon"
                    class="size-4"
                    aria-hidden="true"
                />
            </Toggle>

            <Separator orientation="vertical" class="mx-1 !h-5" />

            <Toggle
                v-for="toggle in blockToggles"
                :key="toggle.id"
                size="sm"
                :pressed="toggle.isActive()"
                :disabled="disabled"
                :aria-label="toggle.label"
                :title="toggle.label"
                @update:pressed="toggle.run()"
            >
                <component
                    :is="toggle.icon"
                    class="size-4"
                    aria-hidden="true"
                />
            </Toggle>

            <Separator orientation="vertical" class="mx-1 !h-5" />

            <Popover v-model:open="linkOpen">
                <PopoverTrigger as-child>
                    <Toggle
                        size="sm"
                        :pressed="hasLink"
                        :disabled="disabled"
                        aria-label="Insert link"
                        title="Insert link"
                    >
                        <LinkIcon class="size-4" aria-hidden="true" />
                    </Toggle>
                </PopoverTrigger>

                <PopoverContent class="w-80 p-3" align="start">
                    <form
                        class="flex flex-col gap-2"
                        @submit.prevent="applyLink"
                    >
                        <label
                            class="text-xs font-medium text-muted-foreground"
                            for="rich-text-link-url"
                        >
                            Link URL
                        </label>

                        <Input
                            id="rich-text-link-url"
                            v-model="linkDraft"
                            type="text"
                            inputmode="url"
                            placeholder="https://example.com"
                            :aria-invalid="Boolean(linkError)"
                            :aria-describedby="
                                linkError ? 'rich-text-link-error' : undefined
                            "
                        />

                        <p
                            v-if="linkError"
                            id="rich-text-link-error"
                            class="text-xs text-destructive"
                        >
                            {{ linkError }}
                        </p>

                        <div class="flex justify-end gap-2 pt-1">
                            <Button
                                v-if="hasLink"
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="removeLink"
                            >
                                Remove
                            </Button>
                            <Button type="submit" size="sm">Apply</Button>
                        </div>
                    </form>
                </PopoverContent>
            </Popover>

            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-8"
                :disabled="disabled || !hasLink"
                aria-label="Remove link"
                title="Remove link"
                @click="removeLink"
            >
                <UnlinkIcon class="size-4" aria-hidden="true" />
            </Button>

            <Separator orientation="vertical" class="mx-1 !h-5" />

            <Button
                v-for="button in trailingButtons"
                :key="button.id"
                type="button"
                variant="ghost"
                size="icon"
                class="size-8"
                :disabled="disabled"
                :aria-label="button.label"
                :title="button.label"
                @click="button.run()"
            >
                <component
                    :is="button.icon"
                    class="size-4"
                    aria-hidden="true"
                />
            </Button>

            <p
                class="ms-auto pe-1 text-xs text-muted-foreground tabular-nums"
                aria-live="polite"
            >
                {{ stats.words }} {{ stats.words === 1 ? 'word' : 'words' }}
                <span v-if="stats.minutes > 0">
                    · {{ stats.minutes }} min read</span
                >
            </p>
        </div>

        <EditorContent
            :editor="editor"
            class="max-h-[36rem] overflow-y-auto"
            :style="{ '--rich-text-min-height': minHeight }"
        />
    </div>
</template>
