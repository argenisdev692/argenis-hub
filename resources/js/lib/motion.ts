import type { Options, VariantType } from 'motion-v';

/**
 * A named set of animation states, keyed by variant label.
 *
 * motion-v re-exports framer-motion's `Variants` and `Transition`, but its own
 * components are typed against these narrower shapes instead — the framer types
 * allow a resolver signature motion-v does not accept, so importing the
 * familiar names produces a prop mismatch at every call site. These aliases
 * pin the system to what the components actually take.
 */
export type MotionVariants = Record<string, VariantType>;

/** The transition shape accepted by motion-v's components and variants. */
export type MotionTransition = NonNullable<Options<unknown>['transition']>;

/**
 * The JavaScript half of the motion system.
 *
 * `resources/css/globals.css` owns the CSS half (`--motion-*`). motion-v drives
 * animation from JS and cannot read custom properties, so the same numbers live
 * here in the unit motion-v expects — seconds, not milliseconds. The two halves
 * are one contract: if a duration changes in `globals.css`, it changes here too.
 *
 * Everything below is a plain value or a pure factory. Nothing in this file
 * touches the DOM or Vue reactivity, so it is safe to import from an SSR
 * render, and `MotionRoot.vue` stays the only place that holds behaviour.
 */

/** Mirrors `--motion-fast` / `--motion-base` / `--motion-slow`, in seconds. */
export const MOTION_DURATION = {
    fast: 0.14,
    base: 0.24,
    slow: 0.4,
} as const;

/**
 * Mirrors `--motion-ease: cubic-bezier(0.32, 0.72, 0, 1)`.
 *
 * A decelerating curve: most of the distance is covered early, then it settles.
 * It is what makes an entrance read as "arriving" rather than "sliding", and
 * using anything else for a reveal is what makes one section look bolted on.
 */
export const MOTION_EASE: [number, number, number, number] = [0.32, 0.72, 0, 1];

/** Mirrors `--motion-stagger`, in seconds. */
export const MOTION_STAGGER = 0.06;

/**
 * The stagger for operational screens, where the page is a tool rather than a
 * pitch.
 *
 * A dashboard is opened dozens of times a day, and the marketing rhythm turns
 * into a tax at that frequency: the landing page can afford to make someone
 * wait for a cascade because they see it once, and the dashboard cannot,
 * because they are looking for a number they already know the position of.
 * Fast enough to register as polish, too fast to wait through.
 */
export const MOTION_STAGGER_TIGHT = 0.04;

/** Mirrors `--motion-reveal-distance`, in pixels. */
export const MOTION_REVEAL_DISTANCE = 24;

/**
 * The house transition. Reach for a bare duration only when a specific element
 * needs to break step with the rest of the page — and say why in a comment.
 */
export const BRAND_TRANSITION: MotionTransition = {
    duration: MOTION_DURATION.base,
    ease: MOTION_EASE,
};

/**
 * A softer spring for anything the pointer drives — hover lifts, press
 * feedback, magnetic buttons.
 *
 * Springs beat durations for interaction because the user can interrupt them:
 * a spring re-targets from wherever it currently is, while a tweened transition
 * restarts and visibly stutters when someone moves the pointer mid-animation.
 */
export const INTERACTIVE_SPRING: MotionTransition = {
    type: 'spring',
    stiffness: 400,
    damping: 30,
};

/**
 * Viewport defaults for every scroll reveal.
 *
 * `once` because a section that re-animates each time it scrolls back into view
 * is a distraction on the second pass, and `amount: 0.25` so a tall section
 * starts moving when a quarter of it is showing instead of waiting for its
 * bottom edge — which, for anything taller than the viewport, never arrives.
 */
export const REVEAL_VIEWPORT = {
    once: true,
    amount: 0.25,
} as const;

/**
 * Parent variant for a group of revealed children.
 *
 * The parent animates nothing itself; it exists to sequence its children, which
 * is why `visible` carries only a transition. Pair it with `REVEAL_ITEM` on
 * each child and neither needs to know its own index.
 *
 * @param stagger Gap between children, in seconds.
 * @param delayChildren Pause before the first child starts, in seconds.
 */
export function staggerContainer(
    stagger: number = MOTION_STAGGER,
    delayChildren = 0,
): MotionVariants {
    return {
        hidden: {},
        visible: {
            transition: {
                staggerChildren: stagger,
                delayChildren,
            },
        },
    };
}

/**
 * The default child variant: rises into place while fading in.
 *
 * @param distance Travel distance in pixels. Defaults to the token.
 */
export function revealUp(
    distance: number = MOTION_REVEAL_DISTANCE,
): MotionVariants {
    return {
        hidden: { opacity: 0, y: distance },
        visible: {
            opacity: 1,
            y: 0,
            transition: BRAND_TRANSITION,
        },
    };
}

/**
 * Fade with no travel — for elements whose position is load-bearing, such as an
 * absolutely placed glow or a chart already anchored to its axis.
 */
export const REVEAL_FADE: MotionVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: BRAND_TRANSITION },
};

/**
 * Settles in from slightly under-scale. Suited to cards and tiles, where the
 * whole surface is the subject; avoid it on text, which goes blurry mid-scale
 * on non-integer device pixel ratios.
 */
export const REVEAL_SCALE: MotionVariants = {
    hidden: { opacity: 0, scale: 0.96 },
    visible: {
        opacity: 1,
        scale: 1,
        transition: BRAND_TRANSITION,
    },
};

/** The shared child variant, at the token distance. */
export const REVEAL_ITEM: MotionVariants = revealUp();
