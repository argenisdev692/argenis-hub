<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Auth\Application\Commands\RevokeAuthSessionHandler;
use Modules\Auth\Application\Commands\RevokeOtherAuthSessionsHandler;
use Modules\Auth\Application\Queries\ListActiveAuthSessionsHandler;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

/**
 * Lets a user see and end their own sessions (FR-14).
 *
 * Every action is scoped to the authenticated user inside the handlers, so a
 * session uuid belonging to somebody else resolves to nothing rather than to a
 * 403 that would confirm it exists (OWASP §11 / API1).
 *
 * Fused web + JSON controller (Controller Fusion Rule): authorization,
 * validation and the handler call are identical across both branches — only the
 * serialization differs.
 */
final readonly class AuthSessionController
{
    public function __construct(
        private ListActiveAuthSessionsHandler $listSessions,
        private RevokeAuthSessionHandler $revokeSession,
        private RevokeOtherAuthSessionsHandler $revokeOtherSessions,
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        $sessions = $this->listSessions->handle(
            (string) $request->user()->uuid,
            $request->session()->getId(),
        );

        return match ($request->expectsJson()) {
            true => response()->json(['data' => $sessions]),
            false => Inertia::render('settings/Sessions', ['sessions' => $sessions]),
        };
    }

    public function destroy(Request $request, string $uuid): RedirectResponse|JsonResponse
    {
        $revoked = $this->revokeSession->handle((string) $request->user()->uuid, $uuid);

        abort_unless($revoked, HttpStatus::HTTP_NOT_FOUND);

        return match ($request->expectsJson()) {
            true => response()->json(['revoked' => 1]),
            false => back()->with('status', __('That session was signed out.')),
        };
    }

    public function destroyOthers(Request $request): RedirectResponse|JsonResponse
    {
        $revoked = $this->revokeOtherSessions->handle(
            (string) $request->user()->uuid,
            $request->session()->getId(),
        );

        return match ($request->expectsJson()) {
            true => response()->json(['revoked' => $revoked]),
            false => back()->with('status', trans_choice('One other session was signed out.|:count other sessions were signed out.', $revoked, ['count' => $revoked])),
        };
    }
}
