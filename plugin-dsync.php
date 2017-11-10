<?php
/*
Plugin Name: WP Data Sync
Description: Allows synchronizing the contents of tags on pages.
Author: Adolph C.
Version: 1.0.0
*/

$epdsync_logging_enabled = false;

/// Mappings for pages that should have their data synchronized.
/// a => b
/// When page with slug a is updated, the corresponding data in the page with slug b is also updated.
/// The hook stops when the two pages have matching data.
/// Only data in tags with the attribute data-ep-dsync are updated.
$epdsync_page_content_sync = array(
    // 'my/cool/page' => 'my/even-cooler/page'
    // 'my/even-cooler/page' => 'my/cool/page'
    // ^ bindings are not 2-way by default so they have to be specified twice.
);

/// Helper function for creating the bindings.
function epdsync_bind($src_slugs, $dest_slugs, $flip = true) {
    if (!is_array($src_slugs)) { $src_slugs = array($src_slugs); }
    if (!is_array($dest_slugs)) { $dest_slugs = array($dest_slugs); }

    foreach ($src_slugs as $src_slug) {
        $epdsync_page_content_sync = $dest_slugs;
    }

    if ($flip) {
        epdsync_sync($dest_slugs, $src_slugs, false);
    }
}


$epdsync_log_header_count = 0;
/// In my infinite wisdom I decided that instead of actually
/// trying to learn how to properly log things in wordpress plugins
/// I could just save time and stuff my log messages into headers
/// instead and read them from the requests being logged in Chrome dev tools.
/// One day I will change this (probably not but it feels good to write it. :) )
function epdsync_log($message) {
    global $epdsync_log_header_count;
    global $epdsync_logging_enabled;

    if ($epdsync_logging_enabled) {
        $epdsync_log_header_count += 1;
        header("X-EP-Log-Message-$epdsync_log_header_count: ${message}");
    }
}

// Action called whenever a post or page is updated or created.
add_action('save_post', 'epdsync_on_post_updated');

/// Action called whenever content on a page is updated.
/// This will synchronize the content on another page if an entry exists
/// in epdsync_page_content_sync.
function epdsync_on_post_updated($post_id) {
    global $epdsync_page_content_sync;
    $src_post = get_post($post_id);
    $src_page_slug = get_page_uri($post_id);
    if (is_object($src_post)) {
        epdsync_log('found src post');
        $src_post_name = $src_post->post_name;
        $src_post_title = $src_post->post_title;
        $src_post_content = $src_post->post_content;

        $dest_slugs = $epdsync_page_content_sync[$src_page_slug];
        if (!is_array($dest_slugs)) { $dest_slugs = array($dest_slugs); }

        foreach ($dest_slugs as $dest_slug) {
           $dest_post = get_page_by_path($dest_slug);
           if (is_object($dest_post)) {
               $dest_post_name = $dest_post->post_name;
               $dest_post_title = $dest_post->post_title;
               $dest_post_content = $dest_post->post_content;
           }

           epdsync_log("using post ${src_post_name} to update ${dest_post_name}.");

           $new_dest_content = epdsync_synchronize_content($src_post_content, $dest_post_content);
           if (is_string($new_dest_content)) {
               epdsync_log('synchronized posts.');
               $dest_post->post_content = $new_dest_content;
               wp_update_post($dest_post);
           }
        }
    }
}

/// Synchronize two pieces of page content.
function epdsync_synchronize_content($src_content, $dest_content) {
    $src_dom = new DOMDocument();
    $src_dom->loadHTML($src_content);

    $dest_dom = new DOMDocument();
    $dest_dom->loadHTML($dest_content);

    $src_xpath = new DOMXpath($src_dom);
    $dest_xpath = new DOMXpath($dest_dom);

    $src_elements = $src_xpath->query('//*[@data-ep-dsync]');
    $dest_elements = $dest_xpath->query('//*[@data-ep-dsync]');

    if (!is_null($src_elements) && !is_null($dest_elements)) {
        $updated_element = false;

        foreach ($src_elements as $src_elem) {
            $updated_element |= epdsync_match_element_with_list($src_elem, $dest_elements);
        }

        if ($updated_element) {
            return $dest_dom->saveHTML();
        } else {
            return null;
        }
    } else {
        epdsync_log('both src and dest elements were null.');
    }
    return null;
}

/// Finds and updates the element in dest_list with a data-ep-dsync attributes matching src_elem.
function epdsync_match_element_with_list($src_elem, $dest_list) {
    foreach ($dest_list as $dest_elem) {
        if ($dest_elem->getAttribute('data-ep-dsync') === $src_elem->getAttribute('data-ep-dsync')) {
            if ($dest_elem->textContent !== $src_elem->textContent) {
                epdsync_log("changed content: $dest_elem->textContent = $src_elem->textContent");
                $dest_elem->nodeValue = $src_elem->nodeValue;
                return true;
            }
        }
    }
    return false;
}
