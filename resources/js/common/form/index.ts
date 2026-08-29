/**
 * Form kit — shadcn-vue `field` primitives + TanStack Form + Zod 4.
 *
 * The layout layer (`@/components/ui/field`) is validation-agnostic, so the
 * schema library stays swappable: anything implementing Standard Schema works.
 */

export { default as AddressInput } from './AddressInput.vue';
export { default as AppField } from './AppField.vue';
export { default as AvatarCropperInput } from './AvatarCropperInput.vue';
export { default as CurrencyInput } from './CurrencyInput.vue';
export { default as DatePickerInput } from './DatePickerInput.vue';
export { default as FileDropzone } from './FileDropzone.vue';
export { default as FilterSelect } from './FilterSelect.vue';
export { default as FormDialog } from './FormDialog.vue';
export { default as FormSheet } from './FormSheet.vue';
export { default as PhoneInput } from './PhoneInput.vue';
export { default as SignaturePadInput } from './SignaturePadInput.vue';
export { default as SortableList } from './SortableList.vue';
export { default as TextField } from './TextField.vue';

export type { DialogForm } from './FormDialog.vue';
export type { FilterSelectOption, FilterSelectValue } from './FilterSelect.vue';
export type { PlaceSuggestion, ResolvedAddress } from './useGooglePlaces';
export { useGooglePlaces } from './useGooglePlaces';
export {
    applyServerErrors,
    fieldErrorMessages,
    isFieldInvalid,
    useAppForm,
} from './useAppForm';
export type {
    FormTarget,
    HttpMethod,
    InertiaSubmitOptions,
    StandardSchema,
} from './useAppForm';
