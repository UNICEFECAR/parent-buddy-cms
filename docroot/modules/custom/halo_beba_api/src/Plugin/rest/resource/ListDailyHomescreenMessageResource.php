<?php

namespace Drupal\halo_beba_api\Plugin\rest\resource;

use Drupal;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "list_daily_homescreen_message",
 *   label = @Translation("List Daily Homescreen Message"),
 *   uri_paths = {
 *     "canonical" = "/api/list-daily-homescreen-message/{langcode}"
 *   }
 * )
 */
class ListDailyHomescreenMessageResource extends ResourceBase {
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
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException Throws exception expected.
   */
  public function get(string $langcode): ResourceResponse {
    // Check if current user has permission to use the Rest API
    if (!Drupal::currentUser()->hasPermission('restful get list_daily_homescreen_message')) {
      throw new AccessDeniedHttpException('Access Denied. You do not have permission to access this resource!!!');
    }

    $return = [
      'total' => 0,
      'data'  => [],
    ];

    $type = 'daily_homescreen_messages';
    $page = $this->currentRequest->get('page') ?? 0;
    $number_per_page = $this->currentRequest->get('numberOfItems') ?? 10;
    $older_then = $this->currentRequest->get('updatedFromDate') ?? 0;
    $published = $this->currentRequest->get('published') ?? 1;

    $total = Drupal::entityQuery('node')
                   ->condition('langcode', $langcode)
                   ->condition('type', $type);
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
                    ->condition('langcode', $langcode)
                    ->condition('type', $type);
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
          // if current node is in the same language and requested language just use the node
          if ($node->get('langcode')->value === $langcode) {
            $translated_node = $node;
          } else {
            $translated_node = $node->getTranslation($langcode);
          }

          /**
           * Get Entity basic information
           */
          $one_entity = [
            'id'         => $translated_node->id(),
            'type'       => $translated_node->getType(),
            'langcode'   => $langcode,
            'title'      => $translated_node->getTitle(),
            'created_at' => $translated_node->get('created')->value,
            'updated_at' => $translated_node->get('changed')->value,
          ];

          $data[] = $one_entity;
        }

        $return['data'] = $data;
      }
    }

    return $this->getResponse($return);
  }

  /**
   *
   * @param array $message
   * @param int   $cache
   *
   * @return ResourceResponse
   */
  protected function getResponse(array $message, $cache = 0): ResourceResponse {
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
