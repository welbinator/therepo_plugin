<?php

function filter_repositories_by_release_date($repositories, $github_headers) {
    $results = [];
    $cached_releases = [];
    $six_months_ago = strtotime('-6 months');

    foreach ($repositories as $repo) {
        if (isset($repo['topics']) && in_array('wordpress-plugin', $repo['topics'])) {
            $repo_url = $repo['html_url'];

            // Check if the repository has releases, using caching
            $cache_key = 'repo_has_releases_' . md5($repo_url);
            if (isset($cached_releases[$repo_url])) {
                $has_releases = $cached_releases[$repo_url];
            } else {
                $cached = get_transient($cache_key);
                if ($cached !== false) {
                    $has_releases = $cached;
                } else {
                    $has_releases = has_releases($repo_url, $github_headers);
                    set_transient($cache_key, $has_releases, DAY_IN_SECONDS);
                }
                $cached_releases[$repo_url] = $has_releases;
            }

            // Proceed only if the repository has releases
            if ($has_releases) {
                // Fetch the latest release date
                $latest_release_date = get_latest_release_date($repo_url, $github_headers);

                // Only include repositories with releases within the last six months
                if ($latest_release_date !== false && $latest_release_date >= $six_months_ago) {
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

/**
 * Fetches the latest release date for a repository.
 * Returns a Unix timestamp of the release date or false if no releases are found.
 */
function get_latest_release_date($repo_url, $github_headers) {
    error_log("Fetching latest release date for: $repo_url");
    $api_url = str_replace('https://github.com/', 'https://api.github.com/repos/', rtrim($repo_url, '/')) . '/releases';

    $response = wp_remote_get($api_url, ['headers' => $github_headers]);

    if (is_wp_error($response)) {
        error_log('GitHub API error for releases: ' . $response->get_error_message());
        return false;
    }

    $releases = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($releases) && is_array($releases)) {
        // Return the release date of the latest release
        return strtotime($releases[0]['created_at']);
    }

    return false; // No releases found
}
