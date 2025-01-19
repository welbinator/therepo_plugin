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

        // Set up activation and deactivation hooks
        register_activation_hook(plugin_basename(__FILE__), [__CLASS__, 'on_activation']);
        register_deactivation_hook(plugin_basename(__FILE__), [__CLASS__, 'on_deactivation']);
    }

    /**
     * Include necessary files required for the plugin functionality.
     */
    private function include_files() {
        $required_files = [
            __DIR__ . '/database/RepositoryCache.php',
            __DIR__ . '/cron/CronTasks.php',
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
        // Initialize RepositoryCache
        \TheRepoPlugin\Database\RepositoryCache::create_table();
    
        // Initialize AssetsManager
        add_action('admin_enqueue_scripts', ['\TheRepoPlugin\AssetsManager', 'enqueue_assets']);
    
        // Initialize AjaxHandlers
        $ajax_handlers = new \TheRepoPlugin\AjaxHandlers\AjaxHandlers();
        add_action('wp_ajax_github_plugin_search', [$ajax_handlers, 'handle_ajax']);
        add_action('wp_ajax_nopriv_github_plugin_search', [$ajax_handlers, 'handle_ajax']);
    
        // Initialize CronTasks
        \TheRepoPlugin\CronTasks\CronTasks::schedule_sync();
    }
    

    /**
     * Handle plugin activation tasks.
     */
    public static function on_activation() {
        // Ensure required database table is created
        \TheRepoPlugin\Database\RepositoryCache::create_table();

        // Schedule cron tasks
        \TheRepoPlugin\CronTasks\CronTasks::schedule_sync();

        // Perform an initial GitHub data fetch
        try {
            \TheRepoPlugin\CronTasks\CronTasks::sync_repositories_to_db();
            error_log('[DEBUG] GitHub data fetch completed successfully during activation.');
        } catch (\Exception $e) {
            error_log('[ERROR] Failed to sync GitHub repositories on activation: ' . $e->getMessage());
        }
    }

    /**
     * Handle plugin deactivation tasks.
     */
    public static function on_deactivation() {
        try {
            // Unschedule cron tasks
            \TheRepoPlugin\CronTasks\CronTasks::unschedule_sync();
            error_log('[DEBUG] Cron tasks unscheduled during deactivation.');
        } catch (\Exception $e) {
            error_log('[ERROR] Failed to unschedule cron tasks on deactivation: ' . $e->getMessage());
        }
    }
}
