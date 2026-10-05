<?php

namespace Modules\Network\Http\Controllers\Web;

use Modules\Network\Enums\WebsiteStatus;
use App\Http\Controllers\Controller;
use Modules\Network\Models\Website;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminHomeController extends Controller
{
    /**
     * Send the user to the admin dashboard of a website they can access.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $websiteId = website_id() ?? $this->firstAccessibleWebsiteId($request);

        if ($websiteId === null) {
            return Inertia::render('NoWebsite');
        }

        return redirect()->to(admin_url(null, $websiteId));
    }

    /**
     * Resolve the first active website the user is allowed to administer.
     */
    protected function firstAccessibleWebsiteId(Request $request): int|string|null
    {
        $user = $request->user();

        return Website::query()
            ->where('status', WebsiteStatus::ACTIVE)
            ->when(
                ! $user->isSuperAdmin(),
                fn (Builder $query) => $query->whereHas(
                    'users',
                    fn (Builder $users) => $users->whereKey($user->getKey())
                )
            )
            ->orderBy('created_at')
            ->value('id');
    }
}
