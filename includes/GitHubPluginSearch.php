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
            __DIR__ . '/classes/GitHubSearch/PluginInstaller.php',
            __DIR__ . '/helpers/find-plugin-file.php',
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
        global $wpdb;

        // Check if the table exists before attempting to create it
        $table_name = $wpdb->prefix . 'github_repositories';
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s", 
            $table_name
        )) === $table_name;

        if (!$table_exists) {
            // Create the table if it doesn't exist
            \TheRepoPlugin\Database\RepositoryCache::create_table();
            error_log("[DEBUG] Table $table_name created during initialization.");
        } else {
            // error_log("[DEBUG] Table $table_name already exists.");
        }

        $github_base_url = 'https://api.github.com';
        $github_headers = [
            'User-Agent' => 'WordPress GitHub Plugin Search',
            'Authorization' => 'token ' . get_option('the_repo_plugin_github_pat', ''),
        ];
       
    
        // Initialize AssetsManager
        add_action('admin_enqueue_scripts', ['\TheRepoPlugin\AssetsManager', 'enqueue_assets']);
    
        // Initialize AjaxHandlers
        $ajax_handlers = new \TheRepoPlugin\AjaxHandlers\AjaxHandlers();
        add_action('wp_ajax_github_plugin_search', [$ajax_handlers, 'handle_ajax']);
        add_action('wp_ajax_nopriv_github_plugin_search', [$ajax_handlers, 'handle_ajax']);
    
        // Initialize PluginInstaller
        $plugin_installer = new \TheRepoPlugin\PluginInstaller\PluginInstaller(
            'https://api.github.com',
            ['User-Agent' => 'TheRepoPlugin']
        );
        add_action('wp_ajax_install_github_plugin', [$plugin_installer, 'handle_install']);
        add_action('wp_ajax_activate_plugin', [$plugin_installer, 'handle_activate_plugin']);
        add_action('wp_ajax_deactivate_plugin', [$plugin_installer, 'handle_deactivate_plugin']);
        add_action('wp_ajax_delete_plugin', [$plugin_installer, 'handle_delete_plugin']);

    
        
    }
    

    /**
     * Handle plugin activation tasks.
     */
    public static function on_activation() {
        // Ensure required database table is created
        \TheRepoPlugin\Database\RepositoryCache::create_table();
        
        // Initialize CronTasks
        \TheRepoPlugin\CronTasks\CronTasks::schedule_sync();
    
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
