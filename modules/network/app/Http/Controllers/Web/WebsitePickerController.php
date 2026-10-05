<?php

namespace Modules\Network\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Modules\Network\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Network\Enums\WebsitePermission;
use Modules\Admin\Http\Controllers\Web\Concerns\AuthorizesAdmin;
use Modules\Network\Http\Requests\Admin\StoreOwnWebsiteRequest;
use Modules\Network\Http\Resources\WebsiteResource;

/**
 * Inertia-facing website picker: the websites the authenticated user can
 * administer, plus self-service creation.
 */
class WebsitePickerController extends Controller
{
    use AuthorizesAdmin;

    public function index(Request $request): Response
    {
        $websites = $request->user()
            ->websites()
            ->with('owner')
            ->withCount('users')
            ->orderByDesc('websites.created_at')
            ->get();

        return Inertia::render('Network::websites/Picker', [
            'title' => __('network.network.title'),
            'websites' => WebsiteResource::collection($websites),
            'networkDomain' => config('network.domain'),
            'canCreate' => $this->allows($request, WebsitePermission::Create->value),
            'isSuperAdmin' => $request->user()->isSuperAdmin(),
        ]);
    }

    public function store(StoreOwnWebsiteRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($data, $user): void {
            $website = Website::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'subdomain' => $data['subdomain'],
                'status' => $data['status'],
                'user_id' => $user->getKey(),
            ]);

            $website->users()->syncWithoutDetaching([$user->getKey()]);
        });

        return redirect()
            ->route('admin.websites.index')
            ->with('success', __('network.network.notices.created'));
    }
}
