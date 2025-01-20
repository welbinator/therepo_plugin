<?php

namespace TheRepoPlugin\Admin\Settings;

use TheRepoPlugin\CronTasks\CronTasks; // Import the CronTasks class

// Register the settings page and the submenu page
add_action('admin_menu', function () {
    add_menu_page(
        __('The Repo Plugin Settings', 'the-repo-plugin'),
        __('The Repo Plugin', 'the-repo-plugin'),
        'manage_options',
        'the-repo-plugin-settings',
        __NAMESPACE__ . '\\render_main_settings_page',
        'dashicons-admin-generic',
        80
    );

    add_submenu_page(
        'the-repo-plugin-settings',
        __('GitHub Plugins', 'the-repo-plugin'),
        __('GitHub Plugins', 'the-repo-plugin'),
        'manage_options',
        'the-repo-plugin-github-plugins',
        __NAMESPACE__ . '\\render_github_plugins_page'
    );
});

// Render the main settings page
function render_main_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Save settings
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['the_repo_plugin_github_pat'])) {
        check_admin_referer('the_repo_plugin_save_settings');
        $github_pat = sanitize_text_field($_POST['the_repo_plugin_github_pat']);
        update_option('the_repo_plugin_github_pat', $github_pat);
        echo '<div class="updated"><p>' . __('Settings saved.', 'the-repo-plugin') . '</p></div>';
    }

    // Get saved GitHub PAT
    $github_pat = get_option('the_repo_plugin_github_pat', '');
    ?>
    <div class="wrap">
        <h1><?php _e('The Repo Plugin Settings', 'the-repo-plugin'); ?></h1>

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
<hr />
<table class="form-table">
    <tr>
        <th scope="row">
            <label for="the_repo_plugin_github_pat"><?php _e('Refresh Repositories in Database', 'the-repo-plugin'); ?></label>
        </th>
        <td>
            <button id="refresh-repositories" class="button button-primary">
                <?php _e('Refresh Repositories', 'the-repo-plugin'); ?>
            </button>
        </td>
    </tr>
</table>
       
        
    </div>

    <script>
      let isSyncing = false;

document.getElementById('refresh-repositories').addEventListener('click', function () {
    if (!confirm('<?php _e('Are you sure you want to refresh the repositories?', 'the-repo-plugin'); ?>')) {
        return;
    }

    const button = this;
    button.disabled = true;
    button.textContent = '<?php _e('Refreshing...', 'the-repo-plugin'); ?>';
    isSyncing = true;

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'refresh_repositories',
            nonce: '<?php echo wp_create_nonce('refresh_repositories_nonce'); ?>',
        }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.data.message || '<?php _e('Repositories refreshed successfully!', 'the-repo-plugin'); ?>');
            } else {
                console.error('[DEBUG] Failed to refresh repositories:', data.data.message);
            }
        })
        .catch(error => {
            console.error('[ERROR] An error occurred during the sync:', error);
        })
        .finally(() => {
            button.disabled = false;
            button.textContent = '<?php _e('Refresh Repositories', 'the-repo-plugin'); ?>';
            isSyncing = false;
        });
});

// Remove the `beforeunload` event listener entirely
window.removeEventListener('beforeunload', null);

    </script>
    <?php
}

// Render the GitHub Plugins page
function render_github_plugins_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    ?>
    <div class="wrap">
        <h1><?php _e('GitHub Plugins', 'the-repo-plugin'); ?></h1>

        <h2><?php _e('Search GitHub Plugins', 'the-repo-plugin'); ?></h2>
        <?php
        $github_search = new \TheRepoPlugin\RenderForm\GitHubSearchUI();
        $github_search->render_form();
        ?>
    </div>
    <?php
}

// AJAX handler for refreshing repositories
add_action('wp_ajax_refresh_repositories', function () {
    error_log('[DEBUG] Starting repository sync via AJAX.');

    check_ajax_referer('refresh_repositories_nonce', 'nonce');

    // Call the sync method from CronTasks
    \TheRepoPlugin\CronTasks\CronTasks::sync_repositories_to_db();

    // wp_send_json_success(['message' => __('Repositories refreshed successfully.', 'the-repo-plugin')]);
});
