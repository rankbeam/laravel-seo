<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SEO Suite Language Lines
    |--------------------------------------------------------------------------
    */

    'title' => 'SEO',
    'description' => 'Search Engine Optimization',

    // Analysis statuses
    'status' => [
        'pass' => 'Pass',
        'warning' => 'Warning',
        'fail' => 'Fail',
        'skip' => 'Skipped',
    ],

    // Score grades
    'grades' => [
        'excellent' => 'Excellent',
        'good' => 'Good',
        'needs_improvement' => 'Needs Improvement',
        'poor' => 'Poor',
    ],

    // Rule messages
    'rules' => [
        'keyword_density' => [
            'name' => 'Keyword Density',
            'pass' => 'Keyword density is :density% (optimal range: 1-2.5%)',
            'warning_low' => 'Keyword density is low at :density%',
            'warning_high' => 'Keyword density is high at :density%',
            'fail' => 'Keyword density is :density%',
            'recommendation' => 'Aim for keyword density between 1-2.5%',
        ],
        'title_length' => [
            'name' => 'Title Length',
            'pass' => 'Title length is :length characters (optimal)',
            'warning_short' => 'Title is too short at :length characters',
            'warning_long' => 'Title may be truncated at :length characters',
            'fail' => 'Title is missing or invalid',
            'recommendation' => 'Keep title between 30-60 characters',
        ],
        'description_length' => [
            'name' => 'Description Length',
            'pass' => 'Description length is :length characters (optimal)',
            'warning_short' => 'Description is too short at :length characters',
            'warning_long' => 'Description may be truncated at :length characters',
            'fail' => 'Description is missing',
            'recommendation' => 'Keep description between 120-160 characters',
        ],
    ],

    // UI labels
    'labels' => [
        'seo_title' => 'SEO Title',
        'meta_description' => 'Meta Description',
        'focus_keyword' => 'Focus Keyword',
        'focus_keywords' => 'Focus Keywords',
        'primary_keyword' => 'Primary Keyword',
        'secondary_keyword' => 'Secondary Keyword',
        'synonyms' => 'Synonyms',
        'canonical_url' => 'Canonical URL',
        'robots' => 'Robots',
        'og_title' => 'Open Graph Title',
        'og_description' => 'Open Graph Description',
        'og_image' => 'Open Graph Image',
        'twitter_title' => 'Twitter Title',
        'twitter_description' => 'Twitter Description',
        'twitter_image' => 'Twitter Image',
        'schema_type' => 'Schema Type',
        'seo_score' => 'SEO Score',
        'analyze' => 'Analyze',
        'analyzing' => 'Analyzing...',
        'save' => 'Save',
        'saving' => 'Saving...',
    ],

    // Tabs
    'tabs' => [
        'basic' => 'Basic SEO',
        'social' => 'Social Media',
        'advanced' => 'Advanced',
        'analysis' => 'Analysis',
    ],

    // Character counters
    'characters' => ':count characters',
    'characters_remaining' => ':count characters remaining',
    'characters_over' => ':count characters over limit',
];
