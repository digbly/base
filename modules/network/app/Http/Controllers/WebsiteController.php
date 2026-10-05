<?php

namespace Modules\Network\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Modules\Network\Http\Requests\IndexWebsiteRequest;
use Modules\Network\Http\Requests\WebsiteRequest;
use Modules\Network\Http\Resources\WebsiteResource;
use Modules\Network\Models\Database;
use Modules\Network\Models\Website;
use OpenApi\Attributes as OA;

/**
 * Network-wide website management. Unlike the per-user picker, these endpoints
 * are not scoped to the authenticated user and require super admin access.
 */
class WebsiteController extends Controller
{
    #[OA\Get(
        path: '/api/v1/network/websites',
        summary: 'List every website in the network',
        operationId: 'network.websites.index',
        tags: ['Network'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['active', 'inactive', 'suspended'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated websites',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(type: WebsiteResource::class)
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(IndexWebsiteRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $websites = Website::query()
            ->with('owner')
            ->withCount('users')
            ->when(
                $filters['q'] ?? null,
                fn (Builder $query, string $q) => $query->where(function (Builder $query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                        ->orWhere('domain', 'like', "%{$q}%")
                        ->orWhere('subdomain', 'like', "%{$q}%");
                })
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status)
            )
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15));

        return WebsiteResource::collection($websites);
    }

    #[OA\Get(
        path: '/api/v1/network/websites/{id}',
        summary: 'Show a website',
        operationId: 'network.websites.show',
        tags: ['Network'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Website detail',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: WebsiteResource::class),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Website not found'),
        ]
    )]
    public function show(Website $website): WebsiteResource
    {
        return WebsiteResource::make(
            Website::with('owner')->withCount('users')->findOrFail($website->getKey())
        );
    }

    #[OA\Post(
        path: '/api/v1/network/websites',
        summary: 'Create a website',
        operationId: 'network.websites.store',
        tags: ['Network'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: WebsiteRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Website created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: WebsiteResource::class),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(WebsiteRequest $request): WebsiteResource
    {
        $website = DB::transaction(function () use ($request) {
            $website = Website::create($request->validated());

            $website->users()->syncWithoutDetaching([$website->user_id]);

            if ($website->database) {
                Database::query()->where('name', $website->database)->increment('total_websites');
            }

            return $website;
        });

        return WebsiteResource::make($website->load('owner')->loadCount('users'));
    }

    #[OA\Put(
        path: '/api/v1/network/websites/{id}',
        summary: 'Update a website',
        operationId: 'network.websites.update',
        tags: ['Network'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: WebsiteRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Website updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: WebsiteResource::class),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Website not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(WebsiteRequest $request, Website $website): WebsiteResource
    {
        $oldDatabase = $website->database;

        DB::transaction(function () use ($request, $website, $oldDatabase) {
            $website->update($request->validated());

            $website->users()->syncWithoutDetaching([$website->user_id]);

            if ($oldDatabase !== $website->database) {
                if ($oldDatabase) {
                    Database::query()->where('name', $oldDatabase)->decrement('total_websites');
                }

                if ($website->database) {
                    Database::query()->where('name', $website->database)->increment('total_websites');
                }
            }
        });

        return WebsiteResource::make($website->load('owner')->loadCount('users'));
    }

    #[OA\Delete(
        path: '/api/v1/network/websites/{id}',
        summary: 'Delete a website',
        operationId: 'network.websites.destroy',
        tags: ['Network'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Website deleted'),
            new OA\Response(response: 404, description: 'Website not found'),
        ]
    )]
    public function destroy(Website $website): JsonResponse
    {
        DB::transaction(function () use ($website) {
            if ($website->database) {
                Database::query()->where('name', $website->database)->decrement('total_websites');
            }

            $website->delete();
        });

        return response()->json(['message' => 'Website deleted successfully.']);
    }
}
