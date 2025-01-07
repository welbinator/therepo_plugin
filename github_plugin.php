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
