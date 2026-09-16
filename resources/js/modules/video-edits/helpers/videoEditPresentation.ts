import {
    BotIcon,
    CheckCircle2Icon,
    ClockIcon,
    CombineIcon,
    FilePenIcon,
    ListChecksIcon,
    Loader2Icon,
    ScissorsIcon,
    XCircleIcon,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import type { BadgeVariants } from '@/components/ui/badge';
import type {
    CutReason,
    ListedVideoEditStatus,
    ProcessingStage,
    SpeechCategory,
    VideoEditMode,
    VideoEditStatus,
} from '../types';

/**
 * How an edit reads in the UI, decided once for the table, the detail page,
 * the form and the confirmation modals.
 */

type Presentation = {
    label: string;
    variant: NonNullable<BadgeVariants['variant']>;
    icon: LucideIcon;
};

const STATUSES: Record<VideoEditStatus, Presentation> = {
    draft: { label: 'Uploading', variant: 'outline', icon: FilePenIcon },
    queued: { label: 'Queued', variant: 'outline', icon: ClockIcon },
    processing: {
        label: 'Processing',
        variant: 'secondary',
        icon: Loader2Icon,
    },
    awaiting_review: {
        label: 'Needs your review',
        variant: 'outline',
        icon: ListChecksIcon,
    },
    completed: {
        label: 'Completed',
        variant: 'default',
        icon: CheckCircle2Icon,
    },
    failed: { label: 'Failed', variant: 'destructive', icon: XCircleIcon },
};

export function videoEditStatusPresentation(
    status: VideoEditStatus,
): Presentation {
    return STATUSES[status];
}

export const LISTED_STATUSES: readonly ListedVideoEditStatus[] = [
    'queued',
    'processing',
    'awaiting_review',
    'completed',
    'failed',
];

/**
 * Queued and processing rows move on their own, so the UI polls them. An edit
 * awaiting review waits for the owner, so it is not polled.
 */
export function isActiveStatus(status: VideoEditStatus): boolean {
    return status === 'queued' || status === 'processing';
}

type ModePresentation = Presentation & {
    description: string;
    minimumSources: number;
};

/** `minimumSources` mirrors `VideoEditMode::minimumSources()`. */
const MODES: Record<VideoEditMode, ModePresentation> = {
    merge: {
        label: 'Merge',
        variant: 'outline',
        icon: CombineIcon,
        description: 'Join two or more clips in order, without cutting.',
        minimumSources: 2,
    },
    auto_edit: {
        label: 'Auto edit',
        variant: 'secondary',
        icon: ScissorsIcon,
        description: 'Remove silences, filler words and the ranges you mark.',
        minimumSources: 1,
    },
    ai_edit: {
        label: 'AI edit',
        variant: 'secondary',
        icon: BotIcon,
        description: 'Let the AI cut against your script and instructions.',
        minimumSources: 1,
    },
};

export function videoEditModePresentation(
    mode: VideoEditMode,
): ModePresentation {
    return MODES[mode];
}

export const VIDEO_EDIT_MODES: readonly VideoEditMode[] = [
    'merge',
    'auto_edit',
    'ai_edit',
];

const SPEECH_CATEGORIES: Record<SpeechCategory, string> = {
    filler: 'Hesitations (eh, mmm)',
    filler_word: 'Filler words (like, you know)',
    stutter: 'False starts',
    repetition: 'Repeated words',
    vocal_sound: 'Vocal sounds (laughs, coughs)',
};

export const SPEECH_CATEGORY_OPTIONS = (
    Object.entries(SPEECH_CATEGORIES) as [SpeechCategory, string][]
).map(([value, label]) => ({ value, label }));

const STAGES: Record<ProcessingStage, string> = {
    download: 'Downloading sources',
    merge: 'Merging clips',
    analysis: 'Analysing audio',
    audio_extraction: 'Extracting audio',
    transcription: 'Transcribing speech',
    speech_detection: 'Detecting filler speech',
    script_extraction: 'Reading the script',
    ai_analysis: 'AI analysis',
    plan_cuts: 'Planning cuts',
    render: 'Rendering',
    publish: 'Publishing result',
};

export function processingStageLabel(stage: ProcessingStage | null): string {
    return stage ? STAGES[stage] : 'Waiting for a worker';
}

const REASONS: Record<CutReason, string> = {
    silence: 'Silence',
    manual: 'Manual range',
    filler: 'Hesitation',
    filler_word: 'Filler word',
    stutter: 'False start',
    repetition: 'Repetition',
    vocal_sound: 'Vocal sound',
    pause_marker: 'Pause marker',
    retake: 'Retake',
    misspoken: 'Misspoken',
};

/** Applied cuts carry reasons as plain strings; unknown ones pass through. */
export function cutReasonLabel(reason: string): string {
    return reason in REASONS ? REASONS[reason as CutReason] : reason;
}

/**
 * Milliseconds → `4:07.3`. A cut is often under a second, so the review shows
 * tenths where the summary's whole seconds would make neighbours look equal.
 */
export function formatTimestampMs(ms: number): string {
    const tenths = Math.floor(ms / 100);
    const minutes = Math.floor(tenths / 600);
    const seconds = String(Math.floor((tenths % 600) / 10)).padStart(2, '0');

    return `${minutes}:${seconds}.${tenths % 10}`;
}

/** Milliseconds → `1:05:09` / `4:07`, or an em dash when unknown. */
export function formatDurationMs(ms: number | null | undefined): string {
    if (ms === null || ms === undefined) {
        return '—';
    }

    const totalSeconds = Math.round(ms / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = String(totalSeconds % 60).padStart(2, '0');

    return hours > 0
        ? `${hours}:${String(minutes).padStart(2, '0')}:${seconds}`
        : `${minutes}:${seconds}`;
}

/** ISO8601 → "3 Jun 2026", or an em dash when there is no timestamp. */
export function formatDateShort(iso: string | null): string {
    return iso
        ? new Intl.DateTimeFormat('en-US', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          }).format(new Date(iso))
        : '—';
}

/** ISO8601 → "3 Jun 2026, 14:05", or `null` when there is no timestamp. */
export function formatDateTime(iso: string | null): string | null {
    return iso
        ? new Intl.DateTimeFormat('en-US', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(iso))
        : null;
}

/**
 * The short reference users search by. `search` matches a uuid prefix on the
 * server, so the label shows exactly the characters that work as a query.
 */
export function videoEditReference(uuid: string): string {
    return uuid.slice(0, 8);
}
