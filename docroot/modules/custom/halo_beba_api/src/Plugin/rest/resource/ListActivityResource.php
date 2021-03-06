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
 *   id = "list_activity_resource",
 *   label = @Translation("List Activity"),
 *   uri_paths = {
 *     "canonical" = "/api/list-activity/{langcode}/{id}"
 *   }
 * )
 */
class ListActivityResource extends ResourceBase {
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
   * @param int    $id
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException Throws exception expected.
   */
  public function get(string $langcode, int $id): ResourceResponse {
    // Check if current user has permission to use the Rest API
    if (!Drupal::currentUser()->hasPermission('restful get list_activity_resource')) {
      throw new AccessDeniedHttpException('Access Denied. You do not have permission to access this resource!!!');
    }
    require_once HALO_BEBA_MODULE_PATH . '/includes/get_data_from_media_entities.php';

    $return = [
      'total' => 0,
      'data'  => [],
    ];

    $type = 'activity';
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

    $total = Drupal::entityQuery('node')
                   ->condition('langcode', $langcode_adjusted)
                   ->condition('type', $type);
    if (!empty($id)) {
      $total = $total->condition('nid', $id);
    } else {
      if (1 === (int) $published) {
        $total = $total->condition('status', 1);
      }
      if ($older_then) {
        $total = $total->condition('changed', $older_then, '>=');
      }
    }
    $total = $total->sort('changed', 'DESC')
                   ->count()
                   ->execute();

    if ($total) {
      $nids = Drupal::entityQuery('node')
                    ->condition('langcode', $langcode_adjusted)
                    ->condition('type', $type);
      if (!empty($id)) {
        $nids = $nids->condition('nid', $id);
      } else{
        if (1 === (int) $published) {
          $nids = $nids->condition('status', 1);
        }
        if ($older_then) {
          $nids = $nids->condition('changed', $older_then, '>=');
        }
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

          /**
           * Get Entity basic information
           */
          $one_entity = [
            'id'         => $translated_node->id(),
            'type'       => $translated_node->getType(),
            'langcode'   => $langcode,
            'title'      => $translated_node->getTitle(),
            'body'       => $translated_node->get('body')->value,
            'summary'    => $translated_node->get('body')->summary,
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
          $one_entity['predefined_tags'] = $predefined_tags;

          /**
           * Get Cover Image data
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

          /**
           * Get Equipment data
           */
          $one_entity['equipment'] = $translated_node->get('field_equipment')->value;

          /**
           * Get Entity Keywords ID
           */
          $keywords = [];
          foreach ($translated_node->get('field_keywords')->getValue() as $keyword) {
            $kid = $keyword['target_id'];

            $keywords[] = $kid;
          }
          $one_entity['keywords'] = $keywords;

          /**
           * Get Entity Related Articles ID
           */
          $related_articles = [];
          foreach ($translated_node->get('field_related_articles')->getValue() as $related_article) {
            $raid = $related_article['target_id'];

            $related_articles[] = $raid;
          }
          $one_entity['related_articles'] = $related_articles;

          /**
           * Get Entity Related Milestones ID
           */
          $related_milestones = [];
          foreach ($translated_node->get('field_related_milestones')->getValue() as $related_milestone) {
            $raid = $related_milestone['target_id'];

            $related_milestones[] = $raid;
          }
          $one_entity['related_milestones'] = $related_milestones;

          /**
           * Get Type of Support data
           */
          $one_entity['type_of_support'] = $translated_node->get('field_type_of_support')->value;

          /**
           * Get Mandatory Content data
           */
          $one_entity['mandatory_content'] = $translated_node->get('field_mandatory_content')->value;

          $data[] = $one_entity;
        }

        $return['data'] = $data;
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
      'id' => 0,
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
