<?php

use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;

/**
 * @param \Drupal\media\Entity\Media $media_entity
 * @param string                     $image_style Use one of the Image Styles that System supports
 * @param string                     $alt         Alternative text shown for images
 * @param string                     $title       Title text shown for images
 *
 * @return array|string[]
 */
function get_data_from_media_entities(Media $media_entity, $image_style = 'halobeba_style', $alt = '', $title = '') {
  $return = [];

  // get entity type and proceed accordingly
  $media_type = $media_entity->bundle();

  if ($media_type === 'image') {
    $return = [
      'type' => 'image',
      'url'  => '',
      'name' => '',
      'alt'  => '',
    ];

    $mid = $media_entity->get('image')->target_id;

    if (!empty($mid)) {
      if (empty($title)) {
        $mname = $media_entity->get('name')->value;
      } elseif ($title === '&quot;&quot;') {
        $mname = '';
      } else {
        $mname = $title;
      }
      if (empty($alt)) {
        $malt = $media_entity->get('image')->alt;
      } else {
        $malt = $alt;
      }

      /** @var File $image */
      $image = File::load($mid);

      if (!empty($image_style)) {
        $path = $image->getFileUri();

        /** @var ImageStyle $implement_style */
        $implement_style = ImageStyle::load($image_style);
        $image_style_path = $implement_style->buildUri($path);

        // if we are working with 'halobeba_style' create it just in case
        if (($image_style === 'halobeba_style') && !file_exists($image_style_path)) {
          $queue = \Drupal::queue('halo_beba_api_update_image_styles');
          $data = ['entity' => $image];
          $queue->createItem($data);
        }

        $url = $implement_style->buildUrl($path);
      } else {
        $url = $image->createFileUrl(FALSE);
      }

      // create image link and return needed data
      $return['url'] = $url;
      $return['name'] = $mname;
      $return['alt'] = $malt;
    }
  } elseif ($media_type === 'video') {
    $return = [
      'type'      => 'video',
      'url'       => $media_entity->get('field_media_video_embed_field')->value,
      'name'      => $media_entity->get('name')->value,
      'site'      => (stripos($media_entity->get('field_media_video_embed_field')->value, 'vimeo') !== FALSE) ? 'vimeo' : 'youtube',
      'thumbnail' => '',
    ];

    $tid = $media_entity->get('thumbnail')->target_id;
    if (!empty($tid)) {
      /** @var File $thumbnail */
      $thumbnail = File::load($tid);

      if (!empty($image_style)) {
        $thumbnail_path = $thumbnail->getFileUri();

        /** @var ImageStyle $implement_style */
        $implement_style = ImageStyle::load($image_style);
        $image_style_path = $implement_style->buildUri($thumbnail_path);

        // if we are working with 'halobeba_style' create it just in case
        if (($image_style === 'halobeba_style') && !file_exists($image_style_path)) {
          $queue = \Drupal::queue('halo_beba_api_update_image_styles');
          $data = ['entity' => $thumbnail];
          $queue->createItem($data);
        }

        $return['thumbnail'] = $implement_style->buildUrl($thumbnail_path);
      } else {
        $return['thumbnail'] = $thumbnail->createFileUrl(FALSE);
      }
    }
  }

  return $return;
}
