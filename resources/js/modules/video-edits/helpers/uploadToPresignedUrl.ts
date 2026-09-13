import { HttpError } from '@/lib/http';
import type { UploadTarget } from '../types';

/**
 * PUTs one file to the presigned storage URL the backend issued.
 *
 * A bare `fetch`, not `httpJson`: this request leaves the app's origin, so it
 * must carry neither the session cookie nor the XSRF header — only the headers
 * the signature was computed over. The URL is never logged (it is a bearer
 * credential until it expires).
 */
export async function uploadToPresignedUrl(
    target: UploadTarget,
    file: File,
): Promise<void> {
    const response = await fetch(target.upload_url, {
        method: 'PUT',
        headers: target.headers,
        body: file,
        credentials: 'omit',
    });

    if (!response.ok) {
        throw new HttpError(
            `Uploading ${file.name} failed. Check your connection and try again.`,
            response.status,
        );
    }
}
