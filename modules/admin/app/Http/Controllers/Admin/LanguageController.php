<?php

namespace Modules\Admin\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LanguageResource;
use App\Models\Language;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Http\Requests\Admin\LanguageRequest;
use OpenApi\Attributes as OA;

class LanguageController extends Controller
{
    #[OA\Get(
        path: '/api/v1/admin/languages',
        summary: 'List Languages',
        operationId: 'admin.languages.index',
        tags: ['Admin Languages'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Languages list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: LanguageResource::class)),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $languages = Language::query()
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $search = $request->string('q');

                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return LanguageResource::collection($languages);
    }

    #[OA\Get(
        path: '/api/v1/admin/languages/{id}',
        summary: 'Show Language',
        operationId: 'admin.languages.show',
        tags: ['Admin Languages'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Language detail',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: LanguageResource::class)])
            ),
            new OA\Response(response: 404, description: 'Language not found'),
        ]
    )]
    public function show(Language $language): LanguageResource
    {
        return LanguageResource::make($language);
    }

    #[OA\Post(
        path: '/api/v1/admin/languages',
        summary: 'Create Language',
        operationId: 'admin.languages.store',
        tags: ['Admin Languages'],
        security: [['bearerAuth' => []]],
        parameters: [
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: LanguageRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Language created',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: LanguageResource::class)])
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(LanguageRequest $request): LanguageResource
    {
        $data = $request->validated();

        $language = DB::transaction(function () use ($data): Language {
            if ($data['is_default'] ?? false) {
                Language::query()->update(['is_default' => false]);
            }

            return Language::create($data);
        });

        return LanguageResource::make($language);
    }

    #[OA\Put(
        path: '/api/v1/admin/languages/{id}',
        summary: 'Update Language',
        operationId: 'admin.languages.update',
        tags: ['Admin Languages'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(type: LanguageRequest::class)
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Language updated',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: LanguageResource::class)])
            ),
            new OA\Response(response: 404, description: 'Language not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(LanguageRequest $request, Language $language): LanguageResource
    {
        $data = $request->validated();

        DB::transaction(function () use ($language, $data): void {
            if (($data['is_default'] ?? false) === true) {
                Language::query()
                    ->whereKeyNot($language->getKey())
                    ->update(['is_default' => false]);
            }

            $language->update($data);
        });

        return LanguageResource::make($language->refresh());
    }

    #[OA\Delete(
        path: '/api/v1/admin/languages/{id}',
        summary: 'Delete Language',
        operationId: 'admin.languages.destroy',
        tags: ['Admin Languages'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Language deleted'),
            new OA\Response(response: 404, description: 'Language not found'),
            new OA\Response(response: 422, description: 'Language cannot be deleted'),
        ]
    )]
    public function destroy(Language $language): JsonResponse
    {
        if ($language->is_default) {
            return response()->json(['message' => 'The default language cannot be deleted.'], 422);
        }

        if ($language->code === config('translatable.fallback_locale')) {
            return response()->json(['message' => 'The fallback language cannot be deleted.'], 422);
        }

        $language->delete();

        return response()->json(['message' => 'Language deleted successfully.']);
    }
}
