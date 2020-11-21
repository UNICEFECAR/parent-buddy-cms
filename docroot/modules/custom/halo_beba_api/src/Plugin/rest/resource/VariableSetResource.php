<?php

namespace Drupal\halo_beba_api\Plugin\rest\resource;

use Drupal;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "variable_set_resource",
 *   label = @Translation("Variable Set Resource"),
 *   serialization_class = "",
 *   uri_paths = {
 *     "https://www.drupal.org/link-relations/create" = "/api/variable-set"
 *   }
 * )
 */
class VariableSetResource extends ResourceBase {

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array                    $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string                   $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed                    $plugin_definition
   *   The plugin implementation definition.
   * @param array                    $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
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
      $container->get('logger.factory')->get('rest')
    );
  }

  /**
   * Responds to POST requests.
   *
   * Inserts key-data pair into database.
   *
   * @param array $data
   *
   * @return \Drupal\rest\ResourceResponse
   * @throws \Exception
   */
  public function post(array $data): ResourceResponse {
    // Check if current user has permission to use the Rest API
    if (!Drupal::currentUser()->hasPermission('restful post variable_set_resource')) {
      throw new AccessDeniedHttpException('Access Denied. You do not have permission to access this resource!!!');
    }

    $return = [
      'status'  => TRUE,
      'message' => '',
    ];

    $key = $data['key'];
    $data = $data['data'];

    if (!empty($key) && !empty($data)) {
      try {
        $connection = Drupal::database();

        $connection
          ->merge('halo_beba_api_variables')
          ->key('key', $key)
          ->fields([
            'data' => $data,
          ])
        ->execute();
      } catch(Exception $e) {
        $return['status'] = FALSE;
        $return['message'] = $e->getMessage();
      }
    } else {
      $return['status'] = FALSE;
      $return['message'] = 'Key and/or Data must be set.';
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
