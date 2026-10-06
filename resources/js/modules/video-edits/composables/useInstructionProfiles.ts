import { useQuery } from '@pinia/colada';
import { httpJson } from '@/lib/http';
import { instructionProfiles as instructionProfilesRoute } from '@/routes/video-edits/admin';
import type { InstructionProfile } from '../types';

/**
 * The standing-notes templates the AI edit form can start from.
 *
 * Config-backed, so this changes only on deploy and is safe to keep for the
 * session: no refetch loop, and no need to invalidate it after an edit is
 * saved. `staleTime` still has to be declared — the query is what the profile
 * dropdown renders from, and a refetch storm on every form open would be
 * pointless traffic for content that cannot change underneath the user.
 */
export function useInstructionProfiles() {
    return useQuery<{ data: InstructionProfile[] }>({
        key: ['video-edits', 'instruction-profiles'],
        query: () =>
            httpJson<{ data: InstructionProfile[] }>(
                instructionProfilesRoute.url(),
            ),
        staleTime: 1000 * 60 * 30,
        gcTime: 1000 * 60 * 60,
    });
}