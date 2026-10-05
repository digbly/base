<?php

namespace App\Http\Controllers;

use App\Http\Resources\LanguageResource;
use App\Models\Language;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class LanguageController extends Controller
{
    #[OA\Get(
        path: '/api/v1/languages',
        summary: 'List languages',
        operationId: 'languages.index',
        tags: ['Languages'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Languages available for the site',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: LanguageResource::class)),
                    ]
                )
            ),
        ]
    )]
    public function __invoke(): AnonymousResourceCollection
    {
        $languages = Language::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return LanguageResource::collection($languages);
    }
}
