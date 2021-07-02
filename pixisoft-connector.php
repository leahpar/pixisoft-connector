<?php

/*
Plugin Name: Pixisoft Connector
Plugin URI: https://github.com/leahpar/pixisoft-connector
Description: Connecteur WP pour Pixisoft
Version: 0.0.1
Author: Raphaël Bacco
Author URI: https://github.com/leahpar
License: MIT
*/


/**
 * Paramétrage Plugin
 * https://github.com/jeremyHixon/RationalOptionPages
 */
if (!class_exists('RationalOptionPages')) {
    require_once('RationalOptionPages.php');
}
$pages = [
    'pixisoft' => [
        'page_title' => "Pixisoft",
        'sections' => [
            'general' => [
                'title' => "Général",
                'fields' => [
                    'option1' => [
                        'title' => "Option 1",
                    ],
                    'option2' => [
                        'title' => "Option 2",
                    ],
                    'option3' => [
                        'title' => "Option 3",
                    ],
                ],
            ],
        ],
    ],
];

$option_page = new RationalOptionPages($pages);
