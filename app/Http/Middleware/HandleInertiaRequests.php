<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $allowedSections = [];
        if ($user) {
            $allowedSections = config("role_permissions.{$user->role}.sections", []);
            // Cross-area access: merge sections of secondary areas (extra_areas).
            // Christina (role=sales, extra_areas=[sponsorship]) must see sponsorship sidebar.
            foreach ((array) ($user->extra_areas ?? []) as $extraRole) {
                $allowedSections = array_merge($allowedSections, config("role_permissions.{$extraRole}.sections", []));
            }
            $allowedSections = array_values(array_unique($allowedSections));
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? array_merge($user->toArray(), [
                    'allowed_sections' => $allowedSections,
                    'role_label' => config("role_permissions.{$user->role}.label", ''),
                ]) : null,
            ],
            'csrf_token' => csrf_token(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'reverb' => [
                'key'    => config('broadcasting.connections.reverb.key'),
                'host'   => config('broadcasting.connections.reverb.options.host'),
                'port'   => (int) config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme'),
            ],
        ];
    }
}
