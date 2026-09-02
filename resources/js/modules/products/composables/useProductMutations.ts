import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import {
    bulkDelete,
    bulkRestore,
    destroy,
    restore,
    store,
    update,
} from '@/routes/products/admin';
import type { Product, ProductWritePayload } from '../types';

/** Every mutation below touches the same list, so one key invalidates all of it. */
const PRODUCTS_KEY = ['products'];

/**
 * Invoice line items embed a product's title, price and default unit, so the
 * invoice form's catalog options go stale the moment the catalog changes.
 */
const INVOICE_FORM_OPTIONS_KEY = ['invoice-form-options'];

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

export function useProductMutations() {
    const queryCache = useQueryCache();

    function invalidateAll(): void {
        queryCache.invalidateQueries({ key: PRODUCTS_KEY });
        queryCache.invalidateQueries({ key: INVOICE_FORM_OPTIONS_KEY });
    }

    const createProduct = useMutation({
        mutation: (payload: ProductWritePayload) =>
            httpJson<Product>(toUrl(store()), { method: 'POST', body: payload }),
        onSuccess() {
            toast.success('Product created.');
            invalidateAll();
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to create the product.'));
        },
    });

    const updateProduct = useMutation({
        mutation: ({
            uuid,
            payload,
        }: {
            uuid: string;
            payload: ProductWritePayload;
        }) =>
            httpJson<Product>(toUrl(update(uuid)), {
                method: 'PUT',
                body: payload,
            }),
        onSuccess() {
            toast.success('Product updated.');
            invalidateAll();
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to update the product.'));
        },
    });

    const deleteProduct = useMutation({
        mutation: (uuid: string) =>
            httpJson<void>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('Product deleted.');
            invalidateAll();
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to delete the product.'));
        },
    });

    const restoreProduct = useMutation({
        mutation: (uuid: string) =>
            httpJson<Product>(toUrl(restore(uuid)), { method: 'PATCH' }),
        onSuccess() {
            toast.success('Product restored.');
            invalidateAll();
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to restore the product.'));
        },
    });

    const bulkDeleteProducts = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<{ deleted: number }>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ deleted }) {
            toast.success(
                `${deleted} ${deleted === 1 ? 'product' : 'products'} deleted.`,
            );
            invalidateAll();
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to delete the selected products.'),
            );
        },
    });

    const bulkRestoreProducts = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<{ restored: number }>(toUrl(bulkRestore()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ restored }) {
            toast.success(
                `${restored} ${restored === 1 ? 'product' : 'products'} restored.`,
            );
            invalidateAll();
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the selected products.'),
            );
        },
    });

    return {
        createProduct,
        updateProduct,
        deleteProduct,
        restoreProduct,
        bulkDeleteProducts,
        bulkRestoreProducts,
    };
}
