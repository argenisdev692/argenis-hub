<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { answers } from '@/routes/cv-studio/audits';
import { audit } from '@/routes/cv-studio/cvs';
import { index as postingsIndex } from '@/routes/cv-studio/postings';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'CV Studio', href: postingsIndex() },
            { title: 'CV audit' },
        ],
    },
});

type AuditResult = {
    uuid: string;
    verdict: string | null;
    verdict_reasons: string[] | null;
    strengths: string[] | null;
    improvements: string[] | null;
    keyword_gaps: string[] | null;
    xyz_gaps: string[] | null;
};

const form = useForm({
    cv_uuid: '',
    target_job_title: '',
});

const result = ref<AuditResult | null>(null);

const answersForm = useForm<Record<string, string>>({});

function runAudit(): void {
    form.post(audit.url(), {
        onSuccess: (page) => {
            result.value =
                (page.props as unknown as { audit: AuditResult }).audit ?? null;
        },
    });
}

const metricQuestions = computed<string[]>(() => {
    const gaps = result.value?.xyz_gaps ?? [];

    return gaps.filter((gap) => gap.endsWith('?')).slice(0, 6);
});

function submitAnswers(): void {
    if (!result.value) {
        return;
    }

    answersForm
        .transform(() => ({
            answers: { ...answersForm.data() },
        }))
        .post(answers(result.value.uuid).url);
}
</script>

<template>
    <Head title="CV Studio · CV audit" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                Audit my CV
            </h1>
            <p class="text-sm text-muted-foreground">
                A blunt recruiter-scan verdict plus per-check ATS readability.
                Every number is a heuristic — never a vendor ATS score.
            </p>
        </header>

        <form
            class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4"
            @submit.prevent="runAudit"
        >
            <label class="flex flex-col gap-1 text-sm">
                Target role (tailors the headline verdict)
                <input
                    v-model="form.target_job_title"
                    type="text"
                    class="rounded-lg border border-border bg-background p-2"
                    placeholder="Senior Fullstack Developer"
                />
            </label>
            <Button type="submit" class="w-fit" :disabled="form.processing">
                Run audit
            </Button>
        </form>

        <section
            v-if="result"
            class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4"
            aria-label="Audit result"
        >
            <div>
                <h2 class="text-sm font-semibold">10-second verdict</h2>
                <p class="text-lg font-semibold">{{ result.verdict }}</p>
                <ul class="mt-1 flex flex-col gap-1 text-sm">
                    <li
                        v-for="reason in result.verdict_reasons ?? []"
                        :key="reason"
                    >
                        · {{ reason }}
                    </li>
                </ul>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <h3 class="text-sm font-semibold">Strengths</h3>
                    <ul class="flex flex-col gap-1 text-sm">
                        <li
                            v-for="strength in result.strengths ?? []"
                            :key="strength"
                        >
                            · {{ strength }}
                        </li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-semibold">Improvements</h3>
                    <ul class="flex flex-col gap-1 text-sm">
                        <li
                            v-for="improvement in result.improvements ?? []"
                            :key="improvement"
                        >
                            · {{ improvement }}
                        </li>
                    </ul>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-semibold">
                    Bullets lacking a measurable result
                </h3>
                <ul class="flex flex-col gap-1 text-sm">
                    <li v-for="gap in result.xyz_gaps ?? []" :key="gap">
                        · {{ gap }}
                    </li>
                </ul>
            </div>

            <form
                v-if="metricQuestions.length > 0"
                class="flex flex-col gap-3"
                @submit.prevent="submitAnswers"
            >
                <h3 class="text-sm font-semibold">
                    Metric questions — answers feed the rewrite
                </h3>
                <label
                    v-for="question in metricQuestions"
                    :key="question"
                    class="flex flex-col gap-1 text-sm"
                >
                    {{ question }}
                    <input
                        v-model="answersForm[question]"
                        type="text"
                        class="rounded-lg border border-border bg-background p-2"
                    />
                </label>
                <Button
                    type="submit"
                    class="w-fit"
                    :disabled="answersForm.processing"
                >
                    Save answers
                </Button>
            </form>
        </section>
    </div>
</template>
