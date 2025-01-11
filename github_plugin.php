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

// Load the main plugin class
require_once __DIR__ . '/includes/GitHubPluginSearch.php';

// Initialize the plugin
new \TheRepoPlugin\GitHubPluginSearch\GitHubPluginSearch();