<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\Schema;

/**
 * Builder for Organization JSON-LD schema.
 *
 * Organization schema helps search engines understand your
 * organization and can improve knowledge panel results.
 *
 * ## Usage
 * ```php
 * $schema = (new OrganizationSchema())
 *     ->setName('My Company')
 *     ->setDescription('We do amazing things')
 *     ->setUrl('https://example.com')
 *     ->setLogo('https://example.com/logo.png')
 *     ->setSocialProfiles([
 *         'https://twitter.com/mycompany',
 *         'https://facebook.com/mycompany',
 *     ])
 *     ->setContactPoint('customer service', '+1-555-555-5555')
 *     ->toArray();
 * ```
 */
class OrganizationSchema
{
    protected string $type = 'Organization';

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?string $url = null;

    protected ?string $logo = null;

    protected ?string $email = null;

    protected ?string $phone = null;

    /** @var array<int, string>|null */
    protected ?array $sameAs = null;

    /** @var array<string, mixed>|null */
    protected ?array $address = null;

    /** @var array<int, array{type: string, phone: string, email?: string}>|null */
    protected ?array $contactPoints = null;

    protected ?string $foundingDate = null;

    /** @var array<int, string>|null */
    protected ?array $founders = null;

    /**
     * Set organization type.
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set as Corporation type.
     */
    public function asCorporation(): self
    {
        $this->type = 'Corporation';

        return $this;
    }

    /**
     * Set as NGO type.
     */
    public function asNGO(): self
    {
        $this->type = 'NGO';

        return $this;
    }

    /**
     * Set organization name.
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set description.
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set URL.
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Set logo URL.
     */
    public function setLogo(string $logo): self
    {
        $this->logo = $logo;

        return $this;
    }

    /**
     * Set email.
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Set phone.
     */
    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Set social profile URLs.
     *
     * @param array<int, string> $profiles
     */
    public function setSocialProfiles(array $profiles): self
    {
        $this->sameAs = $profiles;

        return $this;
    }

    /**
     * Add a social profile URL.
     */
    public function addSocialProfile(string $url): self
    {
        $this->sameAs ??= [];
        $this->sameAs[] = $url;

        return $this;
    }

    /**
     * Set address.
     *
     * @param array{street?: string, city?: string, state?: string, postalCode?: string, country?: string} $address
     */
    public function setAddress(array $address): self
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Add a contact point.
     */
    public function setContactPoint(string $type, string $phone, ?string $email = null): self
    {
        $this->contactPoints ??= [];
        $this->contactPoints[] = [
            'type' => $type,
            'phone' => $phone,
            'email' => $email,
        ];

        return $this;
    }

    /**
     * Set founding date.
     */
    public function setFoundingDate(string $date): self
    {
        $this->foundingDate = $date;

        return $this;
    }

    /**
     * Set founder names.
     *
     * @param array<int, string> $founders
     */
    public function setFounders(array $founders): self
    {
        $this->founders = $founders;

        return $this;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $this->type,
        ];

        if ($this->name) {
            $schema['name'] = $this->name;
        }

        if ($this->description) {
            $schema['description'] = $this->description;
        }

        if ($this->url) {
            $schema['url'] = $this->url;
        }

        if ($this->logo) {
            $schema['logo'] = [
                '@type' => 'ImageObject',
                'url' => $this->logo,
            ];
        }

        if ($this->email) {
            $schema['email'] = $this->email;
        }

        if ($this->phone) {
            $schema['telephone'] = $this->phone;
        }

        if ($this->sameAs) {
            $schema['sameAs'] = $this->sameAs;
        }

        if ($this->address) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $this->address['street'] ?? null,
                'addressLocality' => $this->address['city'] ?? null,
                'addressRegion' => $this->address['state'] ?? null,
                'postalCode' => $this->address['postalCode'] ?? null,
                'addressCountry' => $this->address['country'] ?? null,
            ];
            $schema['address'] = array_filter($schema['address']);
        }

        if ($this->contactPoints) {
            $schema['contactPoint'] = array_map(function ($cp) {
                $point = [
                    '@type' => 'ContactPoint',
                    'contactType' => $cp['type'],
                    'telephone' => $cp['phone'],
                ];
                if (! empty($cp['email'])) {
                    $point['email'] = $cp['email'];
                }

                return $point;
            }, $this->contactPoints);
        }

        if ($this->foundingDate) {
            $schema['foundingDate'] = $this->foundingDate;
        }

        if ($this->founders) {
            $schema['founder'] = array_map(function ($name) {
                return [
                    '@type' => 'Person',
                    'name' => $name,
                ];
            }, $this->founders);
        }

        return $schema;
    }
}
