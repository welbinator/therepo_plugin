<?php

namespace TheRepoPlugin\Admin\Settings;

use TheRepoPlugin\GitHubSearch\GitHubPluginSearch; // Import the class

// Register the settings page and the submenu page
add_action('admin_menu', function () {
    // Main settings page
    add_menu_page(
        __('The Repo Plugin Settings', 'the-repo-plugin'),
        __('The Repo Plugin', 'the-repo-plugin'),
        'manage_options',
        'the-repo-plugin-settings',
        __NAMESPACE__ . '\\render_main_settings_page',
        'dashicons-admin-generic',
        80
    );

    // Submenu page for GitHub Plugins
    add_submenu_page(
        'the-repo-plugin-settings', // Parent slug
        __('GitHub Plugins', 'the-repo-plugin'), // Page title
        __('GitHub Plugins', 'the-repo-plugin'), // Menu title
        'manage_options', // Capability
        'the-repo-plugin-github-plugins', // Menu slug
        __NAMESPACE__ . '\\render_github_plugins_page' // Callback function
    );
});

// Render the main settings page
function render_main_settings_page() {
    // Check if the user is allowed to access this page
    if (!current_user_can('manage_options')) {
        return;
    }

    // Save the settings if the form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['the_repo_plugin_github_pat'])) {
        check_admin_referer('the_repo_plugin_save_settings'); // Verify the nonce for security
        $github_pat = sanitize_text_field($_POST['the_repo_plugin_github_pat']);
        update_option('the_repo_plugin_github_pat', $github_pat);
        echo '<div class="updated"><p>' . __('Settings saved.', 'the-repo-plugin') . '</p></div>';
    }

    // Get the saved GitHub PAT
    $github_pat = get_option('the_repo_plugin_github_pat', '');
    ?>
    <div class="wrap">
        <h1><?php _e('The Repo Plugin Settings', 'the-repo-plugin'); ?></h1>

        <!-- Settings Form -->
        <form method="POST">
            <?php wp_nonce_field('the_repo_plugin_save_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="the_repo_plugin_github_pat"><?php _e('GitHub Personal Access Token', 'the-repo-plugin'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="the_repo_plugin_github_pat" name="the_repo_plugin_github_pat" value="<?php echo esc_attr($github_pat); ?>" class="regular-text" />
                        <p class="description">
                            <?php _e('Enter your GitHub Personal Access Token. This is required to authenticate with the GitHub API.', 'the-repo-plugin'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save Settings', 'the-repo-plugin')); ?>
        </form>
    </div>
    <?php
}

// Render the GitHub Plugins page
function render_github_plugins_page() {
    // Check if the user is allowed to access this page
    if (!current_user_can('manage_options')) {
        return;
    }

    // Create an instance of GitHubPluginSearch
    $github_search = new GitHubPluginSearch();
    ?>
    <div class="wrap">
        <h1><?php _e('GitHub Plugins', 'the-repo-plugin'); ?></h1>

        <!-- GitHub Search Form -->
        <h2><?php _e('Search GitHub Plugins', 'the-repo-plugin'); ?></h2>
        <?php $github_search->render_form(); ?>
    </div>
    <?php
}
