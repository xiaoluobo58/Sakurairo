<?php
/**
 * Assets and native WordPress comments for the Shuoshuo page template.
 */

if (!defined('ABSPATH')) {
    exit;
}

function iro_shuoshuo_feed_assets() {
    if (!iro_opt('poi_pjax', true) && !is_page_template('user/page-shuoshuo.php')) {
        return;
    }

    $directory = get_template_directory();
    $uri = get_template_directory_uri();
    // Compressed theme CSS is printed directly and does not register iro-css.
    wp_enqueue_style('iro-shuoshuo-feed', $uri . '/css/shuoshuo-feed.css', array(), filemtime($directory . '/css/shuoshuo-feed.css'));
    wp_enqueue_script('iro-shuoshuo-feed', $uri . '/js/shuoshuo-feed.js', array('app'), filemtime($directory . '/js/shuoshuo-feed.js'), true);
}
add_action('wp_enqueue_scripts', 'iro_shuoshuo_feed_assets', 20);

/** A comment request must never expose drafts, private posts or protected content. */
function iro_shuoshuo_comment_post($post_id) {
    if (!$post_id) {
        return new WP_Error('invalid_shuoshuo', __('这条说说暂时无法查看。', 'sakurairo'));
    }
    $post = get_post($post_id);
    $type = get_post_type_object('shuoshuo');
    if (!$post || 'shuoshuo' !== $post->post_type || 'publish' !== $post->post_status || !$type || !$type->public || '' !== $post->post_password) {
        return new WP_Error('invalid_shuoshuo', __('这条说说暂时无法查看。', 'sakurairo'));
    }
    return $post;
}

function iro_shuoshuo_comment_count($post_id) {
    return (int) get_comments(array('post_id' => $post_id, 'status' => 'approve', 'type' => 'comment', 'count' => true));
}

/** Limit reply depth using the same discussion settings as the native form. */
function iro_shuoshuo_can_reply($comment) {
    if (!get_option('thread_comments') || !comments_open($comment->comment_post_ID) || (get_option('comment_registration') && !is_user_logged_in())) {
        return false;
    }
    $max_depth = max(1, (int) get_option('thread_comments_depth', 5));
    $depth = 1;
    $parent_id = (int) $comment->comment_parent;
    while ($parent_id && $depth < $max_depth) {
        $parent = get_comment($parent_id);
        if (!$parent || (int) $parent->comment_post_ID !== (int) $comment->comment_post_ID) {
            break;
        }
        $depth++;
        $parent_id = (int) $parent->comment_parent;
    }
    return $depth < $max_depth;
}

function iro_shuoshuo_render_comment($comment) {
    $GLOBALS['comment'] = $comment;
    $parent = $comment->comment_parent ? get_comment($comment->comment_parent) : null;
    $parent_visible = $parent && '1' === (string) $parent->comment_approved && (int) $parent->comment_post_ID === (int) $comment->comment_post_ID;
    ?>
    <li id="iro-shuoshuo-comment-<?php echo (int) $comment->comment_ID; ?>" class="iro-shuoshuo-comment<?php echo $comment->comment_parent ? ' iro-shuoshuo-comment--reply' : ''; ?>">
        <div class="iro-shuoshuo-comment-heading">
            <?php echo get_avatar($comment, 36, '', get_comment_author($comment), array('class' => 'iro-shuoshuo-comment-avatar')); ?>
            <span class="iro-shuoshuo-comment-author"><?php echo esc_html(get_comment_author($comment)); ?></span>
            <time class="iro-shuoshuo-comment-time" datetime="<?php echo esc_attr(get_comment_date('c', $comment)); ?>"><?php echo esc_html(get_comment_date(get_option('date_format') . ' ' . get_option('time_format'), $comment)); ?></time>
        </div>
        <?php if ($comment->comment_parent) : ?>
            <p class="iro-shuoshuo-comment-parent"><?php
                echo esc_html($parent_visible
                    ? sprintf(__('回复 %s', 'sakurairo'), get_comment_author($parent))
                    : __('回复先前的评论', 'sakurairo'));
            ?></p>
        <?php endif; ?>
        <div class="iro-shuoshuo-comment-body"><?php comment_text($comment); ?></div>
        <?php if (iro_shuoshuo_can_reply($comment)) : ?>
            <button type="button" class="iro-shuoshuo-comment-reply" data-iro-reply="<?php echo (int) $comment->comment_ID; ?>" data-iro-reply-author="<?php echo esc_attr(get_comment_author($comment)); ?>"><?php esc_html_e('回复', 'sakurairo'); ?></button>
        <?php endif; ?>
    </li>
    <?php
}

/** Keep WordPress/plugin fields and hooks; add the theme's existing comment options. */
function iro_shuoshuo_comment_form($post_id) {
    $extra = '<input type="hidden" name="post_id" value="' . (int) $post_id . '">';
    $extra .= wp_nonce_field('sakurairo_ajax_comment', 'sakurairo_comment_nonce', false, false);
    $extra .= '<div class="iro-shuoshuo-comment-options">';
    $extra .= '<label><input type="checkbox" name="enable_markdown" value="1"> ' . esc_html__('Markdown', 'sakurairo') . '</label>';
    if (iro_opt('comment_private_message')) {
        $extra .= '<label><input type="checkbox" name="is-private" value="1"> ' . esc_html__('私密评论', 'sakurairo') . '</label>';
    }
    if (iro_opt('mail_notify')) {
        $extra .= '<label><input type="checkbox" name="mail-notify" value="1"> ' . esc_html__('接收回复邮件通知', 'sakurairo') . '</label>';
    }
    $extra .= '</div>';

    if (!is_user_logged_in()) {
        $captcha = iro_opt('comment_captcha_select', 'off');
        if ('iro_captcha' === $captcha) {
            $extra .= '<div class="iro-shuoshuo-captcha" data-iro-captcha="' . esc_url(rest_url('sakura/v1/captcha/create')) . '">';
            $extra .= '<label for="iro-shuoshuo-captcha-answer">' . esc_html__('验证码', 'sakurairo') . '</label>';
            $extra .= '<div class="iro-shuoshuo-captcha-fields"><button type="button" data-iro-captcha-refresh aria-label="' . esc_attr__('刷新验证码', 'sakurairo') . '"><img data-iro-captcha-image width="120" height="40" alt="' . esc_attr__('验证码，点击刷新', 'sakurairo') . '"></button>';
            $extra .= '<input type="text" id="iro-shuoshuo-captcha-answer" name="captcha" required autocomplete="off" placeholder="' . esc_attr__('输入验证码', 'sakurairo') . '"></div>';
            $extra .= '<input type="hidden" name="timestamp"><input type="hidden" name="id"></div>';
        } elseif ('turnstile' === $captcha) {
            $extra .= '<div data-iro-turnstile data-sitekey="' . esc_attr(iro_opt('turnstile_site_key', '')) . '"></div>';
            $extra .= '<input type="hidden" name="cf-turnstile-response" value="">';
        }
    }

    $placeholder = iro_opt('comment_placeholder_text') ?: __('分享你的想法……', 'sakurairo');
    $submit = iro_opt('comment_submit_button_text') ?: __('发表评论', 'sakurairo');
    ?>
    <div class="iro-shuoshuo-reply-status" data-iro-reply-status hidden>
        <span data-iro-reply-name></span>
        <button type="button" data-iro-cancel-reply><?php esc_html_e('取消回复', 'sakurairo'); ?></button>
    </div>
    <?php
    comment_form(array(
        'id_form' => 'iro-shuoshuo-commentform',
        'id_submit' => 'iro-shuoshuo-submit',
        'class_form' => 'comment-form iro-shuoshuo-form',
        'class_submit' => 'submit iro-shuoshuo-submit',
        'title_reply' => __('发表评论', 'sakurairo'),
        'title_reply_before' => '<h3 class="iro-shuoshuo-form-title">',
        'title_reply_after' => '</h3>',
        'label_submit' => $submit,
        'comment_field' => '<p class="comment-form-comment"><label class="screen-reader-text" for="iro-shuoshuo-comment">' . esc_html__('评论', 'sakurairo') . '</label><textarea id="iro-shuoshuo-comment" name="comment" rows="4" maxlength="65525" required placeholder="' . esc_attr($placeholder) . '"></textarea></p>',
        'comment_notes_after' => $extra,
    ), $post_id);
    echo '<p class="iro-shuoshuo-form-status" data-iro-form-status role="status" aria-live="polite" hidden></p>';
}

/** Only one bounded page is read; replies are attributed without loading whole trees. */
function iro_shuoshuo_comments_ajax() {
    $post_id = isset($_REQUEST['post_id']) && is_scalar($_REQUEST['post_id']) ? absint($_REQUEST['post_id']) : 0;
    $post = iro_shuoshuo_comment_post($post_id);
    if (is_wp_error($post)) {
        wp_send_json_error(array('message' => $post->get_error_message()), 404);
    }

    $count = iro_shuoshuo_comment_count($post_id);
    $per_page = 10;
    $pages = max(1, (int) ceil($count / $per_page));
    $requested_page = isset($_REQUEST['comment_page']) && is_scalar($_REQUEST['comment_page']) ? absint($_REQUEST['comment_page']) : 1;
    $page = min($pages, max(1, $requested_page));
    $comments = get_comments(array(
        'post_id' => $post_id,
        'status' => 'approve',
        'type' => 'comment',
        'number' => $per_page,
        'offset' => ($page - 1) * $per_page,
        'orderby' => array('comment_date_gmt' => 'DESC', 'comment_ID' => 'DESC'),
        'hierarchical' => false,
    ));
    $GLOBALS['post'] = $post;
    setup_postdata($post);

    ob_start();
    echo '<div class="iro-shuoshuo-comment-thread">';
    if ($comments) {
        echo '<ol class="iro-shuoshuo-comments-list">';
        foreach ($comments as $comment) {
            iro_shuoshuo_render_comment($comment);
        }
        echo '</ol>';
    } else {
        echo '<p class="iro-shuoshuo-comments-empty">' . esc_html__('这里还没有评论。', 'sakurairo') . '</p>';
    }
    if ($pages > 1) {
        echo '<nav class="iro-shuoshuo-comment-pagination" aria-label="' . esc_attr__('评论分页', 'sakurairo') . '">';
        if ($page > 1) {
            echo '<button type="button" data-iro-comment-page="' . ($page - 1) . '">' . esc_html__('较新的评论', 'sakurairo') . '</button>';
        }
        echo '<span>' . esc_html(sprintf(__('第 %1$d / %2$d 页', 'sakurairo'), $page, $pages)) . '</span>';
        if ($page < $pages) {
            echo '<button type="button" data-iro-comment-page="' . ($page + 1) . '">' . esc_html__('更早的评论', 'sakurairo') . '</button>';
        }
        echo '</nav>';
    }
    echo '</div>';
    if (comments_open($post_id)) {
        iro_shuoshuo_comment_form($post_id);
    } else {
        echo '<p class="iro-shuoshuo-comments-closed">' . esc_html__('这条说说已关闭评论。', 'sakurairo') . '</p>';
    }
    $html = ob_get_clean();
    wp_reset_postdata();
    wp_send_json_success(array('html' => $html, 'page' => $page, 'pages' => $pages, 'count' => $count));
}
add_action('wp_ajax_iro_shuoshuo_comments', 'iro_shuoshuo_comments_ajax');
add_action('wp_ajax_nopriv_iro_shuoshuo_comments', 'iro_shuoshuo_comments_ajax');

function iro_shuoshuo_comment_ajax() {
    $nonce = isset($_POST['sakurairo_comment_nonce']) && is_string($_POST['sakurairo_comment_nonce']) ? sanitize_text_field(wp_unslash($_POST['sakurairo_comment_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sakurairo_ajax_comment')) {
        wp_send_json_error(array('message' => __('评论凭证已过期，请重新展开评论区后再试。', 'sakurairo')), 403);
    }
    $post_id = isset($_POST['post_id']) && is_scalar($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $post = iro_shuoshuo_comment_post($post_id);
    if (is_wp_error($post)) {
        wp_send_json_error(array('message' => $post->get_error_message()), 404);
    }
    $parent_id = isset($_POST['comment_parent']) && is_scalar($_POST['comment_parent']) ? absint($_POST['comment_parent']) : 0;
    if ($parent_id) {
        $parent = get_comment($parent_id);
        if (!$parent || (int) $parent->comment_post_ID !== $post_id || '1' !== (string) $parent->comment_approved || !iro_shuoshuo_can_reply($parent)) {
            wp_send_json_error(array('message' => __('这条评论暂时无法回复，请刷新评论区后重试。', 'sakurairo')), 400);
        }
    }

    // Keep the original POST fields for theme/plugin hooks; pin the target to the validated post.
    $_POST['comment_post_ID'] = $post_id;
    $_POST['comment_parent'] = $parent_id;
    $comment = wp_handle_comment_submission(wp_unslash($_POST));
    if (is_wp_error($comment)) {
        wp_send_json_error(array('message' => $comment->get_error_message()), 400);
    }
    $consent = isset($_POST['wp-comment-cookies-consent']);
    do_action('set_comment_cookies', $comment, wp_get_current_user(), $consent);
    $pending = '1' !== (string) $comment->comment_approved;
    wp_send_json_success(array(
        'message' => $pending ? __('评论已提交，审核通过后会显示。', 'sakurairo') : __('评论发表成功。', 'sakurairo'),
        'pending' => $pending,
        'comment_id' => (int) $comment->comment_ID,
        'count' => iro_shuoshuo_comment_count($post_id),
    ));
}
add_action('wp_ajax_iro_shuoshuo_comment', 'iro_shuoshuo_comment_ajax');
add_action('wp_ajax_nopriv_iro_shuoshuo_comment', 'iro_shuoshuo_comment_ajax');
