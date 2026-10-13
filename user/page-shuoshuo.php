<?php
/**
 * Template Name: Shuoshuo Template
 * Template Post Type: page
 *
 * A paginated shuoshuo feed with comments loaded only when requested.
 */

$title_position = iro_opt('shuoshuo_title_position', 'below_avatar');
$title_position = in_array($title_position, array('below_avatar', 'beside_name'), true) ? $title_position : 'below_avatar';
$title_size = iro_opt('shuoshuo_title_size', 'standard');
$title_size = in_array($title_size, array('small', 'standard', 'large'), true) ? $title_size : 'standard';
$title_style = iro_opt('shuoshuo_title_style', 'bold');
$title_style = in_array($title_style, array('light', 'standard', 'bold'), true) ? $title_style : 'bold';

get_header();
?>
<div id="primary" class="content-area iro-shuoshuo-feed <?php echo esc_attr('iro-shuoshuo-title-position-' . $title_position . ' iro-shuoshuo-title-size-' . $title_size . ' iro-shuoshuo-title-style-' . $title_style); ?>" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
    <main id="main" class="site-main" role="main">
        <?php while (have_posts()) : the_post(); ?>
            <?php if (post_password_required()) : ?>
                <?php echo get_the_password_form(); ?>
            <?php else : ?>
                <?php
                $feed_page_id = get_the_ID();
                $feed_page = isset($_GET['shuoshuo_page']) ? max(1, absint($_GET['shuoshuo_page'])) : 1;
                $show_shuoshuo_title = iro_opt('shuoshuo_show_title', true);
                $feed = new WP_Query(array(
                    'post_type'           => 'shuoshuo',
                    'post_status'         => 'publish',
                    'has_password'        => false,
                    'posts_per_page'      => max(1, min(20, (int) get_option('posts_per_page', 10))),
                    'paged'               => $feed_page,
                    'orderby'             => array('date' => 'DESC', 'ID' => 'DESC'),
                    'ignore_sticky_posts' => true,
                ));
                ?>
                <header class="iro-shuoshuo-heading">
                    <span class="iro-shuoshuo-eyebrow"><i class="fa-regular fa-comment-dots" aria-hidden="true"></i> <?php esc_html_e('记录此刻', 'sakurairo'); ?></span>
                    <?php if (should_show_title()) : ?>
                        <h1 class="iro-shuoshuo-title"><?php the_title(); ?></h1>
                    <?php endif; ?>
                    <?php if (trim(get_the_content()) !== '') : ?>
                        <div class="iro-shuoshuo-intro"><?php the_content(); ?></div>
                    <?php endif; ?>
                    <span class="iro-shuoshuo-count"><?php printf(esc_html__('共 %s 条说说', 'sakurairo'), esc_html(number_format_i18n($feed->found_posts))); ?></span>
                </header>

                <p class="iro-shuoshuo-status" data-iro-feed-status role="status" aria-live="polite" hidden></p>
                <div class="iro-shuoshuo-stream entry-content">
                    <?php while ($feed->have_posts()) : $feed->the_post(); ?>
                        <?php
                        $shuoshuo_id = get_the_ID();
                        $shuoshuo_title = $show_shuoshuo_title ? trim(get_the_title()) : '';
                        $emotion_code = ltrim((string) get_post_meta($shuoshuo_id, 'emotion', true), '\\');
                        if (!preg_match('/^[0-9a-f]{4,6}$/i', $emotion_code) || hexdec($emotion_code) > 0x10ffff) {
                            $emotion_code = 'f86d';
                        }
                        $emotion_icon = html_entity_decode('&#x' . $emotion_code . ';', ENT_NOQUOTES, 'UTF-8');
                        $emotion_color = get_post_meta($shuoshuo_id, 'emotion_color', true) ?: iro_opt('theme_skin_matching', '');
                        $emotion_style = safecss_filter_attr('background-color: ' . $emotion_color);
                        ?>
                        <article class="iro-shuoshuo-card" id="shuoshuo-<?php echo esc_attr($shuoshuo_id); ?>">
                            <header class="iro-shuoshuo-meta">
                                <a class="iro-shuoshuo-avatar" href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>" aria-label="<?php echo esc_attr(get_the_author()); ?>">
                                    <?php echo get_avatar(get_the_author_meta('ID'), 48, '', get_the_author(), array('loading' => 'lazy')); ?>
                                    <span class="iro-shuoshuo-avatar-badge" style="<?php echo esc_attr($emotion_style); ?>" aria-hidden="true"><?php echo esc_html($emotion_icon); ?></span>
                                </a>
                                <div>
                                    <div class="iro-shuoshuo-author-line">
                                        <a class="iro-shuoshuo-author" href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>"><?php echo esc_html(get_the_author()); ?></a>
                                        <?php if ('beside_name' === $title_position && '' !== $shuoshuo_title) : ?>
                                            <h2 class="iro-shuoshuo-post-title" title="<?php echo esc_attr($shuoshuo_title); ?>"><?php echo esc_html($shuoshuo_title); ?></h2>
                                        <?php endif; ?>
                                    </div>
                                    <a class="iro-shuoshuo-time" href="<?php the_permalink(); ?>">
                                        <time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date() . ' · ' . get_the_time()); ?></time>
                                    </a>
                                </div>
                            </header>
                            <?php if ('below_avatar' === $title_position && '' !== $shuoshuo_title) : ?>
                                <h2 class="iro-shuoshuo-post-title"><?php echo esc_html($shuoshuo_title); ?></h2>
                            <?php endif; ?>
                            <div class="iro-shuoshuo-body"><?php the_content('', false); ?></div>
                            <footer class="iro-shuoshuo-actions">
                                <button type="button" class="iro-shuoshuo-toggle" data-iro-comments="<?php echo esc_attr($shuoshuo_id); ?>" aria-expanded="false" aria-controls="shuoshuo-comments-<?php echo esc_attr($shuoshuo_id); ?>">
                                    <i class="fa-regular fa-comment" aria-hidden="true"></i>
                                    <span><?php esc_html_e('评论', 'sakurairo'); ?></span>
                                    <span data-iro-count><?php echo esc_html(get_comments_number()); ?></span>
                                </button>
                                <a class="iro-shuoshuo-permalink" href="<?php the_permalink(); ?>" aria-label="<?php esc_attr_e('查看这条说说', 'sakurairo'); ?>"><i class="fa-solid fa-link" aria-hidden="true"></i></a>
                                <?php if (current_user_can('edit_post', $shuoshuo_id)) : ?>
                                    <a class="iro-shuoshuo-edit" href="<?php echo esc_url(get_edit_post_link($shuoshuo_id)); ?>" data-no-pjax><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i> <?php esc_html_e('编辑', 'sakurairo'); ?></a>
                                <?php endif; ?>
                            </footer>
                            <section class="iro-shuoshuo-panel" id="shuoshuo-comments-<?php echo esc_attr($shuoshuo_id); ?>" aria-label="<?php esc_attr_e('说说评论', 'sakurairo'); ?>" hidden></section>
                            <noscript><p><a href="<?php echo esc_url(get_permalink() . '#comments'); ?>"><?php esc_html_e('查看及发表评论', 'sakurairo'); ?></a></p></noscript>
                        </article>
                    <?php endwhile; ?>
                    <?php if (!$feed->post_count) : ?>
                        <div class="iro-shuoshuo-empty">
                            <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
                            <p><?php esc_html_e('这里还没有说说，留一点空白给下一次分享。', 'sakurairo'); ?></p>
                            <?php if ($feed_page > 1) : ?>
                                <a href="<?php echo esc_url(get_permalink($feed_page_id)); ?>"><?php esc_html_e('回到最新说说', 'sakurairo'); ?></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php wp_reset_postdata(); ?>
                <?php if ($feed->max_num_pages > 1) : ?>
                    <nav class="iro-shuoshuo-pagination" aria-label="<?php esc_attr_e('说说分页', 'sakurairo'); ?>">
                        <?php echo paginate_links(array(
                            'base'      => str_replace('999999999', '%#%', esc_url(add_query_arg('shuoshuo_page', 999999999, get_permalink($feed_page_id)))),
                            'format'    => '',
                            'current'   => $feed_page,
                            'total'     => $feed->max_num_pages,
                            'mid_size'  => 1,
                            'prev_text' => esc_html__('更新的说说', 'sakurairo'),
                            'next_text' => esc_html__('更早的说说', 'sakurairo'),
                        )); ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        <?php endwhile; ?>
        <script type="application/json" class="iro-shuoshuo-config"><?php echo wp_json_encode(array(
            'loading'      => __('正在加载评论…', 'sakurairo'),
            'failed'       => __('评论加载失败，请重试。', 'sakurairo'),
            'retry'        => __('重试', 'sakurairo'),
            'submitting'   => __('正在提交…', 'sakurairo'),
            'submitFailed' => __('提交失败，请稍后重试。', 'sakurairo'),
            'uncertain'    => __('暂未收到提交结果，请先刷新评论确认是否成功，再决定是否重新提交。', 'sakurairo'),
            'captchaError' => __('验证码加载失败，请刷新验证码或重新展开评论区。', 'sakurairo'),
            'replyTo'      => __('正在回复：', 'sakurairo'),
            'navigating'   => __('正在加载说说…', 'sakurairo'),
            'navigateError' => __('加载失败，请重试分页链接。', 'sakurairo'),
        ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    </main>
</div>
<?php get_footer(); ?>
