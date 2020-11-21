<?php

namespace Drupal\halo_beba_api\Plugin\rest\resource;

use Drupal;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\taxonomy\Entity\Term;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "list_taxonomy_resource",
 *   label = @Translation("List Taxonomy Terms for Vocabulary"),
 *   uri_paths = {
 *     "canonical" = "/api/list-taxonomy/{langcode}/{vocabulary}"
 *   }
 * )
 */
class ListTaxonomyResource extends ResourceBase {
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
   * @param string $vocabulary
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException Throws exception expected.
   */
  public function get(string $langcode, string $vocabulary): ResourceResponse {
    // Check if current user has permission to use the Rest API
    if (!Drupal::currentUser()->hasPermission('restful get list_taxonomy_resource')) {
      throw new AccessDeniedHttpException('Access Denied. You do not have permission to access this resource!!!');
    }

    $return = [
      'data'  => [],
    ];

    $query = Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $vocabulary);
    $query->sort('weight');
    $tids = $query->execute();
    $terms = Term::loadMultiple($tids);

    $termList = [];
    /** @var Term $term */
    foreach($terms as $term) {
      $parent = $term->get('parent')->target_id;
      $tid = $term->id();

      // if current node is in the same language and requested language just use the node
      if ($term->get('langcode')->value === $langcode) {
        $translated_term = $term;
      } elseif($term->hasTranslation($langcode)){
        $translated_term = Drupal::service('entity.repository')->getTranslationFromContext($term, $langcode);
      } else {
        $translated_term = $term;
      }

      if ((int) $parent !== 0) {
        if (!isset($termList[$parent])) {
          $termList[$parent] = [
            'name'     => '',
            'children' => [],
          ];
        }
        $termList[$parent]['children'][$tid] = [
          'name'     => $translated_term->getName(),
          'children' => [],
        ];
      } elseif (!isset($termList[$tid]['children'])) {
        $termList[$tid] = [
          'name'     => $translated_term->getName(),
          'children' => [],
        ];
      } else {
        $termList[$tid]['name'] = $translated_term->getName();
      }
    }

    if (!empty($termList)) {
      $return['data'] = $termList;
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
