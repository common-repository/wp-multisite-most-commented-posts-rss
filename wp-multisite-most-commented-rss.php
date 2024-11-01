<?php

/*
 * Plugin Name: WP Multisite Most Commented Posts RSS
 * Plugin URI: http://www.termel.fr
 * Description: Create customizable <em>most commented posts</em> RSS feed
 * Version: 1.2
 * Author: Termel
 * Author URI: http://www.termel.fr
 * License: GPL2
 */
if (! function_exists('wp_mmcr_log')) {

    function wp_mmcr_log($log)
    {
        if (is_array($log) || is_object($log)) {
            error_log(print_r($log, true));
        } else {
            error_log("wp_mmcr_log::" . $log);
        }
    }
}

class wp_multisite_most_commented_rss
{

    var $dbversion = '2015-06-29/11:00:01';
    // constructor
    function __construct()
    {
        $dbv = get_option('wp_mmcr_db_version', false);
        $this->wp_mmcr_register_plugin_styles();
        wp_mmcr_log("init feed");
        
        add_action('init', array(
            $this,
            'wp_mmcr_mostCommentedPostsRSS'
        ));
        
        if ($dbv != $this->dbversion) {
            add_action('init', array(
                $this,
                'wp_mmcr_flush_rules'
            ));
        }
    }

    function wp_mmcr_flush_rules()
    {
        global $wp_rewrite;
        if (is_object($wp_rewrite)) {
            $wp_rewrite->flush_rules();
            update_option('wp_mmcr_db_version', $this->dbversion);
        }
    }

    public function wp_mmcr_register_plugin_styles()
    {
        wp_register_style('wp_multisite_most_commented_rss', plugins_url('/css/wp-multisite-most-commented-rss.css', __FILE__));
        wp_enqueue_style('wp_multisite_most_commented_rss');
    }

    public function wp_mmcr_mostCommentedPostsRSS()
    {
        $feedname = 'mostcommented';
        add_feed($feedname, array(
            $this,
            'wp_mmcr_mostcommentedRSSFunc'
        ));
        wp_mmcr_log("Feed " . $feedname . " added");
    }

    public function wp_mmcr_mostcommentedRSSFunc()
    {
        wp_mmcr_log("feed callback");
        if (! function_exists('is_multisite') || ! is_multisite()) {
            return false;
        }
        $postCount = 3; // The number of posts to show in the feed
        $postTimeframe = "lastmonth";
        wp_mmcr_log("init with time frame " . $postTimeframe);
        $attrs = array(
            'max' => $postCount,
            'type' => $postTimeframe,
            'show_comments' => 'true',
            'show_posts' => 'false'
        );
        $postsArrays = $this->wp_mmcr_getMostCommentedPostsArrays($attrs);
        $postCommentsArray = $postsArrays['SLICE'];
        $postArray = $postsArrays['POSTS'];
        if ($postCommentsArray != null && is_array($postCommentsArray) && count($postCommentsArray) > 0) {
            foreach ($postCommentsArray as $postID => $nbComments) {
                $posts[] = $postArray[$postID];
            }
        } else {
            wp_mmcr_log("Empty array : no sorted posts by comments...");
            $posts = array();
        }
        
        $encoding = get_option('blog_charset');
        
        $header_string = 'Content-Type: ' . feed_content_type('rss2') . '; charset=' . $encoding;
        header($header_string, true);
        
        echo '<?xml version="1.0" encoding="' . $encoding . '" ?>';
        ?>
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
	<?php do_action('rss2_ns'); ?>> <channel>
<title><?php bloginfo_rss('name'); ?> - Most commented posts</title>
<atom:link href="<?php self_link(); ?>" rel="self"
	type="application/rss+xml" />
<link><?php bloginfo_rss('url') ?></link>
<description><?php bloginfo_rss('description') ?></description> <lastBuildDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false); ?></lastBuildDate>
<language>en-US</language> <sy:updatePeriod><?php echo apply_filters( 'rss_update_period', 'hourly' ); ?></sy:updatePeriod>
<sy:updateFrequency><?php echo apply_filters( 'rss_update_frequency', '1' ); ?></sy:updateFrequency>
                <?php do_action('rss2_head'); ?>
                <?php foreach($posts as $post){ ?>
                        <item>
<title><?php echo strip_tags($post->post_title); ?></title>
<link><?php echo $post->guid; ?></link>
<pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', $post->post_date, false); ?></pubDate>
<author><?php echo get_the_author_meta( 'email', $post->post_author ); ?>
</author>
<dc:creator><?php echo get_the_author_meta( 'display_name', $post->post_author ); ?></dc:creator>
<guid isPermaLink="false"><?php echo $post->guid; ?></guid> <description><![CDATA[<?php echo $post->post_excerpt; ?>]]></description>
<content:encoded>
	<![CDATA[<?php echo $post->post_excerpt; ?>]]>
</content:encoded>
                                <?php rss_enclosure(); ?>
                                <?php do_action('rss2_item'); ?>
                        </item>
                <?php } ?>
        </channel> </rss>

<?php
    }

    function wp_mmcr_getMostCommentedPostsArrays($attributes = array())
    {
        extract(shortcode_atts(array(
            'max' => '3',
            'type' => 'lastmonth',
            'show_comments' => 'true',
            'show_posts' => 'false'
        ), $attributes));
        
        wp_mmcr_log("build post array...");
        
        if ($type == 'lastmonth') {
            $startDate = date('Y-m-d', strtotime('today - 30 days'));
            $endDate = date('Y-m-d', strtotime('today'));
            wp_mmcr_log("lastmonth:: between " . $startDate . " and " . $endDate);
            $mostCommentedPosts = $this->wp_mmcr_getMostCommentedPosts($max, $startDate, $endDate, $show_comments, $show_posts);
        } else 
            if ($type == 'currentmonth') {
                $current_month = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
                $startDate = date('Y-m-01 H:i:s', $current_month);
                $endDate = date('Y-m-t H:i:s', $current_month);
                $mostCommentedPosts = $this->wp_mmcr_getMostCommentedPosts($max, $startDate, $endDate, $show_comments, $show_posts);
            } else 
                if ($type == 'lastweek') {
                    $startDate = date('Y-m-d', strtotime('today - 7 days'));
                    $endDate = date('Y-m-d', strtotime('today'));
                    $mostCommentedPosts = $this->wp_mmcr_getMostCommentedPosts($max, $startDate, $endDate, $show_comments, $show_posts);
                } else 
                    if ($type == 'currentweek') {
                        $weekNumber = date("W");
                        
                        $currentYear = date("Y");
                        $weekArray = $this->wp_mmcr_getStartAndEndDate($weekNumber, $currentYear);
                        $startDate = $weekArray['week_start'];
                        $endDate = $weekArray['week_end'];
                    } else 
                        if ($type == 'ever') {
                            $mostCommentedPosts = $this->wp_mmcr_getMostCommentedPosts($max, NULL, NULL, $show_comments, $show_posts);
                        } else {
                            $mostCommentedPosts = $this->wp_mmcr_getMostCommentedPosts($max);
                        }
        
        return $mostCommentedPosts;
    }

    function wp_mmcr_getStartAndEndDate($week, $year)
    {
        $dto = new DateTime();
        $dto->setISODate($year, $week);
        $ret['week_start'] = $dto->format('Y-m-d');
        $dto->modify('+6 days');
        $ret['week_end'] = $dto->format('Y-m-d');
        return $ret;
    }

    function wp_mmcr_postBetweenDates($post, $startDate, $endDate)
    {
        $format = 'Y-m-d';
        $postDate = get_the_time($format, $post->ID);
        $post_date = new DateTime($postDate);
        $start_date = new DateTime($startDate);
        $end_date = new DateTime($endDate);
        
        if ($post_date >= $start_date && $post_date <= $end_date) {
            return true;
        } else {
            return false;
        }
    }

    function wp_mmcr_getAllPostsOfAllBlogs($startDate = NULL, $endDate = NULL)
    {
        $network_sites = wp_get_sites();
        
        $result = array();
        foreach ($network_sites as $network_site) {
            
            $blog_id = $network_site['blog_id'];
            
            switch_to_blog($blog_id);
            
            $allPostsOfCurrentBlog = get_posts(array(
                'numberposts' => - 1,
                'post_type' => 'post',
                'post_status' => array(
                    'publish',
                    'future'
                )
            ));
            
            if ($startDate != NULL && $endDate != NULL) {
                
                foreach ($allPostsOfCurrentBlog as $post) {
                    if ($this->wp_mmcr_postBetweenDates($post, $startDate, $endDate)) {
                        $result[$blog_id][] = $post;
                    }
                }
            } else {
                $result[$blog_id] = $allPostsOfCurrentBlog;
            }
            
            restore_current_blog();
        }
        
        return $result;
    }

    public function wp_mmcr_get_comments_number_for_blog($blogid, $postid)
    {
        switch_to_blog($blogid);
        $result = get_comments_number($postid);
        restore_current_blog();
        return $result;
    }

    public function wp_mmcr_getMostCommentedPosts($maxResults, $startDate = NULL, $endDate = NULL, $show_comments = NULL, $show_posts = NULL)
    {
        $blog_posts_array = $this->wp_mmcr_getAllPostsOfAllBlogs($startDate, $endDate);
        $postCommentsArray = array();
        wp_mmcr_log('count($blog_posts_array) ' . count($blog_posts_array));
        foreach ($blog_posts_array as $blogid => $postsOfBlog) {
            
            foreach ($postsOfBlog as $post) {
                $nbOfComments = $this->wp_mmcr_get_comments_number_for_blog($blogid, $post->ID);
                $postCommentsArray[$blogid . '_' . $post->ID] = $nbOfComments;
                $postArray[$blogid . '_' . $post->ID] = $post;
            }
        }
        $sortResult = arsort($postCommentsArray);
        if ($sortResult && count($postCommentsArray) > 0) {
            wp_mmcr_log("some comments found...");
            $slice = array_slice($postCommentsArray, 0, $maxResults);
            return array(
                "SLICE" => $slice,
                "POSTS" => $postArray
            );
        } else {
            return array();
        }
    }
}

new wp_multisite_most_commented_rss();