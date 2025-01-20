<?php 

function find_plugin_file($installed_plugins, $repo_slug) {
    // error_log('[DEBUG] Searching for plugin file with slug: ' . $repo_slug);

    // Normalize slug for matching
    $normalized_slug = strtolower(trim($repo_slug));

    foreach ($installed_plugins as $file => $data) {
        $plugin_folder = strtolower(dirname($file));
        $plugin_basename = strtolower(basename($file, '.php'));
        $plugin_title = sanitize_title($data['Name']);

        // error_log("[DEBUG] Checking plugin: Folder: $plugin_folder, Basename: $plugin_basename, Title: $plugin_title");

        // Match against folder name, basename, or sanitized title
        if (
            $plugin_folder === $normalized_slug || 
            $plugin_basename === $normalized_slug || 
            $plugin_title === $normalized_slug
        ) {
            // error_log('[DEBUG] Match found: ' . $file);
            return $file;
        }

        // Check if the slug is contained within the folder name or title
        if (
            strpos($plugin_folder, $normalized_slug) !== false || 
            strpos($plugin_title, $normalized_slug) !== false
        ) {
            // error_log('[DEBUG] Partial match found: ' . $file);
            return $file;
        }
    }

    // Fallback logic for matching ZIP names (ensure `get_latest_release_zip_name` is defined)
    if (function_exists('get_latest_release_zip_name')) {
        $latest_release_zip_name = get_latest_release_zip_name("https://github.com/$repo_slug");
        if ($latest_release_zip_name) {
            $normalized_zip_name = strtolower($latest_release_zip_name);
            foreach ($installed_plugins as $file => $data) {
                $plugin_folder = strtolower(dirname($file));
                if (
                    $plugin_folder === $normalized_zip_name || 
                    strpos($plugin_folder, $normalized_zip_name) !== false
                ) {
                    // error_log('[DEBUG] Match found using ZIP name: ' . $file);
                    return $file;
                }
            }
        }
    } else {
        // error_log('[DEBUG] Skipping ZIP name matching because function get_latest_release_zip_name is not defined.');
    }

    // error_log('[DEBUG] No match found for slug: ' . $repo_slug);
    return false;
}
