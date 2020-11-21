<?php

namespace Drupal\halo_beba_api\Controller;

use Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;
use Drupal\webform\Entity\Webform;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines HelloController class.
 */
class HelperPagesController extends ControllerBase {

  /**
   * Display the markup.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array
   *   Return markup array.
   */
  public function FrontPage(Request $request): array {
    return [
      '#type'   => 'markup',
      '#markup' => $this->t(''),
    ];
  }

  /**
   * Display the markup.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array
   *   Return markup array.
   */
  public function TestPage(Request $request): array {
    $connection = Drupal::database();

    $sql = $connection->select('file_managed', 'fm');
    $sql->fields('fm', ['fid']);
    $sql_query = $sql->execute();

    $images = [];
    while ($row = (NULL !== $sql_query) ? $sql_query->fetchField() : NULL) {
      $images[] = $row;
    }

    if (!empty($images)) {
      foreach ($images as $image_id) {
        /** @var \Drupal\file\Entity\File $image_entity */
        $image_entity = Drupal\file\Entity\File::load($image_id);

        if ($image_entity !== NULL) {
          $image = \Drupal::service('image.factory')->get($image_entity->getFileUri());
          /** @var \Drupal\Core\Image\Image $image */
          if ($image->isValid()) {
            $queue = \Drupal::queue('halo_beba_api_update_image_styles');
            $data = ['entity' => $image_entity];
            $queue->createItem($data);
          }
        }
      }
    }


    die('popunjen queue');




    $langcode = 'en';
    $published = 1;

    // get all webform nodes
    $total = Drupal::entityQuery('node')
                   ->condition('langcode', $langcode)
                   ->condition('type', 'webform');
    if (1 === (int) $published) {
      $total = $total->condition('status', 1);
    }
    $total = $total->sort('changed', 'DESC')
                   ->count()
                   ->execute();

    if ($total) {
      $nids = Drupal::entityQuery('node')
                    ->condition('langcode', $langcode)
                    ->condition('type', 'webform');
      if (1 === (int) $published) {
        $nids = $nids->condition('status', 1);
      }
      $nids = $nids->sort('changed', 'DESC')
                   ->execute();

      if (!empty($nids)) {
        $counter = 0;

        $data = [];
        $nodes = Node::loadMultiple($nids);
        /**
         * @var int  $key
         * @var Node $node
         */
        foreach ($nodes as $key => $node) {
          // if current node is in the same language and requested language just use the node
          if ($node->get('langcode')->value === $langcode) {
            $translated_node = $node;
          } else {
            $translated_node = $node->getTranslation($langcode);
          }

          $webform_value = $translated_node->get('webform')->getValue();
          $poll_id = $webform_value[0]['target_id'];

          /** @var Webform $poll */
          $poll = Webform::load($poll_id);

          if ($poll !== NULL) {
            $current_langcode = $poll->getLangcode();

            if ($current_langcode === $langcode) {
              $poll_link = $poll->toUrl('canonical', ['absolute' => TRUE])->toString(TRUE)->getGeneratedUrl();
            } else {
              // check if poll has a translation in langcode we are requesting
              /** @var \Drupal\locale\LocaleConfigManager $local_config_manager */
              $local_config_manager = Drupal::service('locale.config_manager');
              if ($local_config_manager->hasTranslation('webform.webform.' . $poll_id, $langcode)) {
                $poll_link = $poll->toUrl('canonical', ['absolute' => TRUE])->toString(TRUE)->getGeneratedUrl();

                $poll_link = str_ireplace('/'. $current_langcode . '/', '/' . $langcode . '/', $poll_link);
              }
            }

            if (isset($poll_link)) {
              $counter++;

              $one_entity = [
                'id'         => $translated_node->id(),
                'type'       => $translated_node->getType(),
                'langcode'   => $langcode,
                'title'      => $translated_node->getTitle(),
                'category'   => $poll->get('category'),
                'link'       => $poll_link,
                'created_at' => $translated_node->get('created')->value,
                'updated_at' => $translated_node->get('changed')->value,
              ];

              /**
               * Get Entity Predefined Tags ID
               */
              $predefined_tags = [];
              foreach ($translated_node->get('field_content_predefined_tags')->getValue() as $predefined_tag) {
                $tid = $predefined_tag['target_id'];

                $predefined_tags[] = $tid;
              }
              $one_entity['tags'] = $predefined_tags;

              $data[] = $one_entity;
            }
          }
        }
      }
    }

print_r('<pre>');print_r($data);print_r('</pre>');die();



    require_once HALO_BEBA_MODULE_PATH . '/includes/get_data_from_media_entities.php';

    $nid = 91;
    $langcode = 'en';
    $nids = Drupal::entityQuery('node')
                  ->condition('nid', $nid)
                  ->execute();

    if (!empty($nids)) {
      $data = [];
      $nodes = Node::loadMultiple($nids);
      /**
       * @var int  $key
       * @var Node $node
       */
      foreach ($nodes as $key => $node) {
        // if current node is in the same language and requested language just use the node
        if ($node->get('langcode')->value === $langcode) {
          $translated_node = $node;
        } else {
          $translated_node = $node->getTranslation($langcode);
        }

        $content_type = $translated_node->getType();

        /**
         * Get Entity basic information
         */
        $one_entity = [
          'id'         => $translated_node->id(),
          'type'       => $content_type,
          'langcode'   => $langcode,
          'title'      => $translated_node->getTitle(),
          'created_at' => $translated_node->get('created')->value,
          'updated_at' => $translated_node->get('changed')->value,
        ];

        /**
         * Get Entity Content
         */
        $body = $translated_node->get('body')->value;

        $embedded_video = [];
        $embed_count = substr_count($body, '</drupal-entity>');
        if ($embed_count > 0) {
          for ($i = 0; $i < $embed_count; $i++) {
            // find start of drupal-entity embed code
            $start = strpos($body, '<drupal-entity');
            // find end of drupal-entity embed code
            $end = strpos($body, '</drupal-entity>');
            $length = $end - $start + 16;

            // get embedded code
            $embedded_code = substr($body, $start, $length);

            // get media uuid so we can get the relevant data out of it
            preg_match('/data-entity-uuid="([\w-]+)"/i', $embedded_code, $media_uuid_matches);

            if (!empty($media_uuid_matches)) {
              $media_uuid = trim($media_uuid_matches[1]);
              $media_entity = Drupal::service('entity.repository')->loadEntityByUuid('media', $media_uuid);

              if ($media_entity !== NULL) {
                // if we are dealing with embedded images we need to pull their alt and title values from embedded code
                $media_type = $media_entity->bundle();

                if ($media_type === 'image') {
                  // get alt text that should be displayed
                  preg_match('/alt="([\w[:blank:].,\'?!;:#\$%&()*+\-\/<>=@\[\]^_{}|~]+)"/i', $embedded_code, $media_alt_matches);
                  // get title text that should be displayed
                  preg_match('/title="([\w[:blank:].,\'?!;:#\$%&()*+\-\/<>=@\[\]^_{}|~]+)"/i', $embedded_code, $media_title_matches);

                  if (!empty($media_alt_matches)) {
                    $alt = $media_alt_matches[1];
                  } else {
                    $alt = '';
                  }
                  if (!empty($media_title_matches)) {
                    $title = $media_title_matches[1];
                  } else {
                    $title = '';
                  }

                  $media_data = get_data_from_media_entities($media_entity, 'halobeba_style', $alt, $title);
                } else {
                  $media_data = get_data_from_media_entities($media_entity);
                }

                if (!empty($media_data) && ($media_data['type'] === 'image')) {
                  $replace_text = "
                    <div>
                      <img src='{$media_data['url']}' alt='{$media_data['alt']}' />
                      <div class='media-copyright'>{$media_data['name']}</div>
                    </div>
                  ";
                  $replace_text = trim(preg_replace('/\s+/', ' ', $replace_text));
                  $replace_text = trim(preg_replace('/> </', '><', $replace_text));
                } elseif (!empty($media_data) && ($media_data['type'] === 'video')) {
                  $replace_text = '';
                  $embedded_video = $media_data;
                } else {
                  $replace_text = '';
                }

                // replace embedded code with the HTML img tag
                $body = str_ireplace($embedded_code, $replace_text, $body);
              } else {
                $replace_text = '';
                // replace embedded code with the HTML img tag
                $body = str_ireplace($embedded_code, $replace_text, $body);
              }
            }
          }
        }
        $one_entity['body'] = $body;
        $one_entity['summary'] = $translated_node->get('body')->summary;

        /**
         * Get Entity Category ID
         */
        $one_entity['category'] = $translated_node->get('field_content_category')->target_id;

        /**
         * Get Entity Predefined Tags ID
         */
        $predefined_tags = [];
        foreach ($translated_node->get('field_content_predefined_tags')->getValue() as $predefined_tag) {
          $tid = $predefined_tag['target_id'];

          $predefined_tags[] = $tid;
        }
        $one_entity['predefined_tags'] = $predefined_tags;

        /**
         * Get Entity Keywords ID
         */
        $keywords = [];
        foreach ($translated_node->get('field_keywords')->getValue() as $keyword) {
          $kid = $keyword['target_id'];

          $keywords[] = $kid;
        }
        $one_entity['keywords'] = $keywords;

        if ($content_type === 'article') {
          /**
           * Get Article Cover Image data
           */
          $ciid = $translated_node->get('field_cover_image')->target_id;
          /** @var Media $photo_entity */
          $photo_entity = Media::load($ciid);

          $media_data = get_data_from_media_entities($photo_entity);

          $one_entity['cover_image'] = [
            'url'  => $media_data['url'],
            'name' => $media_data['name'],
            'alt'  => $media_data['alt'],
          ];

          if (!empty($embedded_video)) {
            $one_entity['cover_video'] = [
              'url'  => $embedded_video['url'],
              'name' => $embedded_video['name'],
              'site' => $embedded_video['site'],
            ];
          }

          /**
           * Get Entity Referenced Articles ID
           */
          $related_articles = [];
          foreach ($translated_node->get('field_related_articles')->getValue() as $related_article) {
            $raid = $related_article['target_id'];

            $related_articles[] = $raid;
          }
          $one_entity['related_articles'] = $related_articles;
        } elseif ($content_type === 'video_article') {
          /**
           * Get Article Cover Video data
           */
          $cvid = $translated_node->get('field_cover_video')->target_id;
          /** @var Media $video_entity */
          $video_entity = Media::load($cvid);

          $media_data = get_data_from_media_entities($video_entity);

          $one_entity['cover_image'] = [
            'url'  => $media_data['thumbnail'],
            'name' => $media_data['name'],
            'alt'  => '',
          ];
          $one_entity['cover_video'] = [
            'url'       => $media_data['url'],
            'name'      => $media_data['name'],
            'site'      => $media_data['site'],
          ];

          /**
           * Get Entity Referenced Articles ID
           */
          $related_articles = [];
          foreach ($translated_node->get('field_related_articles')->getValue() as $related_article) {
            $raid = $related_article['target_id'];

            $related_articles[] = $raid;
          }
          $one_entity['related_articles'] = $related_articles;
        }

        $data[] = $one_entity;
      }

      print_r('<pre>');print_r($data);print_r('</pre>');die();

      $return['data'] = $data;
    }

    die('aaaaaaa');


    $status = 1;
    $types = [
      'article',
      'faq',
      'video_article',
    ];
    $langcode = 'en';
    $page = 0;
    $number_per_page = 10;
    $older_then = 0;

    $total = Drupal::entityQuery('node')
                   ->condition('langcode', $langcode)
                   ->condition('type', $types, 'IN')
                   ->condition('status', $status);
    if ($older_then) {
      $total = $total->condition('created', $older_then, '>=');
    }
    $total = $total->sort('created', 'DESC')
                   ->count()
                   ->execute();

    if ($total) {
      $nids = Drupal::entityQuery('node')
                    ->condition('langcode', $langcode)
                    ->condition('type', $types, 'IN')
                    ->condition('status', $status);
      if ($older_then) {
        $nids = $nids->condition('created', $older_then, '>=');
      }
      $nids = $nids->sort('created', 'DESC')
                   ->range($page * $number_per_page, $number_per_page)
                   ->execute();

      if (!empty($nids)) {
        $return['total'] = $total;

        $data = [];
        $nodes = Node::loadMultiple($nids);
        /**
         * @var int  $key
         * @var Node $node
         */
        foreach ($nodes as $key => $node) {
          // if current node is in the same language and requested language just use the node
          if ($node->get('langcode')->value === $langcode) {
            $translated_node = $node;
          } else {
            $translated_node = $node->getTranslation($langcode);
          }

          $content_type = $translated_node->getType();

          /**
           * Get Entity basic information
           */
          $one_entity = [
            'id'         => $translated_node->id(),
            'type'       => $content_type,
            'langcode'   => $langcode,
            'title'      => $translated_node->getTitle(),
            'created_at' => $translated_node->get('created')->value,
            'updated_at' => $translated_node->get('changed')->value,
          ];

          /**
           * Get Entity Content
           */
          $body = $translated_node->get('body')->value;

          $embedded_video = [];
          $embed_count = substr_count($body, '</drupal-entity>');
          if ($embed_count > 0) {
            for ($i = 0; $i < $embed_count; $i++) {
              // find start of drupal-entity embed code
              $start = strpos($body, '<drupal-entity');
              // find end of drupal-entity embed code
              $end = strpos($body, '</drupal-entity>');
              $length = $end - $start + 16;

              // get embedded code
              $embedded_code = substr($body, $start, $length);

              // get media uuid so we can get the relevant data out of it
              preg_match('/data-entity-uuid="([\w-]+)"/i', $embedded_code, $media_uuid_matches);

              if (!empty($media_uuid_matches)) {
                $media_uuid = trim($media_uuid_matches[1]);
                $media_entity = Drupal::service('entity.repository')->loadEntityByUuid('media', $media_uuid);

                if ($media_entity !== NULL) {
                  // if we are dealing with embedded images we need to pull their alt and title values from embedded code
                  $media_type = $media_entity->bundle();

                  if ($media_type === 'image') {
                    // get alt text that should be displayed
                    preg_match('/alt="([\w[:blank:].,\'?!;:#\$%&()*+\-\/<>=@\[\]^_{}|~]+)"/i', $embedded_code, $media_alt_matches);
                    // get title text that should be displayed
                    preg_match('/title="([\w[:blank:].,\'?!;:#\$%&()*+\-\/<>=@\[\]^_{}|~]+)"/i', $embedded_code, $media_title_matches);

                    if (!empty($media_alt_matches)) {
                      $alt = $media_alt_matches[1];
                    } else {
                      $alt = '';
                    }
                    if (!empty($media_title_matches)) {
                      $title = $media_title_matches[1];
                    } else {
                      $title = '';
                    }

                    $media_data = get_data_from_media_entities($media_entity, 'halobeba_style', $alt, $title);
                  } else {
                    $media_data = get_data_from_media_entities($media_entity);
                  }

                  if (!empty($media_data) && ($media_data['type'] === 'image')) {
                    $replace_text = "
                      <div>
                        <img src='{$media_data['url']}' alt='{$media_data['alt']}' />
                        <div class='media-copyright'>{$media_data['name']}</div>
                      </div>
                    ";
                    $replace_text = trim(preg_replace('/\s+/', ' ', $replace_text));
                    $replace_text = trim(preg_replace('/> </', '><', $replace_text));
                  } elseif (!empty($media_data) && ($media_data['type'] === 'video')) {
                    $replace_text = '';
                    $embedded_video = $media_data;
                  } else {
                    $replace_text = '';
                  }

                  // replace embedded code with the HTML img tag
                  $body = str_ireplace($embedded_code, $replace_text, $body);
                } else {
                  $replace_text = '';
                  // replace embedded code with the HTML img tag
                  $body = str_ireplace($embedded_code, $replace_text, $body);
                }
              }
            }
          }
          $one_entity['body'] = $body;
          $one_entity['summary'] = $translated_node->get('body')->summary;

          /**
           * Get Entity Category ID
           */
          $one_entity['category'] = $translated_node->get('field_content_category')->target_id;

          /**
           * Get Entity Predefined Tags ID
           */
          $predefined_tags = [];
          foreach ($translated_node->get('field_content_predefined_tags')->getValue() as $predefined_tag) {
            $tid = $predefined_tag['target_id'];

            $predefined_tags[] = $tid;
          }
          $one_entity['predefined_tags'] = $predefined_tags;

          /**
           * Get Entity Keywords ID
           */
          $keywords = [];
          foreach ($translated_node->get('field_keywords')->getValue() as $keyword) {
            $kid = $keyword['target_id'];

            $keywords[] = $kid;
          }
          $one_entity['keywords'] = $keywords;

          if ($content_type === 'article') {
            /**
             * Get Article Cover Image data
             */
            $ciid = $translated_node->get('field_cover_image')->target_id;
            /** @var Media $photo_entity */
            $photo_entity = Media::load($ciid);

            $media_data = get_data_from_media_entities($photo_entity);

            $one_entity['cover_image'] = [
              'url'  => $media_data['url'],
              'name' => $media_data['name'],
              'alt'  => $media_data['alt'],
            ];

            if (!empty($embedded_video)) {
              $one_entity['cover_video'] = [
                'url'  => $embedded_video['url'],
                'name' => $embedded_video['name'],
                'site' => $embedded_video['site'],
              ];
            }

            /**
             * Get Entity Referenced Articles ID
             */
            $related_articles = [];
            foreach ($translated_node->get('field_related_articles')->getValue() as $related_article) {
              $raid = $related_article['target_id'];

              $related_articles[] = $raid;
            }
            $one_entity['related_articles'] = $related_articles;
          } elseif ($content_type === 'video_article') {
            /**
             * Get Article Cover Video data
             */
            $cvid = $translated_node->get('field_cover_video')->target_id;
            /** @var Media $video_entity */
            $video_entity = Media::load($cvid);

            $media_data = get_data_from_media_entities($video_entity);

            $one_entity['cover_image'] = [
              'url'  => $media_data['thumbnail'],
              'name' => $media_data['name'],
              'alt'  => '',
            ];
            $one_entity['cover_video'] = [
              'url'       => $media_data['url'],
              'name'      => $media_data['name'],
              'site'      => $media_data['site'],
            ];

            /**
             * Get Entity Referenced Articles ID
             */
            $related_articles = [];
            foreach ($translated_node->get('field_related_articles')->getValue() as $related_article) {
              $raid = $related_article['target_id'];

              $related_articles[] = $raid;
            }
            $one_entity['related_articles'] = $related_articles;
          }

          $data[] = $one_entity;
        }

        print_r('<pre>');print_r($data);print_r('</pre>');die();

        $return['data'] = $data;
      }
    }

    return [
      '#type'   => 'markup',
      '#markup' => $this->t('Hello, World!'),
    ];
  }

}
