<?php
defined('ABSPATH') || exit;

get_header();

$bvmgr_vendor_profile_post = isset($GLOBALS['bvmgr_vendor_profile_post']) ? $GLOBALS['bvmgr_vendor_profile_post'] : null;
if (!($bvmgr_vendor_profile_post instanceof WP_Post)) {
    echo '<main class="vms-vendor-profile"><div class="vms-vp-card"><p>' . esc_html__('Vendor not found.', 'backstage-venue-manager') . '</p></div></main>';
    wp_reset_postdata();
    get_footer();
    return;
}

$bvmgr_vendor_profile_post_id = (int) $bvmgr_vendor_profile_post->ID;

$bvmgr_vendor_profile_show_email_meta_key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'public_profile_show_email') : '_vms_vendor_public_profile_show_email';
$bvmgr_vendor_profile_show_phone_meta_key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'public_profile_show_phone') : '_vms_vendor_public_profile_show_phone';
$bvmgr_vendor_profile_show_website_meta_key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'public_profile_show_website') : '_vms_vendor_public_profile_show_website';
$bvmgr_vendor_profile_show_location_meta_key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'public_profile_show_location') : '_vms_vendor_public_profile_show_location';

$bvmgr_vendor_profile_raw_show_email = (string) get_post_meta($bvmgr_vendor_profile_post_id, $bvmgr_vendor_profile_show_email_meta_key, true);
$bvmgr_vendor_profile_raw_show_phone = (string) get_post_meta($bvmgr_vendor_profile_post_id, $bvmgr_vendor_profile_show_phone_meta_key, true);
$bvmgr_vendor_profile_raw_show_website = (string) get_post_meta($bvmgr_vendor_profile_post_id, $bvmgr_vendor_profile_show_website_meta_key, true);
$bvmgr_vendor_profile_raw_show_location = (string) get_post_meta($bvmgr_vendor_profile_post_id, $bvmgr_vendor_profile_show_location_meta_key, true);

$bvmgr_vendor_profile_show_email = ($bvmgr_vendor_profile_raw_show_email === '') ? true : ($bvmgr_vendor_profile_raw_show_email === '1');
$bvmgr_vendor_profile_show_phone = ($bvmgr_vendor_profile_raw_show_phone === '') ? true : ($bvmgr_vendor_profile_raw_show_phone === '1');
$bvmgr_vendor_profile_show_website = ($bvmgr_vendor_profile_raw_show_website === '') ? true : ($bvmgr_vendor_profile_raw_show_website === '1');
$bvmgr_vendor_profile_show_location = ($bvmgr_vendor_profile_raw_show_location === '') ? true : ($bvmgr_vendor_profile_raw_show_location === '1');

$bvmgr_vendor_profile_primary_email_meta_key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'primary_email') : '_vms_vendor_primary_email';
$bvmgr_vendor_profile_primary_phone_meta_key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'primary_phone') : '_vms_vendor_primary_phone';
$bvmgr_vendor_profile_website_meta_key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'website') : '_vms_vendor_website';

$bvmgr_vendor_profile_email = (string) get_post_meta($bvmgr_vendor_profile_post_id, $bvmgr_vendor_profile_primary_email_meta_key, true);
$bvmgr_vendor_profile_phone = (string) get_post_meta($bvmgr_vendor_profile_post_id, $bvmgr_vendor_profile_primary_phone_meta_key, true);
$bvmgr_vendor_profile_website = (string) get_post_meta($bvmgr_vendor_profile_post_id, $bvmgr_vendor_profile_website_meta_key, true);

if ($bvmgr_vendor_profile_email === '') {
    $bvmgr_vendor_profile_legacy_email_meta_key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'contact_email') : '_vms_contact_email';
    $bvmgr_vendor_profile_email = (string) get_post_meta($bvmgr_vendor_profile_post_id, $bvmgr_vendor_profile_legacy_email_meta_key, true);
}
if ($bvmgr_vendor_profile_phone === '') {
    $bvmgr_vendor_profile_legacy_phone_meta_key = function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'contact_phone') : '_vms_contact_phone';
    $bvmgr_vendor_profile_phone = (string) get_post_meta($bvmgr_vendor_profile_post_id, $bvmgr_vendor_profile_legacy_phone_meta_key, true);
}
if ($bvmgr_vendor_profile_website === '') {
    $bvmgr_vendor_profile_website = (string) get_post_meta($bvmgr_vendor_profile_post_id, '_vms_website_url', true);
}

$bvmgr_vendor_profile_city  = (string) get_post_meta($bvmgr_vendor_profile_post_id, function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'city') : '_vms_city', true);
$bvmgr_vendor_profile_state = (string) get_post_meta($bvmgr_vendor_profile_post_id, function_exists('bvmgr_meta_key') ? bvmgr_meta_key('vendor', 'state') : '_vms_state', true);
if (($bvmgr_vendor_profile_city === '' || $bvmgr_vendor_profile_state === '')) {
    $bvmgr_vendor_profile_legacy_location = (string) get_post_meta($bvmgr_vendor_profile_post_id, '_vms_vendor_location', true);
    if ($bvmgr_vendor_profile_legacy_location !== '') {
        $bvmgr_vendor_profile_legacy_location_parts = array_map('trim', explode(',', $bvmgr_vendor_profile_legacy_location, 2));
        if ($bvmgr_vendor_profile_city === '' && isset($bvmgr_vendor_profile_legacy_location_parts[0]) && $bvmgr_vendor_profile_legacy_location_parts[0] !== '') {
            $bvmgr_vendor_profile_city = $bvmgr_vendor_profile_legacy_location_parts[0];
        }
        if ($bvmgr_vendor_profile_state === '' && isset($bvmgr_vendor_profile_legacy_location_parts[1]) && $bvmgr_vendor_profile_legacy_location_parts[1] !== '') {
            $bvmgr_vendor_profile_state = $bvmgr_vendor_profile_legacy_location_parts[1];
        }
    }
}

$bvmgr_vendor_profile_next_show_markup = function_exists('vms_vendor_profiles_render_next_show_card')
    ? (string) vms_vendor_profiles_render_next_show_card($bvmgr_vendor_profile_post_id)
    : '';

$bvmgr_vendor_profile_social_markup = function_exists('vms_vendor_profiles_render_social_links')
    ? (string) vms_vendor_profiles_render_social_links($bvmgr_vendor_profile_post_id)
    : '';

$bvmgr_vendor_profile_video_url = trim((string) get_post_meta($bvmgr_vendor_profile_post_id, '_vms_vendor_featured_video_url', true));
$bvmgr_vendor_profile_video_embed = '';
if ($bvmgr_vendor_profile_video_url !== '') {
    $bvmgr_vendor_profile_video_embed = wp_oembed_get($bvmgr_vendor_profile_video_url, array('width' => 960));
    if ($bvmgr_vendor_profile_video_embed === false || $bvmgr_vendor_profile_video_embed === '') {
        $bvmgr_vendor_profile_video_embed = '<p><a href="' . esc_url($bvmgr_vendor_profile_video_url) . '" target="_blank" rel="noopener">' . esc_html__('Watch featured video', 'backstage-venue-manager') . '</a></p>';
    }
}

$bvmgr_vendor_profile_gallery_images = array();
for ($bvmgr_vendor_profile_gallery_image_index = 1; $bvmgr_vendor_profile_gallery_image_index <= 5; $bvmgr_vendor_profile_gallery_image_index++) {
    $bvmgr_vendor_profile_gallery_image_candidate_url = trim((string) get_post_meta($bvmgr_vendor_profile_post_id, '_vms_vendor_gallery_image_' . $bvmgr_vendor_profile_gallery_image_index, true));
    if ($bvmgr_vendor_profile_gallery_image_candidate_url !== '') {
        $bvmgr_vendor_profile_gallery_images[] = esc_url($bvmgr_vendor_profile_gallery_image_candidate_url);
    }
}

$bvmgr_vendor_profile_allowed_html = function_exists('vms_vendor_profiles_promo_allowed_html')
    ? vms_vendor_profiles_promo_allowed_html()
    : wp_kses_allowed_html('post');
$bvmgr_vendor_profile_allowed_html['section'] = array(
    'aria-label' => true,
    'class' => true,
);
$bvmgr_vendor_profile_allowed_html['div'] = array(
    'aria-label' => true,
    'class' => true,
);
$bvmgr_vendor_profile_allowed_html['p'] = array(
    'class' => true,
);
$bvmgr_vendor_profile_allowed_html['h2'] = array(
    'class' => true,
);
$bvmgr_vendor_profile_allowed_html['a'] = array_merge(
    isset($bvmgr_vendor_profile_allowed_html['a']) && is_array($bvmgr_vendor_profile_allowed_html['a'])
        ? $bvmgr_vendor_profile_allowed_html['a']
        : array(),
    array(
        'aria-label' => true,
        'class' => true,
        'href' => true,
        'rel' => true,
        'target' => true,
    )
);
$bvmgr_vendor_profile_allowed_html['span'] = array_merge(
    isset($bvmgr_vendor_profile_allowed_html['span']) && is_array($bvmgr_vendor_profile_allowed_html['span'])
        ? $bvmgr_vendor_profile_allowed_html['span']
        : array(),
    array(
        'aria-hidden' => true,
        'class' => true,
    )
);

$bvmgr_vendor_profile_social_icon_allowed_html = function_exists('vms_vendor_profiles_social_icon_allowed_html')
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

foreach ($bvmgr_vendor_profile_social_icon_allowed_html as $bvmgr_vendor_profile_social_icon_tag => $bvmgr_vendor_profile_social_icon_attributes) {
    $bvmgr_vendor_profile_allowed_html[$bvmgr_vendor_profile_social_icon_tag] = array_merge(
        isset($bvmgr_vendor_profile_allowed_html[$bvmgr_vendor_profile_social_icon_tag]) && is_array($bvmgr_vendor_profile_allowed_html[$bvmgr_vendor_profile_social_icon_tag])
            ? $bvmgr_vendor_profile_allowed_html[$bvmgr_vendor_profile_social_icon_tag]
            : array(),
        $bvmgr_vendor_profile_social_icon_attributes
    );
}
?>
<main class="vms-vendor-profile" role="main">
    <section class="vms-vp-hero">
        <div class="vms-vp-hero-media">
            <?php if (has_post_thumbnail($bvmgr_vendor_profile_post_id)) : ?>
                <div class="vms-vp-avatar">
                    <?php echo get_the_post_thumbnail($bvmgr_vendor_profile_post_id, 'large'); ?>
                </div>
            <?php else : ?>
                <div class="vms-vp-avatar vms-vp-avatar--placeholder" aria-hidden="true"></div>
            <?php endif; ?>
        </div>

        <div class="vms-vp-hero-body">
            <h1 class="vms-vp-title"><?php echo esc_html(get_the_title($bvmgr_vendor_profile_post_id)); ?></h1>

            <?php if ($bvmgr_vendor_profile_show_location && ($bvmgr_vendor_profile_city !== '' || $bvmgr_vendor_profile_state !== '')) : ?>
                <p class="vms-vp-location"><?php echo esc_html(trim($bvmgr_vendor_profile_city . ($bvmgr_vendor_profile_city !== '' && $bvmgr_vendor_profile_state !== '' ? ', ' : '') . $bvmgr_vendor_profile_state)); ?></p>
            <?php endif; ?>

            <?php if ($bvmgr_vendor_profile_social_markup !== '') : ?>
                <?php echo wp_kses($bvmgr_vendor_profile_social_markup, $bvmgr_vendor_profile_allowed_html); ?>
            <?php endif; ?>

            <div class="vms-vp-actions">
                <?php if ($bvmgr_vendor_profile_show_phone && $bvmgr_vendor_profile_phone !== '') : ?>
                    <a class="vms-vp-btn" href="<?php echo esc_url('tel:' . preg_replace('/[^0-9\+]/', '', $bvmgr_vendor_profile_phone)); ?>"><?php echo esc_html__('Call', 'backstage-venue-manager'); ?></a>
                <?php endif; ?>

                <?php if ($bvmgr_vendor_profile_show_email && $bvmgr_vendor_profile_email !== '') : ?>
                    <a class="vms-vp-btn" href="<?php echo esc_url('mailto:' . sanitize_email($bvmgr_vendor_profile_email)); ?>"><?php echo esc_html__('Email', 'backstage-venue-manager'); ?></a>
                <?php endif; ?>

                <?php if ($bvmgr_vendor_profile_show_website && $bvmgr_vendor_profile_website !== '') : ?>
                    <a class="vms-vp-btn" href="<?php echo esc_url($bvmgr_vendor_profile_website); ?>" target="_blank" rel="noopener"><?php echo esc_html__('Website', 'backstage-venue-manager'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($bvmgr_vendor_profile_next_show_markup !== '') : ?>
        <?php echo wp_kses($bvmgr_vendor_profile_next_show_markup, $bvmgr_vendor_profile_allowed_html); ?>
    <?php endif; ?>

    <?php if (trim((string) $bvmgr_vendor_profile_post->post_content) !== '') : ?>
        <section class="vms-vp-card">
            <h2 class="vms-vp-h2"><?php echo esc_html__('About', 'backstage-venue-manager'); ?></h2>
            <div class="vms-vp-content">
                <?php echo wp_kses(apply_filters('the_content', $bvmgr_vendor_profile_post->post_content), $bvmgr_vendor_profile_allowed_html); ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($bvmgr_vendor_profile_video_embed !== '') : ?>
        <section class="vms-vp-card">
            <h2 class="vms-vp-h2"><?php echo esc_html__('Featured video', 'backstage-venue-manager'); ?></h2>
            <div class="vms-vp-video"><?php echo wp_kses($bvmgr_vendor_profile_video_embed, $bvmgr_vendor_profile_allowed_html); ?></div>
        </section>
    <?php endif; ?>

    <?php if (!empty($bvmgr_vendor_profile_gallery_images)) : ?>
        <section class="vms-vp-card">
            <h2 class="vms-vp-h2"><?php echo esc_html__('Photos', 'backstage-venue-manager'); ?></h2>
            <div class="vms-vp-gallery">
                <?php foreach ($bvmgr_vendor_profile_gallery_images as $bvmgr_vendor_profile_gallery_image_url) : ?>
                    <a class="vms-vp-gallery__item" href="<?php echo esc_url($bvmgr_vendor_profile_gallery_image_url); ?>" target="_blank" rel="noopener">
                        <img src="<?php echo esc_url($bvmgr_vendor_profile_gallery_image_url); ?>" alt="<?php echo esc_attr(get_the_title($bvmgr_vendor_profile_post_id)); ?>">
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (($bvmgr_vendor_profile_show_email && $bvmgr_vendor_profile_email !== '') || ($bvmgr_vendor_profile_show_phone && $bvmgr_vendor_profile_phone !== '') || ($bvmgr_vendor_profile_show_website && $bvmgr_vendor_profile_website !== '')) : ?>
        <section class="vms-vp-card">
            <h2 class="vms-vp-h2"><?php echo esc_html__('Contact', 'backstage-venue-manager'); ?></h2>
            <div class="vms-vp-contact">
                <?php if ($bvmgr_vendor_profile_show_phone && $bvmgr_vendor_profile_phone !== '') : ?>
                    <div class="vms-vp-contact-row">
                        <span class="vms-vp-contact-label"><?php echo esc_html__('Phone', 'backstage-venue-manager'); ?></span>
                        <a class="vms-vp-contact-value" href="<?php echo esc_url('tel:' . preg_replace('/[^0-9\+]/', '', $bvmgr_vendor_profile_phone)); ?>"><?php echo esc_html($bvmgr_vendor_profile_phone); ?></a>
                    </div>
                <?php endif; ?>

                <?php if ($bvmgr_vendor_profile_show_email && $bvmgr_vendor_profile_email !== '') : ?>
                    <div class="vms-vp-contact-row">
                        <span class="vms-vp-contact-label"><?php echo esc_html__('Email', 'backstage-venue-manager'); ?></span>
                        <a class="vms-vp-contact-value" href="<?php echo esc_url('mailto:' . sanitize_email($bvmgr_vendor_profile_email)); ?>"><?php echo esc_html($bvmgr_vendor_profile_email); ?></a>
                    </div>
                <?php endif; ?>

                <?php if ($bvmgr_vendor_profile_show_website && $bvmgr_vendor_profile_website !== '') : ?>
                    <div class="vms-vp-contact-row">
                        <span class="vms-vp-contact-label"><?php echo esc_html__('Website', 'backstage-venue-manager'); ?></span>
                        <a class="vms-vp-contact-value" href="<?php echo esc_url($bvmgr_vendor_profile_website); ?>" target="_blank" rel="noopener"><?php echo esc_html($bvmgr_vendor_profile_website); ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php
wp_reset_postdata();
get_footer();
