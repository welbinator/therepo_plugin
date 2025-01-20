<?php

namespace TheRepoPlugin;

class AssetsManager {

    /**
     * Enqueue assets for the admin interface.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public static function enqueue_assets($hook_suffix) {
        $current_screen = get_current_screen();

        // Verify the page where assets should be enqueued
        if (strpos($hook_suffix, 'the-repo-plugin') === false) {
            return; // Only enqueue assets on relevant plugin pages
        }

        // Adjust the path to assets directory
        $plugin_url = plugin_dir_url(__DIR__);

         // Check if the current screen matches the GitHub Plugins page
        if ($current_screen && $current_screen->id === 'the-repo-plugin_page_the-repo-plugin-github-plugins') {
        
            // Enqueue the JavaScript file for this page only
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
        }

        // Enqueue CSS file
        wp_enqueue_style(
            'github-plugin-search-css',
            $plugin_url . '../assets/css/github-plugin-search.css',
            [],
            '1.0.0'
        );

         // Check if the current screen matches the settings page
        if ($current_screen && $current_screen->id === 'toplevel_page_the-repo-plugin-settings') {

            // Enqueue the JavaScript file for this page only
            wp_enqueue_script(
                'repo-plugin-admin-js',
                $plugin_url . '../assets/js/admin.js',
                ['jquery'],
                '1.0.0',
                true
            );
        }

        // Localize script with AJAX URL and nonce for admin settings
        wp_localize_script('repo-plugin-admin-js', 'theRepoPluginAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'refresh_nonce'            => wp_create_nonce('refresh_repositories_nonce'),
            'empty_repositories_nonce' => wp_create_nonce('empty_repositories_nonce'),
            'confirm_refresh_message'  => __('Are you sure you want to refresh the repositories?', 'the-repo-plugin'),
            'refreshing_message'       => __('Refreshing...', 'the-repo-plugin'),
            'success_refresh_message'  => __('Repositories refreshed successfully!', 'the-repo-plugin'),
            'error_refresh_message'    => __('An error occurred while refreshing the repositories.', 'the-repo-plugin'),
            'refresh_button_text'      => __('Refresh Repositories', 'the-repo-plugin'),
        ]);
    }
}
