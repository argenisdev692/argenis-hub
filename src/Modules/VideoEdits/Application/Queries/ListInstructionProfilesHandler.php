<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Queries;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Modules\VideoEdits\Application\DTOs\InstructionProfileData;

/**
 * The standing-notes templates the AI edit form can start from.
 *
 * Reading config is not business logic, so this handler stays a straight
 * projection: no repository, no persistence, nothing to migrate. Profiles are
 * editorial policy that want code review and history, which is exactly what a
 * config file gives and a table would not.
 *
 * A malformed entry is skipped rather than allowed to break the form, and an
 * over-long one is trimmed to what `ai_edit.instructions` accepts — otherwise a
 * profile could produce a payload the validator rejects before the owner has
 * typed anything.
 */
final readonly class ListInstructionProfilesHandler
{
    public function __construct(private ConfigRepository $config) {}

    /**
     * @return list<InstructionProfileData>
     */
    public function handle(): array
    {
        $maxCharacters = (int) $this->config->get('video-edit.ai.max_instructions_characters', 20_000);
        $profiles = [];

        foreach ((array) $this->config->get('video-edit.ai.instruction_profiles', []) as $key => $profile) {
            if (! is_array($profile)) {
                continue;
            }

            $name = trim((string) ($profile['name'] ?? ''));
            $instructions = trim((string) ($profile['instructions'] ?? ''));

            if ($name === '' || $instructions === '') {
                continue;
            }

            $profiles[] = new InstructionProfileData(
                key: (string) $key,
                name: mb_substr($name, 0, 120),
                instructions: mb_substr($instructions, 0, $maxCharacters),
            );
        }

        return $profiles;
    }
}
