/**
 * Table kit — shadcn-vue `table` + `pagination` primitives, one opinion applied.
 *
 * The pieces are separate on purpose. A screen that needs a table and no
 * paginator (a fixed set of tax rates) imports one; a screen that paginates a
 * card grid imports the other. Fusing them into a single "DataTable with
 * built-in paging" component is what forces the first screen to fake a
 * `PaginationMeta` it does not have.
 */

export { default as ConfirmModal } from './ConfirmModal.vue';
export { default as DataTable } from './DataTable.vue';
export { default as Paginator } from './Paginator.vue';

export type {
    ColumnAlign,
    DataTableColumn,
    DataTableSort,
    PaginationMeta,
    SortDirection,
} from './types';
