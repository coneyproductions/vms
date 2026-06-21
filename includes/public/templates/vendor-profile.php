<?php
defined('ABSPATH') || exit;

get_header();

$vendor = isset($GLOBALS['vms_vendor_profile_post']) ? $GLOBALS['vms_vendor_profile_post'] : null;
if (!($vendor instanceof WP_Post)) {
    echo '<main class="vms-vendor-profile"><div class="vms-vp-card"><p>' . esc_html__('Vendor not found.', 'vms') . '</p></div></main>';
    wp_reset_postdata();
    get_footer();
    return;
}

$vendor_id = (int) $vendor->ID;

$k_show_e   = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'public_profile_show_email') : '_vms_vendor_public_profile_show_email';
$k_show_p   = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'public_profile_show_phone') : '_vms_vendor_public_profile_show_phone';
$k_show_w   = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'public_profile_show_website') : '_vms_vendor_public_profile_show_website';
$k_show_loc = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'public_profile_show_location') : '_vms_vendor_public_profile_show_location';

$raw_show_e   = (string) get_post_meta($vendor_id, $k_show_e, true);
$raw_show_p   = (string) get_post_meta($vendor_id, $k_show_p, true);
$raw_show_w   = (string) get_post_meta($vendor_id, $k_show_w, true);
$raw_show_loc = (string) get_post_meta($vendor_id, $k_show_loc, true);

$show_email   = ($raw_show_e === '') ? true : ($raw_show_e === '1');
$show_phone   = ($raw_show_p === '') ? true : ($raw_show_p === '1');
$show_website = ($raw_show_w === '') ? true : ($raw_show_w === '1');
$show_loc     = ($raw_show_loc === '') ? true : ($raw_show_loc === '1');

$k_primary_email = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'primary_email') : '_vms_vendor_primary_email';
$k_primary_phone = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'primary_phone') : '_vms_vendor_primary_phone';
$k_vendor_web    = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'website') : '_vms_vendor_website';

$email   = (string) get_post_meta($vendor_id, $k_primary_email, true);
$phone   = (string) get_post_meta($vendor_id, $k_primary_phone, true);
$website = (string) get_post_meta($vendor_id, $k_vendor_web, true);

if ($email === '') {
    $legacy_email_key = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'contact_email') : '_vms_contact_email';
    $email = (string) get_post_meta($vendor_id, $legacy_email_key, true);
}
if ($phone === '') {
    $legacy_phone_key = function_exists('vms_meta_key') ? vms_meta_key('vendor', 'contact_phone') : '_vms_contact_phone';
    $phone = (string) get_post_meta($vendor_id, $legacy_phone_key, true);
}
if ($website === '') {
    $website = (string) get_post_meta($vendor_id, '_vms_website_url', true);
}

$city  = (string) get_post_meta($vendor_id, function_exists('vms_meta_key') ? vms_meta_key('vendor', 'city') : '_vms_city', true);
$state = (string) get_post_meta($vendor_id, function_exists('vms_meta_key') ? vms_meta_key('vendor', 'state') : '_vms_state', true);
if (($city === '' || $state === '')) {
    $legacy_loc = (string) get_post_meta($vendor_id, '_vms_vendor_location', true);
    if ($legacy_loc !== '') {
        $parts = array_map('trim', explode(',', $legacy_loc, 2));
        if ($city === '' && isset($parts[0]) && $parts[0] !== '') {
            $city = $parts[0];
        }
        if ($state === '' && isset($parts[1]) && $parts[1] !== '') {
            $state = $parts[1];
        }
    }
}

$next_show_markup = function_exists('vms_vendor_profiles_render_next_show_card')
    ? (string) vms_vendor_profiles_render_next_show_card($vendor_id)
    : '';

$social_markup = function_exists('vms_vendor_profiles_render_social_links')
    ? (string) vms_vendor_profiles_render_social_links($vendor_id)
    : '';

$video_url = trim((string) get_post_meta($vendor_id, '_vms_vendor_featured_video_url', true));
$video_embed = '';
if ($video_url !== '') {
    $video_embed = wp_oembed_get($video_url, array('width' => 960));
    if ($video_embed === false || $video_embed === '') {
        $video_embed = '<p><a href="' . esc_url($video_url) . '" target="_blank" rel="noopener">' . esc_html__('Watch featured video', 'vms') . '</a></p>';
    }
}

$gallery_images = array();
for ($i = 1; $i <= 5; $i++) {
    $url = trim((string) get_post_meta($vendor_id, '_vms_vendor_gallery_image_' . $i, true));
    if ($url !== '') {
        $gallery_images[] = esc_url($url);
    }
}

$profile_markup_allowed_html = function_exists('vms_vendor_profiles_promo_allowed_html')
    ? vms_vendor_profiles_promo_allowed_html()
    : wp_kses_allowed_html('post');
$profile_markup_allowed_html['section'] = array(
    'aria-label' => true,
    'class' => true,
);
$profile_markup_allowed_html['div'] = array(
    'aria-label' => true,
    'class' => true,
);
$profile_markup_allowed_html['p'] = array(
    'class' => true,
);
$profile_markup_allowed_html['h2'] = array(
    'class' => true,
);
$profile_markup_allowed_html['a'] = array_merge(
    isset($profile_markup_allowed_html['a']) && is_array($profile_markup_allowed_html['a'])
        ? $profile_markup_allowed_html['a']
        : array(),
    array(
        'aria-label' => true,
        'class' => true,
        'href' => true,
        'rel' => true,
        'target' => true,
    )
);
$profile_markup_allowed_html['span'] = array_merge(
    isset($profile_markup_allowed_html['span']) && is_array($profile_markup_allowed_html['span'])
        ? $profile_markup_allowed_html['span']
        : array(),
    array(
        'aria-hidden' => true,
        'class' => true,
    )
);

$social_icon_allowed_html = function_exists('vms_vendor_profiles_social_icon_allowed_html')
    ? vms_vendor_profiles_social_icon_allowed_html()
    : array(
        'svg' => array(
            'aria-hidden' => true,
            'focusable' => true,
            'viewbox' => true,
        ),
        'path' => array(
            'd' => true,
            'fill' => true,
        ),
    );

foreach ($social_icon_allowed_html as $tag => $attrs) {
    $profile_markup_allowed_html[$tag] = array_merge(
        isset($profile_markup_allowed_html[$tag]) && is_array($profile_markup_allowed_html[$tag])
            ? $profile_markup_allowed_html[$tag]
            : array(),
        $attrs
    );
}
?>
<main class="vms-vendor-profile" role="main">
    <section class="vms-vp-hero">
        <div class="vms-vp-hero-media">
            <?php if (has_post_thumbnail($vendor_id)) : ?>
                <div class="vms-vp-avatar">
                    <?php echo get_the_post_thumbnail($vendor_id, 'large'); ?>
                </div>
            <?php else : ?>
                <div class="vms-vp-avatar vms-vp-avatar--placeholder" aria-hidden="true"></div>
            <?php endif; ?>
        </div>

        <div class="vms-vp-hero-body">
            <h1 class="vms-vp-title"><?php echo esc_html(get_the_title($vendor_id)); ?></h1>

            <?php if ($show_loc && ($city !== '' || $state !== '')) : ?>
                <p class="vms-vp-location"><?php echo esc_html(trim($city . ($city !== '' && $state !== '' ? ', ' : '') . $state)); ?></p>
            <?php endif; ?>

            <?php if ($social_markup !== '') : ?>
                <?php echo wp_kses($social_markup, $profile_markup_allowed_html); ?>
            <?php endif; ?>

            <div class="vms-vp-actions">
                <?php if ($show_phone && $phone !== '') : ?>
                    <a class="vms-vp-btn" href="<?php echo esc_url('tel:' . preg_replace('/[^0-9\+]/', '', $phone)); ?>"><?php echo esc_html__('Call', 'vms'); ?></a>
                <?php endif; ?>

                <?php if ($show_email && $email !== '') : ?>
                    <a class="vms-vp-btn" href="<?php echo esc_url('mailto:' . sanitize_email($email)); ?>"><?php echo esc_html__('Email', 'vms'); ?></a>
                <?php endif; ?>

                <?php if ($show_website && $website !== '') : ?>
                    <a class="vms-vp-btn" href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener"><?php echo esc_html__('Website', 'vms'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($next_show_markup !== '') : ?>
        <?php echo wp_kses($next_show_markup, $profile_markup_allowed_html); ?>
    <?php endif; ?>

    <?php if (trim((string) $vendor->post_content) !== '') : ?>
        <section class="vms-vp-card">
            <h2 class="vms-vp-h2"><?php echo esc_html__('About', 'vms'); ?></h2>
            <div class="vms-vp-content">
                <?php echo wp_kses(apply_filters('the_content', $vendor->post_content), $profile_markup_allowed_html); ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($video_embed !== '') : ?>
        <section class="vms-vp-card">
            <h2 class="vms-vp-h2"><?php echo esc_html__('Featured video', 'vms'); ?></h2>
            <div class="vms-vp-video"><?php echo wp_kses($video_embed, $profile_markup_allowed_html); ?></div>
        </section>
    <?php endif; ?>

    <?php if (!empty($gallery_images)) : ?>
        <section class="vms-vp-card">
            <h2 class="vms-vp-h2"><?php echo esc_html__('Photos', 'vms'); ?></h2>
            <div class="vms-vp-gallery">
                <?php foreach ($gallery_images as $image_url) : ?>
                    <a class="vms-vp-gallery__item" href="<?php echo esc_url($image_url); ?>" target="_blank" rel="noopener">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr(get_the_title($vendor_id)); ?>">
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (($show_email && $email !== '') || ($show_phone && $phone !== '') || ($show_website && $website !== '')) : ?>
        <section class="vms-vp-card">
            <h2 class="vms-vp-h2"><?php echo esc_html__('Contact', 'vms'); ?></h2>
            <div class="vms-vp-contact">
                <?php if ($show_phone && $phone !== '') : ?>
                    <div class="vms-vp-contact-row">
                        <span class="vms-vp-contact-label"><?php echo esc_html__('Phone', 'vms'); ?></span>
                        <a class="vms-vp-contact-value" href="<?php echo esc_url('tel:' . preg_replace('/[^0-9\+]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a>
                    </div>
                <?php endif; ?>

                <?php if ($show_email && $email !== '') : ?>
                    <div class="vms-vp-contact-row">
                        <span class="vms-vp-contact-label"><?php echo esc_html__('Email', 'vms'); ?></span>
                        <a class="vms-vp-contact-value" href="<?php echo esc_url('mailto:' . sanitize_email($email)); ?>"><?php echo esc_html($email); ?></a>
                    </div>
                <?php endif; ?>

                <?php if ($show_website && $website !== '') : ?>
                    <div class="vms-vp-contact-row">
                        <span class="vms-vp-contact-label"><?php echo esc_html__('Website', 'vms'); ?></span>
                        <a class="vms-vp-contact-value" href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener"><?php echo esc_html($website); ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php
wp_reset_postdata();
get_footer();
