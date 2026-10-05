<?php

namespace App\Http\Controllers;

use App\Support\AdminTranslations;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use OpenApi\Attributes as OA;

class TranslationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/translations/{locale}',
        summary: 'Show all admin SPA translations for a locale',
        operationId: 'translations.show',
        tags: ['Translations'],
        parameters: [
            new OA\Parameter(name: 'locale', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'en')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Translation trees keyed by i18next namespace',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'common', type: 'object'),
                        new OA\Property(property: 'admin', type: 'object'),
                        new OA\Property(property: 'auth', type: 'object'),
                        new OA\Property(property: 'blog', type: 'object'),
                        new OA\Property(property: 'network', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Locale not found'),
        ]
    )]
    public function __invoke(string $locale, AdminTranslations $translations): JsonResponse
    {
        abort_unless(in_array($locale, $translations->locales(), true), 404);

        $translations->registerNamespaces();

        App::setLocale($locale);

        $payload = [];

        foreach (array_keys($translations->namespaces()) as $namespace) {
            $lines = trans($translations->translationKey($namespace));

            $payload[$namespace] = is_array($lines) ? $lines : [];
        }

        return response()->json($payload);
    }
}
