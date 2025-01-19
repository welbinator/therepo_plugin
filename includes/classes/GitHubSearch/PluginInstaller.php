<?php 

namespace TheRepoPlugin\PluginInstaller;

class PluginInstaller {
    private $github_base_url;
    private $github_headers;

    public function __construct($github_base_url, $github_headers) {
        $this->github_base_url = $github_base_url;
        $this->github_headers = $github_headers;
    }

    function handle_install() {
        // Check permissions
        if (!current_user_can('install_plugins')) {
            wp_send_json_error(['message' => __('You do not have permission to install plugins.', 'the-repo-plugin')]);
        }
    
        $repo_url = isset($_POST['repo_url']) ? esc_url_raw($_POST['repo_url']) : '';
        if (empty($repo_url)) {
            wp_send_json_error(['message' => __('Invalid repository URL.', 'the-repo-plugin')]);
        }
    
        $api_url = str_replace('https://github.com/', 'https://api.github.com/repos/', $repo_url) . '/releases/latest';
        error_log('[DEBUG] GitHub API URL: ' . $api_url);
        $response = wp_remote_get($api_url, ['headers' => $this->github_headers]);
    
        if (is_wp_error($response)) {
            error_log('[DEBUG] Failed to fetch release information: ' . $response->get_error_message());
            wp_send_json_error(['message' => __('Failed to fetch release information.', 'the-repo-plugin')]);
        }
    
        $release_data = json_decode(wp_remote_retrieve_body($response), true);
        error_log('[DEBUG] Release data: ' . print_r($release_data, true));

        $zip_url = '';
        if (!empty($assets)) {
            foreach ($assets as $asset) {
                if (isset($asset['browser_download_url']) && str_ends_with($asset['name'], '.zip')) {
                    $zip_url = $asset['browser_download_url'];
                    break;
                }
            }
        }

        // Fallback to GitHub's default zipball if no asset matches
        if (empty($zip_url) && !empty($release_data['zipball_url'])) {
            $zip_url = $release_data['zipball_url'];
            error_log('[DEBUG] Falling back to zipball_url for the latest release.');
        }

        if (empty($zip_url)) {
            error_log('[DEBUG] No valid ZIP URL found in the release data.');
            wp_send_json_error(['message' => __('No downloadable ZIP found for the latest release.', 'the-repo-plugin')]);
        }

    
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    
        global $wp_filesystem;
        if (!WP_Filesystem()) {
            error_log('[DEBUG] Failed to initialize filesystem.');
            wp_send_json_error(['message' => __('Failed to initialize filesystem.', 'the-repo-plugin')]);
        }
    
        $temp_file = download_url($zip_url);
    
        if (is_wp_error($temp_file)) {
            error_log('[DEBUG] Failed to download the plugin ZIP: ' . $temp_file->get_error_message());
            wp_send_json_error(['message' => __('Failed to download the plugin ZIP.', 'the-repo-plugin')]);
        }
    
        $temp_dir = WP_CONTENT_DIR . '/uploads/temp-plugin-extract';
        if (!wp_mkdir_p($temp_dir)) {
            unlink($temp_file);
            error_log('[DEBUG] Failed to create temporary extraction directory.');
            wp_send_json_error(['message' => __('Failed to create temporary extraction directory.', 'the-repo-plugin')]);
        }
    
        $unzip_result = unzip_file($temp_file, $temp_dir);
        if (is_wp_error($unzip_result)) {
            unlink($temp_file);
            $wp_filesystem->delete($temp_dir, true);
            error_log('[DEBUG] Failed to extract the plugin ZIP: ' . $unzip_result->get_error_message());
            wp_send_json_error(['message' => __('Failed to extract the plugin ZIP.', 'the-repo-plugin')]);
        }
    
        $extracted_folders = array_diff(scandir($temp_dir), ['.', '..']);
        error_log('[DEBUG] Extracted folders: ' . print_r($extracted_folders, true));
    
        if (empty($extracted_folders)) {
            unlink($temp_file);
            $wp_filesystem->delete($temp_dir, true);
            error_log('[DEBUG] No files found in the plugin ZIP.');
            wp_send_json_error(['message' => __('No files found in the ZIP.', 'the-repo-plugin')]);
        }
    
        $plugin_folder_name = reset($extracted_folders);
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_folder_name;
    
        // Handle existing directory
        if (is_dir($plugin_dir)) {
            $wp_filesystem->delete($plugin_dir, true);
            error_log('[DEBUG] Existing plugin directory removed: ' . $plugin_dir);
        }
    
        if (!rename($temp_dir . '/' . $plugin_folder_name, $plugin_dir)) {
            unlink($temp_file);
            $wp_filesystem->delete($temp_dir, true);
            error_log('[DEBUG] Failed to move plugin to the plugins directory.');
            wp_send_json_error(['message' => __('Failed to move plugin to the plugins directory.', 'the-repo-plugin')]);
        }
    
        // Clean up temporary files
        $wp_filesystem->delete($temp_dir, true);
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
    
        if (!is_dir($plugin_dir)) {
            error_log('[DEBUG] Plugin directory was not created: ' . $plugin_folder_name);
            wp_send_json_error(['message' => __('Plugin installation failed.', 'the-repo-plugin')]);
        }
    
        error_log('[DEBUG] Successfully installed plugin. Folder name: ' . $plugin_folder_name);
    
        wp_send_json_success([
            'message' => __('Plugin installed successfully! Please activate it from the Plugins page.', 'the-repo-plugin'),
            'folder_name' => $plugin_folder_name,
        ]);
    }
    
    
    
    function handle_activate_plugin() {
        if (!current_user_can('activate_plugins')) {
            error_log('[DEBUG] User does not have permission to activate plugins.');
            wp_send_json_error(['message' => __('You do not have permission to activate plugins.', 'the-repo-plugin')]);
        }
    
        $repo_slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
        if (empty($repo_slug)) {
            error_log('[DEBUG] Missing slug in activation request.');
            wp_send_json_error(['message' => __('Missing plugin slug.', 'the-repo-plugin')]);
        }
    
        error_log('[DEBUG] Activation request for slug: ' . $repo_slug);
    
        $installed_plugins = get_plugins(); // Fetch all installed plugins
        error_log('[DEBUG] Installed plugins: ' . print_r($installed_plugins, true));
    
        $plugin_file = find_plugin_file($installed_plugins, $repo_slug);
        

    
        if (!$plugin_file) {
            error_log('[DEBUG] Plugin file not found for slug: ' . $repo_slug);
            wp_send_json_error(['message' => __('Plugin not found.', 'the-repo-plugin')]);
        }
    
        $result = activate_plugin($plugin_file);
        if (is_wp_error($result)) {
            error_log('[DEBUG] Activation error: ' . $result->get_error_message());
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
    
        error_log('[DEBUG] Plugin activated successfully: ' . $plugin_file);
        wp_send_json_success(['message' => __('Plugin activated successfully.', 'the-repo-plugin')]);
    }
    
    function handle_deactivate_plugin() {
        error_log('[DEBUG] Raw POST data: ' . print_r($_POST, true));
        // Check permissions
        if (!current_user_can('activate_plugins')) {
            error_log('[DEBUG] User does not have permission to deactivate plugins.');
            wp_send_json_error(['message' => __('You do not have permission to deactivate plugins.', 'the-repo-plugin')]);
        }
    
        // Log incoming POST data
        error_log('[DEBUG] Received POST data: ' . print_r($_POST, true));
    
        // Sanitize and validate slug
        $repo_slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
        error_log('[DEBUG] Received slug in deactivate_plugin: ' . $repo_slug);
        if (empty($repo_slug)) {
            error_log('[DEBUG] Missing slug in deactivation request.');
            wp_send_json_error(['message' => __('Missing plugin slug.', 'the-repo-plugin')]);
        }
    
        // Log the slug being searched
        error_log('[DEBUG] Attempting to deactivate plugin with slug: ' . $repo_slug);
    
        // Fetch installed plugins
        $installed_plugins = get_plugins(); // Fetch all installed plugins
        error_log('[DEBUG] Installed plugins: ' . print_r($installed_plugins, true));
    
        // Find the plugin file
        $plugin_file = find_plugin_file($installed_plugins, $repo_slug);
        
        error_log('[DEBUG] Matched plugin file: ' . ($plugin_file ?: 'Not Found'));
    
        // Handle missing plugin file
        if (!$plugin_file) {
            error_log('[DEBUG] Plugin file not found for slug: ' . $repo_slug);
            wp_send_json_error(['message' => __('Plugin not found.', 'the-repo-plugin')]);
        }
    
        // Attempt to deactivate the plugin
        deactivate_plugins($plugin_file);
    
        // Check if deactivation was successful
        if (!is_plugin_active($plugin_file)) {
            error_log('[DEBUG] Plugin deactivated successfully: ' . $plugin_file);
            wp_send_json_success(['message' => __('Plugin deactivated successfully.', 'the-repo-plugin')]);
        } else {
            error_log('[DEBUG] Failed to deactivate the plugin: ' . $plugin_file);
            wp_send_json_error(['message' => __('Failed to deactivate the plugin.', 'the-repo-plugin')]);
        }
    }
    
    
    

    function handle_delete_plugin() {
        if (!current_user_can('delete_plugins')) {
            wp_send_json_error(['message' => __('You do not have permission to delete plugins.', 'the-repo-plugin')]);
        }
    
        $repo_slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
        if (empty($repo_slug)) {
            wp_send_json_error(['message' => __('Missing plugin slug.', 'the-repo-plugin')]);
        }
    
        error_log('[DEBUG] Attempting to delete plugin with slug: ' . $repo_slug);
    
        $installed_plugins = get_plugins(); // Fetch all installed plugins
        $plugin_file = find_plugin_file($installed_plugins, $repo_slug);
    
        if (!$plugin_file) {
            error_log('[DEBUG] Plugin file not found for slug: ' . $repo_slug);
            wp_send_json_error(['message' => __('Plugin not found.', 'the-repo-plugin')]);
        }
    
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin_file);
    
        // Delete the plugin directory
        global $wp_filesystem;
        if (!WP_Filesystem()) {
            wp_send_json_error(['message' => __('Failed to initialize filesystem.', 'the-repo-plugin')]);
        }
    
        $result = $wp_filesystem->delete($plugin_dir, true);
    
        if ($result) {
            error_log('[DEBUG] Plugin deleted successfully: ' . $plugin_dir);
            wp_send_json_success(['message' => __('Plugin deleted successfully.', 'the-repo-plugin')]);
        } else {
            error_log('[DEBUG] Failed to delete plugin directory: ' . $plugin_dir);
            wp_send_json_error(['message' => __('Failed to delete the plugin.', 'the-repo-plugin')]);
        }
    }
    
}
