<?php

namespace Drupal\halo_beba_api\Plugin\rest\resource;

use Drupal;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "list_content_resource",
 *   label = @Translation("List Content"),
 *   uri_paths = {
 *     "canonical" = "/api/list-content/{langcode}/{type}"
 *   }
 * )
 */
class ListContentResource extends ResourceBase {
  /**
   * Current request instance
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array                                     $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string                                    $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed                                     $plugin_definition
   *   The plugin implementation definition.
   * @param array                                     $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface                  $logger
   *   A logger instance.
   * @param \Symfony\Component\HttpFoundation\Request $currentRequest
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, Request $currentRequest) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentRequest = $currentRequest;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param string $langcode
   * @param string $type
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException Throws exception expected.
   */
  public function get(string $langcode, string $type): ResourceResponse {
    // Check if current user has permission to use the Rest API
    if (!Drupal::currentUser()->hasPermission('restful get list_content_resource')) {
      throw new AccessDeniedHttpException('Access Denied. You do not have permission to access this resource!!!');
    }
    require_once HALO_BEBA_MODULE_PATH . '/includes/get_data_from_media_entities.php';

    $return = [
      'total' => 0,
      'data'  => [],
    ];

    if (empty($type)) {
      $types = [
        'article',
        'faq',
        'video_article',
      ];
    } else {
      $types = [
        $type,
      ];
    }
    $page = $this->currentRequest->get('page') ?? 0;
    $number_per_page = $this->currentRequest->get('numberOfItems') ?? 10;
    $older_then = $this->currentRequest->get('updatedFromDate') ?? 0;
    $published = $this->currentRequest->get('published') ?? 1;

    // fix until we update the HaloBeba APP to deal with new setting for Serbian language
    if ($langcode === 'sr') {
      $langcode_adjusted = 'rs-sr';
    } else {
      $langcode_adjusted = $langcode;
    }

    if ($types) {
      $total = Drupal::entityQuery('node')
                     ->condition('langcode', $langcode_adjusted)
                     ->condition('type', $types, 'IN');
      if (1 === (int) $published) {
        $total = $total->condition('status', 1);
      }
      if ($older_then) {
        $total = $total->condition('changed', $older_then, '>=');
      }
      $total = $total->sort('changed', 'DESC')
                     ->count()
                     ->execute();

      if ($total) {
        $nids = Drupal::entityQuery('node')
                      ->condition('langcode', $langcode_adjusted)
                      ->condition('type', $types, 'IN');
        if (1 === (int) $published) {
          $nids = $nids->condition('status', 1);
        }
        if ($older_then) {
          $nids = $nids->condition('changed', $older_then, '>=');
        }
        $nids = $nids->sort('changed', 'DESC')
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
            // if current node is in the same language as requested language use the already loaded node, if not translate it
            if ($node->get('langcode')->value === $langcode_adjusted) {
              $translated_node = $node;
            } else {
              $translated_node = $node->getTranslation($langcode_adjusted);
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
                    // embedded media entity cannot be loaded so just remove it from body
                    $replace_text = '';
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

              if ($photo_entity === NULL) {
                // there is a problem with Cover Image, load default baby image
                $photo_entity = Media::load(2701);
              }

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
               * Get Entity Related Articles ID
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

              if ($video_entity !== NULL) {
                $media_data = get_data_from_media_entities($video_entity);

                $one_entity['cover_image'] = [
                  'url'  => $media_data['thumbnail'],
                  'name' => $media_data['name'],
                  'alt'  => '',
                ];
                $one_entity['cover_video'] = [
                  'url'  => $media_data['url'],
                  'name' => $media_data['name'],
                  'site' => $media_data['site'],
                ];
              } else {
                // there is a problem with Cover Video, load default Cover Image instead
                /** @var Media $photo_entity */
                $photo_entity = Media::load(2701);

                $media_data = get_data_from_media_entities($photo_entity);

                $one_entity['cover_image'] = [
                  'url'  => $media_data['url'],
                  'name' => $media_data['name'],
                  'alt'  => $media_data['alt'],
                ];
              }

              /**
               * Get Entity Related Articles ID
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

          $return['data'] = $data;
        }
      }
    }

    return $this->getResponse($return);
  }

  /**
   * {@inheritdoc}
   */
  public function routes(): RouteCollection {
    $collection = parent::routes();

    // Add defaults for optional parameters.
    $defaults = [
      'type' => '',
    ];
    foreach ($collection->all() as $route) {
      $route->addDefaults($defaults);
    }

    return $collection;
  }

  /**
   *
   * @param array $message
   * @param int   $cache
   *
   * @return ResourceResponse
   */
  protected function getResponse(array $message, int $cache = 0): ResourceResponse {
    $build = [
      '#cache' => [
        'max-age' => $cache,
      ],
    ];

    $response = new ResourceResponse($message);
    $cache_metadata = CacheableMetadata::createFromRenderArray($build);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }
}
