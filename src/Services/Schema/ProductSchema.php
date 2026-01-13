<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Services\Schema;

/**
 * Builder for Product JSON-LD schema.
 *
 * Product schema helps search engines understand product
 * information and can result in rich product snippets.
 *
 * ## Usage
 * ```php
 * $schema = (new ProductSchema())
 *     ->setName('Example Product')
 *     ->setDescription('A great product')
 *     ->setImage('https://example.com/product.jpg')
 *     ->setBrand('Example Brand')
 *     ->setSku('SKU123')
 *     ->setPrice(99.99, 'USD')
 *     ->setAvailability('InStock')
 *     ->setAggregateRating(4.5, 100)
 *     ->toArray();
 * ```
 */
class ProductSchema
{
    protected ?string $name = null;

    protected ?string $description = null;

    /** @var string|array<int, string>|null */
    protected string|array|null $image = null;

    protected ?string $brand = null;

    protected ?string $sku = null;

    protected ?string $gtin = null;

    protected ?string $mpn = null;

    /** @var array{price: float, currency: string}|null */
    protected ?array $price = null;

    protected ?string $availability = null;

    protected ?string $url = null;

    /** @var array{ratingValue: float, reviewCount: int}|null */
    protected ?array $aggregateRating = null;

    /** @var array<int, array{author: string, rating: float, text: string}>|null */
    protected ?array $reviews = null;

    /**
     * Set product name.
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
     * Set image(s).
     *
     * @param string|array<int, string> $image
     */
    public function setImage(string|array $image): self
    {
        $this->image = $image;

        return $this;
    }

    /**
     * Set brand name.
     */
    public function setBrand(string $brand): self
    {
        $this->brand = $brand;

        return $this;
    }

    /**
     * Set SKU.
     */
    public function setSku(string $sku): self
    {
        $this->sku = $sku;

        return $this;
    }

    /**
     * Set GTIN (Global Trade Item Number).
     */
    public function setGtin(string $gtin): self
    {
        $this->gtin = $gtin;

        return $this;
    }

    /**
     * Set MPN (Manufacturer Part Number).
     */
    public function setMpn(string $mpn): self
    {
        $this->mpn = $mpn;

        return $this;
    }

    /**
     * Set price.
     */
    public function setPrice(float $price, string $currency = 'USD'): self
    {
        $this->price = [
            'price' => $price,
            'currency' => $currency,
        ];

        return $this;
    }

    /**
     * Set availability.
     *
     * @param string $availability One of: InStock, OutOfStock, PreOrder, BackOrder, Discontinued
     */
    public function setAvailability(string $availability): self
    {
        // Normalize to schema.org format
        $map = [
            'in_stock' => 'InStock',
            'instock' => 'InStock',
            'out_of_stock' => 'OutOfStock',
            'outofstock' => 'OutOfStock',
            'pre_order' => 'PreOrder',
            'preorder' => 'PreOrder',
            'back_order' => 'BackOrder',
            'backorder' => 'BackOrder',
            'discontinued' => 'Discontinued',
        ];

        $normalized = $map[strtolower($availability)] ?? $availability;
        $this->availability = $normalized;

        return $this;
    }

    /**
     * Set product URL.
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;

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
     * Add a review.
     */
    public function addReview(string $author, float $rating, string $text): self
    {
        $this->reviews ??= [];
        $this->reviews[] = [
            'author' => $author,
            'rating' => $rating,
            'text' => $text,
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
            '@type' => 'Product',
        ];

        if ($this->name) {
            $schema['name'] = $this->name;
        }

        if ($this->description) {
            $schema['description'] = $this->description;
        }

        if ($this->image) {
            $schema['image'] = $this->image;
        }

        if ($this->brand) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => $this->brand,
            ];
        }

        if ($this->sku) {
            $schema['sku'] = $this->sku;
        }

        if ($this->gtin) {
            $schema['gtin'] = $this->gtin;
        }

        if ($this->mpn) {
            $schema['mpn'] = $this->mpn;
        }

        if ($this->price || $this->availability) {
            $offer = ['@type' => 'Offer'];

            if ($this->price) {
                $offer['price'] = $this->price['price'];
                $offer['priceCurrency'] = $this->price['currency'];
            }

            if ($this->availability) {
                $offer['availability'] = 'https://schema.org/' . $this->availability;
            }

            if ($this->url) {
                $offer['url'] = $this->url;
            }

            $schema['offers'] = $offer;
        }

        if ($this->aggregateRating) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $this->aggregateRating['ratingValue'],
                'reviewCount' => $this->aggregateRating['reviewCount'],
            ];
        }

        if ($this->reviews) {
            $schema['review'] = array_map(function ($review) {
                return [
                    '@type' => 'Review',
                    'author' => [
                        '@type' => 'Person',
                        'name' => $review['author'],
                    ],
                    'reviewRating' => [
                        '@type' => 'Rating',
                        'ratingValue' => $review['rating'],
                    ],
                    'reviewBody' => $review['text'],
                ];
            }, $this->reviews);
        }

        return $schema;
    }
}
