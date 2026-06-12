<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\Schema;

/**
 * Builder for LocalBusiness JSON-LD schema.
 *
 * LocalBusiness schema helps search engines understand local
 * business information and can improve local search visibility.
 *
 * ## Usage
 * ```php
 * $schema = (new LocalBusinessSchema())
 *     ->setName('My Restaurant')
 *     ->setDescription('Best pizza in town')
 *     ->setAddress([
 *         'street' => '123 Main St',
 *         'city' => 'New York',
 *         'state' => 'NY',
 *         'postalCode' => '10001',
 *         'country' => 'US',
 *     ])
 *     ->setGeo(40.7128, -74.0060)
 *     ->setPhone('+1-555-555-5555')
 *     ->setPriceRange('$$')
 *     ->setOpeningHours([
 *         'Mo-Fr 09:00-17:00',
 *         'Sa 10:00-14:00',
 *     ])
 *     ->toArray();
 * ```
 */
class LocalBusinessSchema
{
    protected string $type = 'LocalBusiness';

    protected ?string $name = null;

    protected ?string $description = null;

    /** @var array<string, mixed>|null */
    protected ?array $address = null;

    /** @var array{latitude: float, longitude: float}|null */
    protected ?array $geo = null;

    protected ?string $phone = null;

    protected ?string $email = null;

    protected ?string $url = null;

    protected ?string $image = null;

    /** @var array<int, string>|null */
    protected ?array $openingHours = null;

    protected ?string $priceRange = null;

    /** @var array<int, array{ratingValue: float, reviewCount: int}>|null */
    protected ?array $aggregateRating = null;

    /**
     * Set business type (e.g., Restaurant, Store, MedicalBusiness).
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set as Restaurant type.
     */
    public function asRestaurant(): self
    {
        $this->type = 'Restaurant';

        return $this;
    }

    /**
     * Set as Store type.
     */
    public function asStore(): self
    {
        $this->type = 'Store';

        return $this;
    }

    /**
     * Set business name.
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
     * Set geographic coordinates.
     */
    public function setGeo(float $latitude, float $longitude): self
    {
        $this->geo = [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        return $this;
    }

    /**
     * Set phone number.
     */
    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Set email address.
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Set website URL.
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Set image URL.
     */
    public function setImage(string $image): self
    {
        $this->image = $image;

        return $this;
    }

    /**
     * Set opening hours.
     *
     * @param array<int, string> $hours Format: ['Mo-Fr 09:00-17:00', 'Sa 10:00-14:00']
     */
    public function setOpeningHours(array $hours): self
    {
        $this->openingHours = $hours;

        return $this;
    }

    /**
     * Set price range.
     */
    public function setPriceRange(string $range): self
    {
        $this->priceRange = $range;

        return $this;
    }

    /**
     * Set aggregate rating.
     */
    public function setAggregateRating(float $ratingValue, int $reviewCount): self
    {
        $this->aggregateRating = [
            'ratingValue' => $ratingValue,
            'reviewCount' => $reviewCount,
        ];

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

        if ($this->address) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $this->address['street'] ?? null,
                'addressLocality' => $this->address['city'] ?? null,
                'addressRegion' => $this->address['state'] ?? null,
                'postalCode' => $this->address['postalCode'] ?? null,
                'addressCountry' => $this->address['country'] ?? null,
            ];
            // Remove null values
            $schema['address'] = array_filter($schema['address']);
        }

        if ($this->geo) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $this->geo['latitude'],
                'longitude' => $this->geo['longitude'],
            ];
        }

        if ($this->phone) {
            $schema['telephone'] = $this->phone;
        }

        if ($this->email) {
            $schema['email'] = $this->email;
        }

        if ($this->url) {
            $schema['url'] = $this->url;
        }

        if ($this->image) {
            $schema['image'] = $this->image;
        }

        if ($this->openingHours) {
            $schema['openingHours'] = $this->openingHours;
        }

        if ($this->priceRange) {
            $schema['priceRange'] = $this->priceRange;
        }

        if ($this->aggregateRating) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $this->aggregateRating['ratingValue'],
                'reviewCount' => $this->aggregateRating['reviewCount'],
            ];
        }

        return $schema;
    }
}
