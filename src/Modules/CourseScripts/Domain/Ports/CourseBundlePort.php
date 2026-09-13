<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Ports;

/**
 * Packs deliverables into one ZIP (FR-48, D17). The caller streams the file
 * and deletes it.
 */
interface CourseBundlePort
{
    /**
     * @param  array<string, string>  $generatedFiles  zip path => contents (README, bible, index)
     * @param  array<string, string>  $storedFiles  zip path => storage path
     * @return string absolute path of the temporary ZIP
     */
    public function build(array $generatedFiles, array $storedFiles): string;
}
