<?php

namespace TheRepoPlugin\RenderForm;

class GitHubSearchUI {

    function render_form() {
        ?>
        <div id="github-plugin-search">
    <form id="github-search-form">
        <input type="text" id="github-search-query" placeholder="Search for WordPress plugins..." />
        <div id="search-button-container">
            <button type="submit" id="search-button">Search</button>
            <div id="searching-message" style="display: none;"><img class="searching-gif" src="<?php echo esc_url(plugins_url('assets/img/loading.gif', dirname(__DIR__, 3) . '/the-repo-plugin.php')); ?>" alt="Loading"></div>
        </div>
    </form>
    <div id="github-search-results" class="grid"></div>
    <div id="github-search-pagination"></div>
</div>

        <?php
    }
    
}
