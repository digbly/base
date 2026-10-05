<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexMediaRequest;
use App\Http\Requests\Admin\StoreMediaRequest;
use App\Http\Requests\Admin\UpdateMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\MediaItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Modules\Network\Models\Website;
use OpenApi\Attributes as OA;

class MediaController extends Controller
{
    #[OA\Get(
        path: '/api/v1/admin/websites/{website}/media',
        summary: 'List Media',
        operationId: 'admin.media.index',
        tags: ['Admin Media'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['image', 'document'])),
            new OA\Parameter(name: 'month', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: '2026-09')),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['created_at', 'updated_at', 'title'])),
            new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Media list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: MediaResource::class)),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(Website $website, IndexMediaRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $items = MediaItem::query()
            ->with('media')
            ->when(
                $filters['search'] ?? null,
                fn (Builder $query, string $search) => $query->where(
                    function (Builder $query) use ($search): void {
                        $query->where('title', 'like', "%{$search}%")
                            ->orWhere('alt', 'like', "%{$search}%")
                            ->orWhereHas(
                                'media',
                                fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('file_name', 'like', "%{$search}%")
                            );
                    }
                )
            )
            ->when(
                $filters['type'] ?? null,
                fn (Builder $query, string $type) => $query->whereHas(
                    'media',
                    fn (Builder $query) => $type === 'image'
                        ? $query->where('mime_type', 'like', 'image/%')
                        : $query->where('mime_type', 'not like', 'image/%')
                )
            )
            ->when(
                $filters['month'] ?? null,
                fn (Builder $query, string $month) => $query
                    ->whereYear('created_at', substr($month, 0, 4))
                    ->whereMonth('created_at', substr($month, 5, 2))
            )
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->paginate((int) ($filters['per_page'] ?? 24));

        return MediaResource::collection($items);
    }

    #[OA\Get(
        path: '/api/v1/admin/websites/{website}/media/{id}',
        summary: 'Show Media',
        operationId: 'admin.media.show',
        tags: ['Admin Media'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Media detail',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: MediaResource::class)])
            ),
            new OA\Response(response: 404, description: 'Media not found'),
        ]
    )]
    public function show(Website $website, MediaItem $media): MediaResource
    {
        return MediaResource::make($media->load('media'));
    }

    #[OA\Post(
        path: '/api/v1/admin/websites/{website}/media',
        summary: 'Upload Media',
        operationId: 'admin.media.store',
        tags: ['Admin Media'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(type: StoreMediaRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Media uploaded',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: MediaResource::class)),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Website $website, StoreMediaRequest $request): JsonResponse
    {
        $data = $request->validated();
        $files = $request->file('files') ?? array_filter([$request->file('file')]);

        $created = Collection::make($files)->map(
            function ($file) use ($data, $request): MediaResource {
                $item = new MediaItem([
                    'title' => $data['title'] ?? null,
                    'alt' => $data['alt'] ?? null,
                    'caption' => $data['caption'] ?? null,
                    'description' => $data['description'] ?? null,
                    'uploaded_by' => $request->user('api')?->getKey(),
                ]);

                $item->save();

                $media = $item->addMedia($file)->toMediaCollection('default');

                MediaItem::storeDimensions($media);

                return MediaResource::make($item->refresh()->load('media'));
            }
        );

        return MediaResource::collection($created)->response()->setStatusCode(201);
    }

    #[OA\Put(
        path: '/api/v1/admin/websites/{website}/media/{id}',
        summary: 'Update Media',
        operationId: 'admin.media.update',
        tags: ['Admin Media'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: UpdateMediaRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Media updated',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: MediaResource::class)])
            ),
            new OA\Response(response: 404, description: 'Media not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Website $website, UpdateMediaRequest $request, MediaItem $media): MediaResource
    {
        $media->update($request->validated());

        return MediaResource::make($media->load('media'));
    }

    #[OA\Delete(
        path: '/api/v1/admin/websites/{website}/media/{id}',
        summary: 'Delete Media',
        operationId: 'admin.media.destroy',
        tags: ['Admin Media'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'website', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Media deleted'),
            new OA\Response(response: 404, description: 'Media not found'),
        ]
    )]
    public function destroy(Website $website, MediaItem $media): JsonResponse
    {
        $media->delete();

        return response()->json(['message' => 'Media deleted successfully.']);
    }
}
