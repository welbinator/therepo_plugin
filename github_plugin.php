<?php
/**
 * Plugin Name: The Repo Plugin
 * Description: A plugin that allows you to browse, and install WordPress plugins from GitHub
 * Version: 1.0.0
 * Author: James Welbes
 * Text Domain: the-repo-plugin
 */

 namespace TheRepoPlugin;

 // Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define the plugin version as a constant.
define('THE_REPO_PLUGIN_VERSION', '1.0.0');

// Include necessary files.
require_once plugin_dir_path(__FILE__) . 'includes/class-github-plugin-search.php';
require_once plugin_dir_path(__FILE__) . 'admin/admin-settings.php';

add_action('init', function() {
    delete_transient('github_search_' . md5('youtube'));
});

function has_github_releases($repo_url) {
    // Convert GitHub repo URL to API releases endpoint
    $api_url = str_replace('https://github.com/', 'https://api.github.com/repos/', rtrim($repo_url, '/')) . '/releases';

    // Set up headers (optional: use a personal access token for higher rate limits)
    $headers = [
        'User-Agent' => 'GitHub Release Checker',
    ];

    // Perform the request
    $response = wp_remote_get($api_url, ['headers' => $headers]);

    // Check for errors in the response
    if (is_wp_error($response)) {
        error_log('GitHub API error: ' . $response->get_error_message());
        return false;
    }

    // Parse the response body
    $releases = json_decode(wp_remote_retrieve_body($response), true);

    // Check if the response contains valid releases
    return !empty($releases) && is_array($releases);
}

$repo_url = 'https://github.com/lbell/pretty-google-calendar';
if (has_github_releases($repo_url)) {
    error_log("The repository has releases.");
} else {
    error_log("No releases found for this repository.");
}