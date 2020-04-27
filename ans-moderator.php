<?php

/**
 * Plugin Name: ANS Moderator
 * Description: This plugin allows users to have refferal links and to count their post symbols and earnings. Adds path /moderator/name/. Requires options-permalink.php visit
 */
class ANS_Moderators {

    // Moderator nicenames and price for each action: ['per_1000', 'registered', 'profile', 'forum']
    public $moderators = [
        'anton' => ['per_1000' => 60, 'registered' => 20, 'profile' => 10, 'forum' => 30],
    ];
    // How long the cookie exists after the visit of the user by a referral link
    public $cookie_max_age = WEEK_IN_SECONDS;
    // How many days since registration should pass that we can show the user as a referral (PHP format)
    public $time_to_count_user = '7 days';
    // How many posts should user submit to the forum to get payment for him
    public $posts_to_count_forum = 5;
    public $paid_forums = ['Соционика', 'Психософия', 'Типирование'];

    public function __construct() {
        $this->add_moderator_page();

        add_action('wp_footer', [$this, 'print_scripts']);
        add_action('user_register', [$this, 'log_referral']);
    }

    public function add_moderator_page() {
        add_filter('generate_rewrite_rules', function ($wp_rewrite) {
            $wp_rewrite->rules = ['moderator/([^/]+)/?$' => 'index.php?ans_moderator=$matches[1]'] + $wp_rewrite->rules;
        });

        add_filter('query_vars', function($query_vars) {
            $query_vars[] = 'ans_moderator';
            return $query_vars;
        });

        add_action('template_redirect', function() {
            $moderator = get_query_var('ans_moderator');

            if (in_array($moderator, array_keys($this->moderators))) {
                include plugin_dir_path(__FILE__) . 'templates/moderator.php';
                exit;
            }
        });
    }

    public function print_scripts() {
        ?>    
        <script>
            jQuery(document).ready(function ($) {
                var moderators = <?= json_encode(array_keys($this->moderators)) ?>;
                var moderator = window.location.hash.substring(1); // # removed

                if (moderators.includes(moderator) && !$('body').hasClass('logged-in')) {
                    var cookie = {referred_by: moderator, referrer_url: encodeURIComponent(document.referrer)};

                    document.cookie = 'ans_moderator=' + JSON.stringify(cookie) + '; max-age=<?= $this->cookie_max_age ?>; path=/';

                    if ($('body').hasClass('registration')) {
                        // Remove hash from URL on the registration page because after form submitted it is preserved
                        history.replaceState(null, null, ' ');
                    }
                }
            });
        </script>
        <?php
    }

    public function log_referral($user_id) {
        $cookie = json_decode(stripslashes($_COOKIE['ans_moderator'] ?? ''));
        $referred_by = $cookie->referred_by ?? '';
        $referrer_url = filter_var($cookie->referrer_url ?? '', FILTER_VALIDATE_URL);

        if ($cookie && in_array($referred_by, array_keys($this->moderators))) {
            update_user_meta($user_id, 'ans_ip', $_SERVER['REMOTE_ADDR']);
            update_user_meta($user_id, 'ans_referred_by', $referred_by);

            if ($referrer_url) {
                // Referrer may be empty. Especially if https -> http (referrer not passed)
                update_user_meta($user_id, 'ans_referrer_url', $referrer_url);
            }

            setcookie('ans_moderator', null, -1, '/'); // Clear cookie
        }
    }

    public function get_referrals($moderator) {
        $referrals = get_users(['meta_key' => 'ans_referred_by', 'meta_value' => $moderator]);

        $referrals = array_filter($referrals, function(WP_User $referral) {
            return $referral->user_status === '0'; // Is user activated
        });

        return $referrals;
    }

    public function filter_users_by_date($start, $end, $users) {
        return array_filter($users, function(WP_User $user) use ($start, $end) {
            $user_registered = new DateTime("$user->user_registered + $this->time_to_count_user");

            return $user_registered >= new DateTime($start) && $user_registered <= new DateTime($end);
        });
    }

    public function get_user_posts($user_nicename) {
        $posts = [];

        $query = new WP_Query([
            'post_type' => ['topic', 'reply'],
            'posts_per_page' => -1, // return all
            'author_name' => $user_nicename,
        ]);

        // Filter using paid forums
        $posts = array_filter($query->posts, function(WP_Post $post) {
            $ancestors = get_post_ancestors($post->ID);
            // Is the root ancestor in our list
            return in_array(end($ancestors), $this->get_paid_forum_ids());
        });

        return $posts;
    }

    public function get_paid_forum_ids() {
        // Cache the result
        if (!isset($this->paid_forum_ids)) {
            foreach ($this->paid_forums as $forum_name) {
                $forum = get_page_by_title($forum_name, OBJECT, 'forum');
                $this->paid_forum_ids[] = $forum->ID;
            }
        }

        return $this->paid_forum_ids;
    }

    public function filter_posts_by_date($start, $end, $posts) {
        return array_filter($posts, function(WP_Post $post) use ($start, $end) {
            $post_date = new DateTime($post->post_date_gmt);

            return $post_date >= new DateTime($start) && $post_date <= new DateTime($end);
        });
    }

    public function count_posts_text_size($posts) {
        return array_reduce($posts, function($total, WP_Post $post) {
            $content = $post->post_content;
            // Remove quotes and other tags
            $content = preg_replace('/<blockquote>.*<\/blockquote>/Us', '', $content);
            $content = strip_tags($content);
            // Remove links
            $links = wp_extract_urls($content);
            $content = str_replace($links, '', $content);
            // Remove multiple whitespaces
            $content = preg_replace('/\s+/', ' ', $content);

            return $total += mb_strlen($content);
        }, 0);
    }

    public function has_user_completed_profile($user_id) {
        $default_avatar = bp_core_avatar_default('local');
        $user_avatar = bp_core_fetch_avatar(['item_id' => $user_id, 'no_grav' => true, 'type' => 'full', 'html' => false]);

        $fields = ['Пол', 'Дата рождения', 'Город', 'Социотип'];
        $fields_data = [];

        foreach ($fields as $field) {
            $fields_data[] = xprofile_get_field_data($field, $user_id);
        }

        $fields_data = array_filter($fields_data); // Clean empty elements

        return $default_avatar != $user_avatar && count($fields) == count($fields_data);
    }

    public function avatar_date_is_fine(WP_User $user) {
        $avatar = bp_core_fetch_avatar(['item_id' => $user->ID, 'html' => false]);
        $avatar_path = str_replace(wp_get_upload_dir()['baseurl'], wp_get_upload_dir()['basedir'], $avatar);
        $avatar_created = new DateTime('@' . filemtime($avatar_path));

        return $avatar_created <= new DateTime("$user->user_registered + $this->time_to_count_user");
    }

}

new ANS_Moderators();
