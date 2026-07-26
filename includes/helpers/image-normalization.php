<?php
defined('ABSPATH') || exit;

if (!function_exists('vms_normalize_uploaded_image_to_jpeg')) {
    /**
     * Normalize an uploaded image into a JPEG proof copy.
     *
     * This path intentionally re-saves the image so oversized uploads are reduced,
     * EXIF orientation can be honored, and most source metadata is dropped.
     *
     * @param array<string,mixed> $args
     * @return array{path:string,mime:string,width:int,height:int,filesize:int}|WP_Error
     */
    function vms_normalize_uploaded_image_to_jpeg(string $source_path, string $target_dir, string $filename_base, array $args = array())
    {
        $source_path = trim($source_path);
        $target_dir = trim($target_dir);
        $filename_base = sanitize_file_name($filename_base);

        if ($source_path === '' || !file_exists($source_path)) {
            return new WP_Error('image_processing_failed', __('Could not process image. Try a JPG, PNG, WEBP, or screenshot instead.', 'backstage-venue-manager'));
        }

		if ($target_dir === '' || !is_dir($target_dir) || !wp_is_writable($target_dir)) {
			return new WP_Error('save_failed', __('Could not save the normalized image. Please try again.', 'backstage-venue-manager'));
		}

        if ($filename_base === '') {
            $filename_base = 'proof-' . gmdate('Ymd-His');
        }

        $max_dimension = isset($args['max_dimension']) ? absint($args['max_dimension']) : 2200;
        $quality = isset($args['quality']) ? absint($args['quality']) : 86;
        $max_output_bytes = isset($args['max_output_bytes']) ? (int) $args['max_output_bytes'] : 0;

        $max_dimension = max(600, min(3200, $max_dimension));
        $quality = max(60, min(92, $quality));
        $max_output_bytes = max(0, $max_output_bytes);

        if (!function_exists('wp_get_image_editor')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $editor = wp_get_image_editor($source_path);
        if (is_wp_error($editor)) {
            return new WP_Error('image_processing_failed', __('Could not process image. Try a JPG, PNG, WEBP, or screenshot instead.', 'backstage-venue-manager'));
        }

        if (method_exists($editor, 'maybe_exif_rotate')) {
            $rotated = $editor->maybe_exif_rotate();
            if (is_wp_error($rotated)) {
                return new WP_Error('image_processing_failed', __('Could not process image. Try a JPG, PNG, WEBP, or screenshot instead.', 'backstage-venue-manager'));
            }
        }

        $size = $editor->get_size();
        if (is_wp_error($size) || !is_array($size)) {
            return new WP_Error('image_processing_failed', __('Could not process image. Try a JPG, PNG, WEBP, or screenshot instead.', 'backstage-venue-manager'));
        }

        $width = isset($size['width']) ? (int) $size['width'] : 0;
        $height = isset($size['height']) ? (int) $size['height'] : 0;
        if ($width <= 0 || $height <= 0) {
            return new WP_Error('image_processing_failed', __('Could not process image. Try a JPG, PNG, WEBP, or screenshot instead.', 'backstage-venue-manager'));
        }

        if ($width > $max_dimension || $height > $max_dimension) {
            $resized = $editor->resize($max_dimension, $max_dimension, false);
            if (is_wp_error($resized)) {
                return new WP_Error('image_processing_failed', __('Could not process image. Try a JPG, PNG, WEBP, or screenshot instead.', 'backstage-venue-manager'));
            }
        }

        if (method_exists($editor, 'set_quality')) {
            $editor->set_quality($quality);
        }

        $filename = wp_unique_filename($target_dir, $filename_base . '.jpg');
        $target_path = trailingslashit($target_dir) . $filename;
        $saved = $editor->save($target_path, 'image/jpeg');
        if (is_wp_error($saved) || empty($saved['path']) || !file_exists((string) $saved['path'])) {
            return new WP_Error('image_processing_failed', __('Could not process image. Try a JPG, PNG, WEBP, or screenshot instead.', 'backstage-venue-manager'));
        }

        $saved_path = (string) $saved['path'];
		@chmod($saved_path, 0640); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Preserve 0640 permissions on the validated proof-image file written into the plugin-controlled target directory; WP_Filesystem would add incompatible credential-driven semantics.

        $filesize = (int) @filesize($saved_path);
        if ($max_output_bytes > 0 && $filesize > $max_output_bytes) {
			wp_delete_file($saved_path);
			return new WP_Error('file_too_large', __('This image is still too large after processing. Try a screenshot or smaller JPG/PNG.', 'backstage-venue-manager'));
		}

        return array(
            'path' => $saved_path,
            'mime' => sanitize_text_field((string) ($saved['mime-type'] ?? 'image/jpeg')),
            'width' => isset($saved['width']) ? (int) $saved['width'] : 0,
            'height' => isset($saved['height']) ? (int) $saved['height'] : 0,
            'filesize' => $filesize,
        );
    }
}
