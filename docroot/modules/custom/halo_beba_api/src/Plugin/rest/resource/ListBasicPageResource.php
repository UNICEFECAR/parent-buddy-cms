<?php

namespace Drupal\halo_beba_api\Plugin\rest\resource;

use Drupal;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "list_basic_page_resource",
 *   label = @Translation("List Basic Page"),
 *   uri_paths = {
 *     "canonical" = "/api/list-basic-page/{langcode}/{eid}"
 *   }
 * )
 */
class ListBasicPageResource extends ResourceBase {
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
   * @param int    $eid
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException Throws exception expected.
   */
  public function get(string $langcode, int $eid): ResourceResponse {
    // Check if current user has permission to use the Rest API
    if (!Drupal::currentUser()->hasPermission('restful get list_basic_page_resource')) {
      throw new AccessDeniedHttpException('Access Denied. You do not have permission to access this resource!!!');
    }
    require_once HALO_BEBA_MODULE_PATH . '/includes/get_data_from_media_entities.php';

    $return = [
      'total' => 0,
      'data'  => [],
    ];

    $type = 'page';

    // fix until we update the HaloBeba APP to deal with new setting for Serbian language
    if ($langcode === 'sr') {
      $langcode_adjusted = 'rs-sr';
    } else {
      $langcode_adjusted = $langcode;
    }

    $total = Drupal::entityQuery('node')
                   ->condition('type', $type);
    if (!empty($eid)) {
      $total->condition('nid', $eid);
    }
    $total = $total->count()
          ->execute();

    if ($total) {
      $nids = Drupal::entityQuery('node')
                    ->condition('type', $type);
      if (!empty($eid)) {
        $nids = $nids->condition('nid', $eid);
      }
      $nids = $nids->execute();

      if (!empty($nids)) {
        $data = [];

        $nodes = Node::loadMultiple($nids);
        /**
         * @var int  $key
         * @var Node $node
         */
        foreach ($nodes as $key => $node) {
          // if current node is in the same language as requested language use the already loaded node, if not translate it
          if ($node->get('langcode')->value === $langcode_adjusted) {
            $continue_process = TRUE;
            $translated_node = $node;
          } elseif($node->hasTranslation($langcode_adjusted)) {
            $continue_process = TRUE;
            $translated_node = $node->getTranslation($langcode_adjusted);
          } else {
            $continue_process = FALSE;
            $translated_node = new stdClass();
          }

          if ($continue_process) {
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
                      $media_data = [];
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

            $data[] = $one_entity;
          }
        }

        if (!empty($data)) {
          $return['total'] = count($data);

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
      'eid' => 0,
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
