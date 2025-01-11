<?php

namespace TheRepoPlugin\GitHubPluginSearch;

class GitHubPluginSearch {

    /**
     * Constructor: Sets up the plugin by including necessary files and initializing classes.
     */
    public function __construct() {
        // Include required files
        $this->include_files();
        
        // Initialize classes
        $this->initialize_classes();
        
    }

    /**
     * Include necessary files required for the plugin functionality.
     */
    private function include_files() {
        $required_files = [
            __DIR__ . '/helpers/filter-repositories-by-release-date.php',
            __DIR__ . '/helpers/filter-repositories.php',
            __DIR__ . '/helpers/find-plugin-file.php',
            __DIR__ . '/helpers/get-latest-release-names.php',
            __DIR__ . '/helpers/get-latest-release-data.php',
            __DIR__ . '/helpers/has-releases.php',
            __DIR__ . '/classes/GitHubSearch/GitHubAPIHandler.php',
            __DIR__ . '/classes/GitHubSearch/PluginInstaller.php',
            __DIR__ . '/classes/GitHubSearch/AjaxHandlers.php',
            __DIR__ . '/classes/AssetsManager.php',
            __DIR__ . '/../admin/admin-settings.php',
            __DIR__ . '/classes/GitHubSearch/GitHubSearchUI.php',
        ];

        foreach ($required_files as $file) {
            if (!file_exists($file)) {
                error_log("[DEBUG] Required file missing: {$file}");
                continue;
            }
            require_once $file;
        }
    }

    /**
     * Initialize the core classes used in the plugin and register hooks.
     */
    private function initialize_classes() {
        // Common dependencies
        $github_base_url = 'https://api.github.com';
        $github_headers = [
            'User-Agent' => 'WordPress GitHub Plugin Search',
            'Authorization' => 'token ' . get_option('the_repo_plugin_github_pat', ''),
        ];
    
        // Initialize AssetsManager
        $assets_manager = new \TheRepoPlugin\AssetsManager();
        add_action('admin_enqueue_scripts', [$assets_manager, 'enqueue_assets']); // Properly register assets hook
    
        // Initialize other classes with shared dependencies
        $plugin_installer = new \TheRepoPlugin\PluginInstaller\PluginInstaller($github_base_url, $github_headers);
        $ajax_handlers = new \TheRepoPlugin\AjaxHandlers\AjaxHandlers($github_base_url, $github_headers, $plugin_installer);
    
        // Register AJAX handlers
        add_action('wp_ajax_github_plugin_search', [$ajax_handlers, 'handle_ajax']);
        add_action('wp_ajax_nopriv_github_plugin_search', [$ajax_handlers, 'handle_ajax']);
        add_action('wp_ajax_install_github_plugin', [$plugin_installer, 'handle_install']);
        add_action('wp_ajax_activate_plugin', [$plugin_installer, 'handle_activate_plugin']);
        add_action('wp_ajax_deactivate_plugin', [$plugin_installer, 'handle_deactivate_plugin']);
    
        // Initialize and register UI class
        $github_ui = new \TheRepoPlugin\RenderForm\GitHubSearchUI();
        add_action('admin_menu', function () use ($github_ui) {
            add_menu_page(
                'GitHub Plugin Search',
                'GitHub Search',
                'manage_options',
                'github-plugin-search',
                [$github_ui, 'render_form']
            );
        });
    }
    
    
}
