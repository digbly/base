<?php

namespace Modules\Auth\Enums;

enum SocialProvider: string
{
    case Google = 'google';
    case Facebook = 'facebook';
    case Github = 'github';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Facebook => 'Facebook',
            self::Github => 'GitHub',
        };
    }

    public function icon(): string
    {
        return $this->value;
    }

    public function clientId(): ?string
    {
        return config("services.{$this->value}.client_id");
    }

    public function isConfigured(): bool
    {
        return ! empty($this->clientId());
    }

    /**
     * @return array<int, self>
     */
    public static function configured(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $provider): bool => $provider->isConfigured()
        ));
    }
}
