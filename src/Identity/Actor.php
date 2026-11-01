<?php

namespace Finest\Platform\Identity;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who makes a request, from an identity access token: a person, or an
 * organization's integration key. Either way it acts for one organization;
 * what it may do is decided by the service's policies.
 */
final readonly class Actor implements Authenticatable
{
    /**
     * @param  string  $id  the person's or the integration key's id in identity
     * @param  array<string, string>  $roles  the person's role in each product; none for a key
     * @param  bool  $hasAccess  the organization uses a product this service serves, and the
     *                           person has a role in it or the key has its scope
     * @param  list<string>  $products  every product it may use: the organization uses it, and
     *                                  the person has a role in it or the key has its scope
     */
    public function __construct(
        public string $id,
        public string $organizationId,
        public array $roles = [],
        public bool $integration = false,
        public bool $hasAccess = true,
        public array $products = [],
    ) {}

    /** Whether it may use any of the products. */
    public function mayUse(string ...$products): bool
    {
        return array_intersect($products, $this->products) !== [];
    }

    public function role(string $product): ?string
    {
        return $this->roles[$product] ?? null;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
