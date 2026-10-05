<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Enums\SocialProvider;
use OpenApi\Attributes as OA;

class SocialLoginController extends Controller
{
    /**
     * List configured social providers so the frontend can render buttons dynamically.
     */
    #[OA\Get(
        path: '/api/v1/auth/social-providers',
        summary: 'List available social login providers',
        operationId: 'user.social.providers',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Available providers'),
        ]
    )]
    public function providers(): JsonResponse
    {
        $available = collect(SocialProvider::configured())
            ->map(static fn (SocialProvider $provider): array => [
                'driver' => $provider->value,
                'label' => $provider->label(),
                'icon' => $provider->icon(),
            ])
            ->values();

        return response()->json(['data' => $available]);
    }
}
