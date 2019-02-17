<?php

/**
 * This file contains the blogs default configuration. If you want to adjust the configuration copy this
 * file to "config.php" in the same folder and adjust it. This will prevent your configuration form being
 * overwritten by an update.
 */
return [

    'paths' => [
        // Path to view and layout files
        'templates' => __DIR__ . '/../resources/views/',

        // Path to your article files
        'articles' => __DIR__ . '/../resources/articles/',

        // Path to your public folder
        'public' => __DIR__ . '/../public/',

        // Path to store automatically generated thumbnails
        'thumbs' => __DIR__ . '/../storage/.thumbs/',
    ],

    // Define unicode category icons here
    'category_icons' => [
        //'news' => '&#128463;'
    ],

    // This section configures page-urls and meta-data:
    'pages' => [

        'blog' => [
            'parse_pattern' => '/^\/$/', // patterns need be be a valid regular expression
            'build_pattern' => '/',

            'meta_title' => 'Blog',
            'meta_description' => '',
            'meta_robots' => 'index, follow',
        ],

        'feed' => [
            'parse_pattern' => '/^\/blog\/feed$/',
            'build_pattern' => '/blog/feed',

            'channel_title' => 'RSS Feed',
            'channel_description' => '',
            'max_articles' => 15,
        ],

        'article' => [
            'parse_pattern' => '/^\/blog\/(.+)$/',
            'build_pattern' => '/blog/:slug',
        ],

        'image' => [
            'parse_pattern' => '/\/image\/([0-9]+)x([0-9]+)\/(.+)/',
            'build_pattern' => '/image/:widthx:height/:path',
        ],

        // Add your custom pages here:

        'about' => [
            'parse_pattern' => '/^\/about$/',
            'build_pattern' => '/about',

            'view' => 'about',

            'meta_title' => 'About',
            'meta_description' => 'About',
            'meta_robots' => 'noindex, follow',
        ]
    ]
];
