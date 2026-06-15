<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Services\Schema;

use Rankbeam\Seo\Data\SchemaValidationResult;

/**
 * Validates JSON-LD structured data schemas.
 *
 * Validates schemas against Google's structured data requirements
 * to ensure they can generate rich results.
 *
 * ## Usage
 * ```php
 * $validator = new SchemaValidator();
 *
 * $result = $validator->validate($schema);
 * if (!$result->isValid) {
 *     foreach ($result->errors as $error) {
 *         echo $error['message'];
 *     }
 * }
 * ```
 *
 * ## Specific Validators
 * ```php
 * $result = $validator->validateArticle($schema);
 * $result = $validator->validateFAQ($schema);
 * $result = $validator->validateBreadcrumb($schema);
 * $result = $validator->validateLocalBusiness($schema);
 * $result = $validator->validateProduct($schema);
 * ```
 */
class SchemaValidator
{
    /**
     * Validate a schema based on its @type.
     *
     * @param array<string, mixed> $schema
     */
    public function validate(array $schema): SchemaValidationResult
    {
        $type = $schema['@type'] ?? null;

        if (! $type) {
            return SchemaValidationResult::invalid([
                ['field' => '@type', 'message' => 'Schema must have an @type property'],
            ]);
        }

        return match ($type) {
            'Article', 'NewsArticle', 'BlogPosting' => $this->validateArticle($schema),
            'FAQPage' => $this->validateFAQ($schema),
            'BreadcrumbList' => $this->validateBreadcrumb($schema),
            'LocalBusiness', 'Restaurant', 'Store' => $this->validateLocalBusiness($schema),
            'Product' => $this->validateProduct($schema),
            'Organization', 'Corporation', 'NGO' => $this->validateOrganization($schema),
            default => $this->validateGeneric($schema),
        };
    }

    /**
     * Validate Article schema.
     *
     * Required by Google:
     * - headline
     * - image
     * - author
     * - datePublished
     *
     * Recommended:
     * - dateModified
     * - publisher
     * - mainEntityOfPage
     *
     * @param array<string, mixed> $schema
     */
    public function validateArticle(array $schema): SchemaValidationResult
    {
        $errors = [];
        $warnings = [];

        // Required fields
        $required = ['headline', 'image', 'author', 'datePublished'];
        $errors = array_merge($errors, $this->checkRequiredFields($schema, $required));

        // Validate headline length (Google recommends < 110 chars)
        if (isset($schema['headline']) && mb_strlen($schema['headline']) > 110) {
            $warnings[] = [
                'field' => 'headline',
                'message' => 'Headline should be under 110 characters for best display',
            ];
        }

        // Validate image
        if (isset($schema['image'])) {
            $imageUrl = is_array($schema['image'])
                ? ($schema['image']['url'] ?? null)
                : $schema['image'];

            if ($imageUrl && ! $this->validateUrl($imageUrl)) {
                $errors[] = [
                    'field' => 'image',
                    'message' => 'Image must be a valid URL',
                ];
            }
        }

        // Validate author
        if (isset($schema['author'])) {
            $authorName = is_array($schema['author'])
                ? ($schema['author']['name'] ?? null)
                : $schema['author'];

            if (empty($authorName)) {
                $errors[] = [
                    'field' => 'author',
                    'message' => 'Author must have a name',
                ];
            }
        }

        // Validate dates
        if (isset($schema['datePublished']) && ! $this->validateDate($schema['datePublished'])) {
            $errors[] = [
                'field' => 'datePublished',
                'message' => 'datePublished must be in ISO 8601 format',
            ];
        }

        if (isset($schema['dateModified']) && ! $this->validateDate($schema['dateModified'])) {
            $errors[] = [
                'field' => 'dateModified',
                'message' => 'dateModified must be in ISO 8601 format',
            ];
        }

        // Recommended fields
        if (! isset($schema['publisher'])) {
            $warnings[] = [
                'field' => 'publisher',
                'message' => 'publisher is recommended for Article schema',
            ];
        }

        if (! isset($schema['dateModified'])) {
            $warnings[] = [
                'field' => 'dateModified',
                'message' => 'dateModified is recommended for Article schema',
            ];
        }

        return empty($errors)
            ? SchemaValidationResult::valid($schema, $warnings)
            : SchemaValidationResult::invalid($errors, $warnings, $schema);
    }

    /**
     * Validate FAQ schema.
     *
     * Required by Google:
     * - mainEntity (array of Question items)
     *
     * @param array<string, mixed> $schema
     */
    public function validateFAQ(array $schema): SchemaValidationResult
    {
        $errors = [];
        $warnings = [];

        // Required: mainEntity
        if (! isset($schema['mainEntity'])) {
            $errors[] = [
                'field' => 'mainEntity',
                'message' => 'FAQPage must have mainEntity with Question items',
            ];

            return SchemaValidationResult::invalid($errors, $warnings, $schema);
        }

        if (! is_array($schema['mainEntity'])) {
            $errors[] = [
                'field' => 'mainEntity',
                'message' => 'mainEntity must be an array of Question items',
            ];

            return SchemaValidationResult::invalid($errors, $warnings, $schema);
        }

        // Validate each question
        foreach ($schema['mainEntity'] as $index => $question) {
            if (! is_array($question)) {
                $errors[] = [
                    'field' => "mainEntity[{$index}]",
                    'message' => 'Each mainEntity item must be a Question object',
                ];
                continue;
            }

            if (($question['@type'] ?? '') !== 'Question') {
                $errors[] = [
                    'field' => "mainEntity[{$index}].@type",
                    'message' => 'Each mainEntity item must have @type "Question"',
                ];
            }

            if (empty($question['name'])) {
                $errors[] = [
                    'field' => "mainEntity[{$index}].name",
                    'message' => 'Question must have a name (the question text)',
                ];
            }

            if (! isset($question['acceptedAnswer'])) {
                $errors[] = [
                    'field' => "mainEntity[{$index}].acceptedAnswer",
                    'message' => 'Question must have an acceptedAnswer',
                ];
            } elseif (! is_array($question['acceptedAnswer']) || empty($question['acceptedAnswer']['text'] ?? null)) {
                // A non-array acceptedAnswer (a bare string) has no text key and
                // would throw on string-offset access — treat it as missing text.
                $errors[] = [
                    'field' => "mainEntity[{$index}].acceptedAnswer.text",
                    'message' => 'Answer must have text content',
                ];
            }
        }

        // Warning for too few questions
        if (count($schema['mainEntity']) < 2) {
            $warnings[] = [
                'field' => 'mainEntity',
                'message' => 'FAQ pages typically have multiple questions for best results',
            ];
        }

        return empty($errors)
            ? SchemaValidationResult::valid($schema, $warnings)
            : SchemaValidationResult::invalid($errors, $warnings, $schema);
    }

    /**
     * Validate BreadcrumbList schema.
     *
     * Required by Google:
     * - itemListElement (array of ListItem)
     *
     * @param array<string, mixed> $schema
     */
    public function validateBreadcrumb(array $schema): SchemaValidationResult
    {
        $errors = [];
        $warnings = [];

        // Required: itemListElement
        if (! isset($schema['itemListElement'])) {
            $errors[] = [
                'field' => 'itemListElement',
                'message' => 'BreadcrumbList must have itemListElement array',
            ];

            return SchemaValidationResult::invalid($errors, $warnings, $schema);
        }

        if (! is_array($schema['itemListElement'])) {
            $errors[] = [
                'field' => 'itemListElement',
                'message' => 'itemListElement must be an array',
            ];

            return SchemaValidationResult::invalid($errors, $warnings, $schema);
        }

        // Validate each item
        $positions = [];
        foreach ($schema['itemListElement'] as $index => $item) {
            // A non-array entry (scalar / null) cannot be a ListItem and would
            // throw on string-offset access below — report and skip it.
            if (! is_array($item)) {
                $errors[] = [
                    'field' => "itemListElement[{$index}]",
                    'message' => 'Each item must be a ListItem object',
                ];
                continue;
            }

            if (($item['@type'] ?? '') !== 'ListItem') {
                $errors[] = [
                    'field' => "itemListElement[{$index}].@type",
                    'message' => 'Each item must have @type "ListItem"',
                ];
            }

            if (! isset($item['position'])) {
                $errors[] = [
                    'field' => "itemListElement[{$index}].position",
                    'message' => 'ListItem must have a position',
                ];
            } else {
                if (in_array($item['position'], $positions, true)) {
                    $errors[] = [
                        'field' => "itemListElement[{$index}].position",
                        'message' => 'Position must be unique',
                    ];
                }
                $positions[] = $item['position'];
            }

            if (empty($item['name'])) {
                $errors[] = [
                    'field' => "itemListElement[{$index}].name",
                    'message' => 'ListItem must have a name',
                ];
            }

            if (empty($item['item']) && $index < count($schema['itemListElement']) - 1) {
                // Last item doesn't need URL (current page)
                $errors[] = [
                    'field' => "itemListElement[{$index}].item",
                    'message' => 'ListItem must have an item URL (except last item)',
                ];
            }
        }

        return empty($errors)
            ? SchemaValidationResult::valid($schema, $warnings)
            : SchemaValidationResult::invalid($errors, $warnings, $schema);
    }

    /**
     * Validate LocalBusiness schema.
     *
     * Required by Google:
     * - name
     * - address
     *
     * Recommended:
     * - telephone
     * - openingHours
     * - geo
     *
     * @param array<string, mixed> $schema
     */
    public function validateLocalBusiness(array $schema): SchemaValidationResult
    {
        $errors = [];
        $warnings = [];

        // Required fields
        $required = ['name', 'address'];
        $errors = array_merge($errors, $this->checkRequiredFields($schema, $required));

        // Validate address structure
        if (isset($schema['address'])) {
            if (! is_array($schema['address'])) {
                $errors[] = [
                    'field' => 'address',
                    'message' => 'address must be a PostalAddress object',
                ];
            } else {
                if (empty($schema['address']['streetAddress'] ?? null)) {
                    $warnings[] = [
                        'field' => 'address.streetAddress',
                        'message' => 'streetAddress is recommended',
                    ];
                }
                if (empty($schema['address']['addressLocality'] ?? null)) {
                    $warnings[] = [
                        'field' => 'address.addressLocality',
                        'message' => 'addressLocality (city) is recommended',
                    ];
                }
            }
        }

        // Recommended fields
        if (! isset($schema['telephone'])) {
            $warnings[] = [
                'field' => 'telephone',
                'message' => 'telephone is recommended for LocalBusiness',
            ];
        }

        if (! isset($schema['openingHours'])) {
            $warnings[] = [
                'field' => 'openingHours',
                'message' => 'openingHours is recommended for LocalBusiness',
            ];
        }

        if (! isset($schema['geo'])) {
            $warnings[] = [
                'field' => 'geo',
                'message' => 'geo coordinates are recommended for LocalBusiness',
            ];
        }

        return empty($errors)
            ? SchemaValidationResult::valid($schema, $warnings)
            : SchemaValidationResult::invalid($errors, $warnings, $schema);
    }

    /**
     * Validate Product schema.
     *
     * Required by Google:
     * - name
     * - image
     * - offers (with price and availability)
     *
     * Recommended:
     * - description
     * - brand
     * - sku
     * - aggregateRating
     *
     * @param array<string, mixed> $schema
     */
    public function validateProduct(array $schema): SchemaValidationResult
    {
        $errors = [];
        $warnings = [];

        // Required fields
        $required = ['name', 'image'];
        $errors = array_merge($errors, $this->checkRequiredFields($schema, $required));

        // Validate offers
        if (! isset($schema['offers'])) {
            $errors[] = [
                'field' => 'offers',
                'message' => 'Product must have offers with price information',
            ];
        } elseif (! is_array($schema['offers'])) {
            // A malformed scalar offers (e.g. a bare price string) cannot carry
            // the required keys — report it as invalid rather than indexing a
            // non-array.
            $errors[] = [
                'field' => 'offers',
                'message' => 'offers must be an Offer object with price information',
            ];
        } else {
            $offers = $schema['offers'];
            if (! isset($offers['price'])) {
                $errors[] = [
                    'field' => 'offers.price',
                    'message' => 'Offer must have a price',
                ];
            }
            if (! isset($offers['priceCurrency'])) {
                $errors[] = [
                    'field' => 'offers.priceCurrency',
                    'message' => 'Offer must have a priceCurrency',
                ];
            }
            if (! isset($offers['availability'])) {
                $warnings[] = [
                    'field' => 'offers.availability',
                    'message' => 'availability is recommended for Product offers',
                ];
            }
        }

        // Recommended fields
        if (! isset($schema['description'])) {
            $warnings[] = [
                'field' => 'description',
                'message' => 'description is recommended for Product',
            ];
        }

        if (! isset($schema['brand'])) {
            $warnings[] = [
                'field' => 'brand',
                'message' => 'brand is recommended for Product',
            ];
        }

        if (! isset($schema['sku']) && ! isset($schema['gtin']) && ! isset($schema['mpn'])) {
            $warnings[] = [
                'field' => 'identifier',
                'message' => 'At least one of sku, gtin, or mpn is recommended',
            ];
        }

        return empty($errors)
            ? SchemaValidationResult::valid($schema, $warnings)
            : SchemaValidationResult::invalid($errors, $warnings, $schema);
    }

    /**
     * Validate Organization schema.
     *
     * Required by Google:
     * - name
     * - url
     *
     * Recommended:
     * - logo
     * - sameAs (social profiles)
     *
     * @param array<string, mixed> $schema
     */
    public function validateOrganization(array $schema): SchemaValidationResult
    {
        $errors = [];
        $warnings = [];

        // Required fields
        $required = ['name', 'url'];
        $errors = array_merge($errors, $this->checkRequiredFields($schema, $required));

        // Validate URL
        if (isset($schema['url']) && ! $this->validateUrl($schema['url'])) {
            $errors[] = [
                'field' => 'url',
                'message' => 'url must be a valid URL',
            ];
        }

        // Validate logo URL
        if (isset($schema['logo'])) {
            $logoUrl = is_array($schema['logo'])
                ? ($schema['logo']['url'] ?? null)
                : $schema['logo'];

            if ($logoUrl && ! $this->validateUrl($logoUrl)) {
                $errors[] = [
                    'field' => 'logo',
                    'message' => 'logo must be a valid URL',
                ];
            }
        } else {
            $warnings[] = [
                'field' => 'logo',
                'message' => 'logo is recommended for Organization',
            ];
        }

        // Validate sameAs URLs. sameAs may arrive as a scalar (a single URL
        // string) or a malformed non-iterable; normalise to a list so foreach
        // never throws a TypeError on a non-array.
        if (isset($schema['sameAs'])) {
            $sameAs = is_array($schema['sameAs']) ? $schema['sameAs'] : [$schema['sameAs']];

            foreach ($sameAs as $index => $url) {
                if (! $this->validateUrl($url)) {
                    $errors[] = [
                        'field' => "sameAs[{$index}]",
                        'message' => 'sameAs URLs must be valid',
                    ];
                }
            }
        }

        return empty($errors)
            ? SchemaValidationResult::valid($schema, $warnings)
            : SchemaValidationResult::invalid($errors, $warnings, $schema);
    }

    /**
     * Generic validation for unknown schema types.
     *
     * @param array<string, mixed> $schema
     */
    protected function validateGeneric(array $schema): SchemaValidationResult
    {
        $warnings = [];

        // Check for @context
        if (! isset($schema['@context'])) {
            $warnings[] = [
                'field' => '@context',
                'message' => 'Schema should have @context set to "https://schema.org"',
            ];
        }

        return SchemaValidationResult::valid($schema, $warnings);
    }

    /**
     * Check for required fields.
     *
     * @param array<string, mixed> $schema
     * @param array<int, string> $required
     * @return array<int, array{field: string, message: string}>
     */
    protected function checkRequiredFields(array $schema, array $required): array
    {
        $errors = [];

        foreach ($required as $field) {
            if (! isset($schema[$field]) || (is_string($schema[$field]) && empty(trim($schema[$field])))) {
                $errors[] = [
                    'field' => $field,
                    'message' => "{$field} is required",
                ];
            }
        }

        return $errors;
    }

    /**
     * Validate URL format.
     *
     * Accepts mixed: schema arrays come from user input (and from
     * SEOData::fromArray, which does not validate shapes), so a non-string
     * value here must report invalid rather than throw a TypeError.
     */
    protected function validateUrl(mixed $url): bool
    {
        if (! is_string($url)) {
            return false;
        }

        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }

    /**
     * Validate ISO 8601 date format.
     */
    protected function validateDate(string $date): bool
    {
        // Try parsing as ISO 8601
        $parsed = \DateTime::createFromFormat(\DateTime::ATOM, $date);

        if ($parsed !== false) {
            return true;
        }

        // Try other common formats
        $formats = [
            'Y-m-d',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s.uP',
        ];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed !== false) {
                return true;
            }
        }

        return false;
    }
}
