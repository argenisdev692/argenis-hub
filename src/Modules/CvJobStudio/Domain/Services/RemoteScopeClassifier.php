<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

use Modules\CvJobStudio\Domain\Enums\RemoteScope;

/**
 * Exactly one remote-scope label per posting (FR-11). Pure keyword rules over
 * title + location text; order matters — hybrid signals beat remote ones.
 */
final readonly class RemoteScopeClassifier
{
    #[\NoDiscard]
    public function classify(string $title, ?string $locationText): RemoteScope
    {
        $haystack = mb_strtolower(trim($title.' '.($locationText ?? '')));

        if ($this->contains($haystack, ['hybrid', 'híbrido', 'hibrido', 'onsite', 'on-site', 'presencial', 'in-office', 'in office'])) {
            return RemoteScope::HybridLocal;
        }

        $hasRemoteSignal = $this->contains($haystack, ['remote', 'remoto', 'remota', 'teletrabajo', 'teletrabalho', 'work from home', 'wfh', 'distributed']);

        if (! $hasRemoteSignal) {
            return RemoteScope::RemoteUnclear;
        }

        if ($this->contains($haystack, ['worldwide', 'global', 'anywhere', 'remoto global'])) {
            return RemoteScope::RemoteGlobal;
        }

        if ($this->contains($haystack, ['europe', 'european', 'emea', 'remote eu', 'eu remote']) || preg_match('/\beu\b/', $haystack) === 1) {
            return RemoteScope::RemoteEu;
        }

        if ($this->contains($haystack, ['portugal', 'spain', 'españa', 'espana', 'lisboa', 'lisbon', 'madrid', 'barcelona', 'porto', 'remoto pt', 'iberia'])) {
            return RemoteScope::RemotePtEs;
        }

        return RemoteScope::RemoteUnclear;
    }

    /**
     * @param  list<string>  $needles
     */
    private function contains(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
