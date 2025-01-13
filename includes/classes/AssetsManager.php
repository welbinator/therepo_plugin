<?php

namespace TheRepoPlugin;

class AssetsManager {

    /**
     * Enqueue assets for the admin interface.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public static function enqueue_assets($hook_suffix) {
        
      

        // Adjust the path to assets directory
        $plugin_url = plugin_dir_url(__DIR__); // This resolves to the root plugin directory
        
        wp_enqueue_script(
            'github-plugin-search-js',
            $plugin_url . '../assets/js/github-plugin-search.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('github-plugin-search-js', 'github_plugin_search', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);

        wp_enqueue_style(
            'github-plugin-search-css',
            $plugin_url . '../assets/css/github-plugin-search.css',
            [],
            '1.0.0'
        );
    }
}
