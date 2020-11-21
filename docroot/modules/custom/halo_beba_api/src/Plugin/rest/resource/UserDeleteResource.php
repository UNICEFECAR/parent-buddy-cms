<?php

namespace Drupal\halo_beba_api\Plugin\rest\resource;

use Drupal;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\user\Entity\User;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "user_delete_resource",
 *   label = @Translation("User Delete"),
 *   uri_paths = {
 *     "canonical" = "/api/user/delete"
 *   }
 * )
 */
class UserDeleteResource extends ResourceBase {
  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

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
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get(): ResourceResponse {
    // Check if current user has permission to use the Rest API
    if (!Drupal::currentUser()->hasPermission('administer users')) {
      throw new AccessDeniedHttpException('Access Denied. You do not have permission to access this resource!!!');
    }

    $return = [
      'status'  => TRUE,
      'message' => '',
    ];

    $username = $this->currentRequest->get('username');

    $connection = Drupal::database();

    // check if this username exists
    $sql = '
      SELECT
        ufd.uid
      FROM users_field_data AS ufd 
      WHERE ufd.name = :username
    ';
    $query = $connection->query($sql, [':username' => $username]);
    $uid = $query->fetchField();

    if (!empty($uid)) {
      try {
        /** @var User $user */
        $user = User::load($uid);

        if (NULL !== $user) {
          $user->delete();

          $return['message'] = 'User successfully deleted';
        } else {
          $return['status'] = FALSE;
          $return['message'] = 'There is no user that matches that username.';
        }
      } catch(Exception $e) {
        $return['status'] = FALSE;
        $return['message'] = $e->getMessage();
      }
    } else {
      $return['status'] = FALSE;
      $return['message'] = 'There is no user that matches that username.';
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
