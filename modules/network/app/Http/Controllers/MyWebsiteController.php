<?php

namespace Modules\Network\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Network\Models\Database;
use Modules\Network\Models\Website;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Modules\Network\Http\Requests\Admin\WebsiteRequest;
use Modules\Network\Http\Resources\WebsiteResource;
use OpenApi\Attributes as OA;

class MyWebsiteController extends Controller
{
    #[OA\Get(
        path: '/api/v1/admin/websites',
        summary: 'List Websites of the authenticated user',
        operationId: 'websites.index',
        tags: ['Websites'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['active', 'inactive', 'suspended'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Websites the authenticated user is a member of',
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
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $websites = $request->user('api')
            ->websites()
            ->with('owner')
            ->withCount('users')
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $q = $request->string('q');

                $query->where(function (Builder $query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                        ->orWhere('domain', 'like', "%{$q}%")
                        ->orWhere('subdomain', 'like', "%{$q}%");
                });
            })
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $query->where('status', $request->string('status'))
            )
            ->orderByDesc('websites.created_at')
            ->get();

        return WebsiteResource::collection($websites);
    }

    #[OA\Get(
        path: '/api/v1/admin/websites/{id}',
        summary: 'Show Website',
        operationId: 'websites.show',
        tags: ['Websites'],
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
        path: '/api/v1/admin/websites',
        summary: 'Create Website',
        operationId: 'websites.store',
        tags: ['Websites'],
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
        path: '/api/v1/admin/websites/{id}',
        summary: 'Update Website',
        operationId: 'websites.update',
        tags: ['Websites'],
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
        path: '/api/v1/admin/websites/{id}',
        summary: 'Delete Website',
        operationId: 'websites.destroy',
        tags: ['Websites'],
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
