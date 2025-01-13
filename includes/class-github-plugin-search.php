<?php

namespace TheRepoPlugin\GitHubSearch;

class GitHubPluginSearch {

    private $github_base_url = 'https://api.github.com';
    private $github_headers;

    function __construct() {
        $github_pat = get_option('the_repo_plugin_github_pat', '');
        $this->github_headers = [
            'User-Agent' => 'WordPress GitHub Plugin Search',
        ];
        if (!empty($github_pat)) {
            $this->github_headers['Authorization'] = 'token ' . $github_pat;
        }
    
        add_action('wp_ajax_github_plugin_search', [$this, 'handle_ajax']);
        add_action('wp_ajax_nopriv_github_plugin_search', [$this, 'handle_ajax']);
        add_action('wp_ajax_install_github_plugin', [$this, 'handle_install']);
        add_action('wp_ajax_activate_plugin', [$this, 'handle_activate_plugin']);
        add_action('wp_ajax_deactivate_plugin', [$this, 'handle_deactivate_plugin']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

    }
   
    function enqueue_assets() {
       
        wp_enqueue_script(
            'github-plugin-search-js',
            plugin_dir_url(__FILE__) . '../assets/js/github-plugin-search.js',
            ['jquery'], // Add dependencies if needed
            '1.0.0',
            true
        );
    
        wp_localize_script('github-plugin-search-js', 'github_plugin_search', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    
        wp_enqueue_style(
            'github-plugin-search-css',
            plugin_dir_url(__FILE__) . '../assets/css/github-plugin-search.css',
            [],
            '1.0.0'
        );
    }
    

    function handle_ajax() {
        if (!isset($_GET['query']) || empty($_GET['query'])) {
            error_log('[DEBUG] Query parameter is missing or empty.');
            wp_send_json_error(['error' => 'Query parameter is missing.']);
        }
    
        $query = sanitize_text_field($_GET['query']);
        $page = isset($_GET['page']) ? absint($_GET['page']) : 1;
    
        error_log('[DEBUG] Received query: ' . $query . ', Page: ' . $page);
    
        // Process the API request
        $topics = ['wordpress-plugin'];
        $all_results = [];
        foreach ($topics as $topic) {
            $api_query = urlencode($query) . "+topic:" . $topic;
            $api_url = "{$this->github_base_url}/search/repositories?q=" . $api_query . "&per_page=100";
    
            $response = wp_remote_get($api_url, ['headers' => $this->github_headers]);
            if (is_wp_error($response)) {
                error_log('[DEBUG] GitHub API Error: ' . $response->get_error_message());
                continue;
            }
    
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);
    
            if (!isset($data['items']) || !is_array($data['items'])) {
                error_log('[DEBUG] Invalid or missing "items" in GitHub API response: ' . $response_body);
                continue;
            }
    
            $all_results = array_merge($all_results, $data['items']);
        }
    
        $unique_results = array_map('unserialize', array_unique(array_map('serialize', $all_results)));
        $filtered_results = $this->filter_repositories_by_release_date($unique_results);
    
        error_log('[DEBUG] Filtered results count: ' . count($filtered_results));
    
        $paginated_results = array_slice($filtered_results, ($page - 1) * 12, 12);
    
        wp_send_json([
            'results' => $paginated_results,
            'total_pages' => ceil(count($filtered_results) / 12),
        ]);
    }
    
    
    function get_latest_release_zip_name($repo_url) {
        $api_url = str_replace('https://github.com/', 'https://api.github.com/repos/', rtrim($repo_url, '/')) . '/releases/latest';
    
        $response = wp_remote_get($api_url, ['headers' => $this->github_headers]);
    
        if (is_wp_error($response)) {
            error_log('[DEBUG] Failed to fetch latest release for ' . $repo_url . ': ' . $response->get_error_message());
            return null;
        }
    
        $release_data = json_decode(wp_remote_retrieve_body($response), true);
    
        if (isset($release_data['assets'][0]['name'])) {
            $zip_name = strtolower(pathinfo($release_data['assets'][0]['name'], PATHINFO_FILENAME));
            error_log('[DEBUG] Latest release ZIP name for ' . $repo_url . ': ' . $zip_name);
            return $zip_name;
        }
    
        error_log('[DEBUG] No ZIP name found for latest release of ' . $repo_url);
        return null;
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
        $response = wp_remote_get($api_url, ['headers' => $this->github_headers]);
    
        if (is_wp_error($response)) {
            error_log('[DEBUG] Failed to fetch release information: ' . $response->get_error_message());
            wp_send_json_error(['message' => __('Failed to fetch release information.', 'the-repo-plugin')]);
        }
    
        $release_data = json_decode(wp_remote_retrieve_body($response), true);
        $zip_url = '';
        $assets = isset($release_data['assets']) ? $release_data['assets'] : [];

        // Search for the first custom ZIP asset
        foreach ($assets as $asset) {
            if (isset($asset['name']) && isset($asset['browser_download_url']) && str_ends_with($asset['name'], '.zip')) {
                $zip_url = $asset['browser_download_url'];
                break;
            }
        }


        if (empty($zip_url)) {
            if (!empty($release_data['zipball_url'])) {
                $zip_url = $release_data['zipball_url'];
                error_log('[DEBUG] Falling back to Source code (zip) for the latest release.');
            } else {
                error_log('[DEBUG] No downloadable ZIP found for the latest release.');
                wp_send_json_error(['message' => __('No downloadable ZIP found for the latest release.', 'the-repo-plugin')]);
            }
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

        // Check if the extracted ZIP has a root folder
        if (count($extracted_folders) === 1 && is_dir($temp_dir . '/' . reset($extracted_folders))) {
            // Proper root folder exists
            $plugin_folder_name = reset($extracted_folders);
        } else {
            // No root folder - Create one
            $plugin_folder_name = sanitize_file_name(basename($temp_file, '.zip'));
            $new_plugin_dir = $temp_dir . '/' . $plugin_folder_name;

            if (!mkdir($new_plugin_dir, 0755)) {
                unlink($temp_file);
                $wp_filesystem->delete($temp_dir, true);
                error_log('[DEBUG] Failed to create root folder for plugin.');
                wp_send_json_error(['message' => __('Failed to create root folder for plugin.', 'the-repo-plugin')]);
            }

            // Move all extracted files into the new root folder
            foreach ($extracted_folders as $file) {
                rename($temp_dir . '/' . $file, $new_plugin_dir . '/' . $file);
            }
        }

        // Verify the new plugin directory
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_folder_name;

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
            wp_send_json_error(['message' => __('You do not have permission to activate plugins.', 'the-repo-plugin')]);
        }
    
        $repo_slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
        if (empty($repo_slug)) {
            wp_send_json_error(['message' => __('Missing plugin slug.', 'the-repo-plugin')]);
        }
    
        error_log('[DEBUG] Attempting to activate plugin with slug: ' . $repo_slug);
    
        $installed_plugins = get_plugins(); // Fetch all installed plugins
    
        // Match plugin file by folder name, basename, or slug
        $plugin_file = $this->find_plugin_file($installed_plugins, $repo_slug);
    
        if (!$plugin_file) {
            error_log('[DEBUG] Plugin file not found for slug: ' . $repo_slug);
            wp_send_json_error(['message' => __('Plugin not found.', 'the-repo-plugin')]);
        }
    
        error_log('[DEBUG] Found plugin file for activation: ' . $plugin_file);
    
        // Attempt to activate the plugin
        $result = activate_plugin($plugin_file);
        if (is_wp_error($result)) {
            error_log('[DEBUG] Activation error: ' . $result->get_error_message());
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
    
        error_log('[DEBUG] Plugin activated successfully: ' . $plugin_file);
        wp_send_json_success(['message' => __('Plugin activated successfully.', 'the-repo-plugin')]);
    }
    
    
    function handle_deactivate_plugin() {
        if (!current_user_can('activate_plugins')) {
            wp_send_json_error(['message' => __('You do not have permission to deactivate plugins.', 'the-repo-plugin')]);
        }
    
        $repo_slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
        if (empty($repo_slug)) {
            wp_send_json_error(['message' => __('Missing plugin slug.', 'the-repo-plugin')]);
        }
    
        error_log('[DEBUG] Attempting to deactivate plugin with slug: ' . $repo_slug);
    
        $installed_plugins = get_plugins(); // Fetch all installed plugins
    
        // Match plugin file by folder name, basename, or slug
        $plugin_file = $this->find_plugin_file($installed_plugins, $repo_slug);
    
        if (!$plugin_file) {
            error_log('[DEBUG] Plugin file not found for slug: ' . $repo_slug);
            wp_send_json_error(['message' => __('Plugin not found.', 'the-repo-plugin')]);
        }
    
        deactivate_plugins($plugin_file);
    
        if (!is_plugin_active($plugin_file)) {
            error_log('[DEBUG] Plugin deactivated successfully: ' . $plugin_file);
            wp_send_json_success(['message' => __('Plugin deactivated successfully.', 'the-repo-plugin')]);
        } else {
            wp_send_json_error(['message' => __('Failed to deactivate the plugin.', 'the-repo-plugin')]);
        }
    }
    
    
    function find_plugin_file($installed_plugins, $repo_slug) {
        error_log('[DEBUG] Searching for plugin file with slug: ' . $repo_slug);
    
        // Normalize slug for matching
        $normalized_slug = strtolower($repo_slug);
    
        foreach ($installed_plugins as $file => $data) {
            $plugin_folder = strtolower(dirname($file));
            $plugin_basename = strtolower(basename($file, '.php'));
            $plugin_title = sanitize_title($data['Name']);
    
            error_log("[DEBUG] Checking plugin: Folder: $plugin_folder, Basename: $plugin_basename, Title: $plugin_title");
    
            // Match against folder name, basename, or sanitized title
            if (
                $plugin_folder === $normalized_slug || 
                $plugin_basename === $normalized_slug || 
                $plugin_title === $normalized_slug
            ) {
                error_log('[DEBUG] Match found: ' . $file);
                return $file;
            }
    
            // Check if the slug is contained within the folder name or title
            if (
                strpos($plugin_folder, $normalized_slug) !== false || 
                strpos($plugin_title, $normalized_slug) !== false
            ) {
                error_log('[DEBUG] Partial match found: ' . $file);
                return $file;
            }
        }
    
        // Fallback: Try matching latest release ZIP name
        $latest_release_zip_name = $this->get_latest_release_zip_name("https://github.com/$repo_slug");
        if ($latest_release_zip_name) {
            $normalized_zip_name = strtolower($latest_release_zip_name);
            foreach ($installed_plugins as $file => $data) {
                $plugin_folder = strtolower(dirname($file));
                if (
                    $plugin_folder === $normalized_zip_name || 
                    strpos($plugin_folder, $normalized_zip_name) !== false
                ) {
                    error_log('[DEBUG] Match found using ZIP name: ' . $file);
                    return $file;
                }
            }
        }
    
        error_log('[DEBUG] No match found for slug: ' . $repo_slug);
        return false;
    }


    function render_form() {
        ?>
        <div id="github-plugin-search">
            <form id="github-search-form">
                <input type="text" id="github-search-query" placeholder="Search for WordPress plugins..." />
                <button type="submit">Search</button>
            </form>
            <div id="github-search-results" class="grid"></div>
            <div id="github-search-pagination"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    function filter_repositories_by_release_date($repositories) {
        $results = [];
        $six_months_ago = strtotime('-6 months');
    
        foreach ($repositories as $repo) {
            if (isset($repo['topics']) && in_array('wordpress-plugin', $repo['topics'])) {
                $repo_url = $repo['html_url'];
    
                // Fetch latest release
                $latest_release = $this->get_latest_release_data($repo_url);
                if ($latest_release && isset($latest_release['created_at'])) {
                    $release_date = strtotime($latest_release['created_at']);
                    if ($release_date >= $six_months_ago) {
                        $results[] = [
                            'full_name'   => $repo['full_name'],
                            'html_url'    => $repo['html_url'],
                            'description' => $repo['description'] ?: 'No description available.',
                            'homepage'    => !empty($repo['homepage']) ? esc_url($repo['homepage']) : null,
                        ];
                    }
                }
            }
        }
    
        return $results;
    }
    
    function get_latest_release_data($repo_url) {
        $api_url = str_replace('https://github.com/', 'https://api.github.com/repos/', rtrim($repo_url, '/')) . '/releases/latest';
        $response = wp_remote_get($api_url, ['headers' => $this->github_headers]);
    
        if (is_wp_error($response)) {
            error_log('GitHub API error for releases: ' . $response->get_error_message());
            return null;
        }
    
        $release_data = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($release_data['created_at'])) {
            error_log('[DEBUG] Latest release date for ' . $repo_url . ': ' . $release_data['created_at']);
        } else {
            error_log('[DEBUG] No releases found for ' . $repo_url);
        }
    
        return $release_data;
    }
    
    function filter_repositories($repositories) {
        $results = [];
        $cached_releases = [];
    
        foreach ($repositories as $repo) {
            if (isset($repo['topics']) && in_array('wordpress-plugin', $repo['topics'])) {
                $repo_url = $repo['html_url'];
    
                // Check if releases are cached
                $cache_key = 'repo_releases_' . md5($repo_url);
                if (isset($cached_releases[$repo_url])) {
                    $has_releases = $cached_releases[$repo_url];
                } else {
                    $cached = get_transient($cache_key);
                    if ($cached !== false) {
                        $has_releases = $cached;
                    } else {
                        // If not cached, check for releases
                        $has_releases = $this->has_releases($repo_url);
                        set_transient($cache_key, $has_releases, DAY_IN_SECONDS);
                    }
                    $cached_releases[$repo_url] = $has_releases;
                }
    
                if ($has_releases) {
                    $results[] = [
                        'full_name'   => $repo['full_name'],
                        'html_url'    => $repo['html_url'],
                        'description' => $repo['description'] ?: 'No description available.',
                        'homepage'    => !empty($repo['homepage']) ? esc_url($repo['homepage']) : null,
                    ];
                }
            }
        }
    
        return $results;
    }
    
    
    function has_releases($repo_url) {
        $api_url = str_replace('https://github.com/', 'https://api.github.com/repos/', rtrim($repo_url, '/')) . '/releases';
    
        $response = wp_remote_get($api_url, ['headers' => $this->github_headers]);
    
        if (is_wp_error($response)) {
            error_log('GitHub API error for releases: ' . $response->get_error_message());
            return false;
        }
    
        $releases = json_decode(wp_remote_retrieve_body($response), true);
    
        // Return true if releases exist and are valid
        return !empty($releases) && is_array($releases);
    }
    
    

     
 
}



new GitHubPluginSearch();