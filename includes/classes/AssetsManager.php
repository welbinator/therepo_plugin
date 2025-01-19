<?php

namespace TheRepoPlugin;

class AssetsManager {

    /**
     * Enqueue assets for the admin interface.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public static function enqueue_assets($hook_suffix) {
        // Verify the page where assets should be enqueued
        if (strpos($hook_suffix, 'the-repo-plugin') === false) {
            return; // Only enqueue assets on relevant plugin pages
        }

        // Adjust the path to assets directory
        $plugin_url = plugin_dir_url(__DIR__);

        // Enqueue JavaScript file
        wp_enqueue_script(
            'github-plugin-search-js',
            $plugin_url . '../assets/js/github-plugin-search.js',
            ['jquery'],
            '1.0.0',
            true
        );

        // Localize script with AJAX URL
        wp_localize_script('github-plugin-search-js', 'github_plugin_search', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);

        // Enqueue CSS file
        wp_enqueue_style(
            'github-plugin-search-css',
            $plugin_url . '../assets/css/github-plugin-search.css',
            [],
            '1.0.0'
        );
    }
}
