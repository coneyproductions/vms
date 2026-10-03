<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

final class WP_Post
{
    public int $ID;

    public function __construct(int $id)
    {
        $this->ID = $id;
    }
}

$attachment_url_calls = array();

function vmseb_get_event_plan_public_event_post(int $event_plan_id): ?WP_Post
{
    return $event_plan_id === 700 ? new WP_Post(701) : null;
}

function get_post_thumbnail_id(int $post_id): int
{
    return array(701 => 901, 700 => 902, 800 => 903)[$post_id] ?? 0;
}

function wp_get_attachment_image_url(int $attachment_id, string $size): string
{
    $GLOBALS['attachment_url_calls'][] = array($attachment_id, $size);
    return "https://example.test/uploads/{$attachment_id}-{$size}.jpg";
}

require dirname(__DIR__) . '/includes/helpers.php';

function vmseb_poster_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

vmseb_poster_assert(vmseb_get_event_banner_image_id(700) === 901, 'linked public event poster was not preferred');
vmseb_poster_assert(vmseb_get_event_banner_image_id(800) === 903, 'Event Plan poster fallback was not retained');
vmseb_poster_assert(vmseb_get_event_banner_image_id(900) === 0, 'missing poster did not fail closed');
vmseb_poster_assert(vmseb_get_event_banner_image_url(700) === 'https://example.test/uploads/901-medium.jpg', 'compact event-choice URL contract changed');
vmseb_poster_assert($attachment_url_calls === array(array(901, 'medium')), 'event-choice URL did not retain the medium source');

$public_source = (string) file_get_contents(dirname(__DIR__) . '/includes/public.php');
$css_source = (string) file_get_contents(dirname(__DIR__) . '/assets/css/public.css');

vmseb_poster_assert(strpos($public_source, "wp_get_attachment_image(\n                \$event_poster_id,\n                'large'") !== false, 'Ordering for card does not use WordPress responsive image markup');
vmseb_poster_assert(strpos($public_source, "'sizes'    => '(max-width: 440px) min(72vw, 280px), (max-width: 700px) 110px, 160px'") !== false, 'responsive poster sizes contract is missing');
vmseb_poster_assert(strpos($public_source, "wp_kses(\$event_poster_html, \$event_poster_allowed_html)") !== false, 'responsive image attributes are not preserved by the output allowlist');
vmseb_poster_assert(strpos($public_source, "'srcset'        => true") !== false && strpos($public_source, "'sizes'         => true") !== false, 'responsive image attributes are absent from the output allowlist');
vmseb_poster_assert(strpos($css_source, 'aspect-ratio:3/4') !== false, 'poster frame does not have an explicit portrait aspect ratio');
vmseb_poster_assert(strpos($css_source, '.vmseb-event-card__poster img{display:block;width:100%;height:100%;object-fit:contain}') !== false, 'desktop poster is not contained');
vmseb_poster_assert(strpos($css_source, '.vmseb-event-card__poster{width:min(72vw,280px);height:auto;min-height:0;margin:0 auto;padding:10px}') !== false, 'mobile portrait treatment is missing');

fwrite(STDOUT, "PASS: Express Bar responsive event poster 0.6.38\n");
