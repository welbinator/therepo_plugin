<?php

function filter_repositories($repositories) {
    error_log("repos filtered!");
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
                    $has_releases = has_releases($repo_url);
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