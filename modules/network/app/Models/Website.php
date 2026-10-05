<?php

namespace Modules\Network\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Auth\Models\User;
use Modules\Network\Enums\WebsiteStatus;

class Website extends Model
{
    use HasUuids;

    protected $table = 'websites';

    protected $fillable = [
        'title',
        'domain',
        'subdomain',
        'description',
        'user_id',
        'status',
        'setup',
        'is_demo',
        'language',
        'theme',
        'database',
    ];

    protected $casts = [
        'status' => WebsiteStatus::class,
        'setup' => 'boolean',
        'is_demo' => 'boolean',
    ];

    protected $appends = [
        'url',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'website_user', 'website_id', 'user_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeWhereHost(Builder $builder, string $host): Builder
    {
        $domain = $host;
        $subdomain = null;

        $appDomain = config('network.subsite_domain');
        if ($appDomain && str_ends_with($host, $appDomain)) {
            $subdomain = substr($host, 0, -(strlen($appDomain) + 1));
            $domain = null;
        }

        return $builder->where(
            function (Builder $query) use ($domain, $subdomain) {
                if ($domain) {
                    $query->where('domain', $domain);
                }

                if ($subdomain) {
                    $query->orWhere('subdomain', $subdomain);
                }
            }
        );
    }

    public function getUrlAttribute(): ?string
    {
        if ($this->domain) {
            return 'http://'.$this->domain;
        }

        if ($this->isMainWebsite()) {
            return 'http://'.config('network.domain');
        }

        return 'http://'.$this->subdomain.'.'.config('network.subsite_domain');
    }

    public function isMainWebsite(): bool
    {
        return $this->id === config('network.main_website_id');
    }

    public function isDemoWebsite(): bool
    {
        return $this->is_demo === true;
    }

    public function getHost(): ?string
    {
        return $this->domain ?: ($this->subdomain.'.'.config('network.domain'));
    }
}
