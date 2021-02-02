<?php

namespace Drupal\halo_beba_api\Plugin\rest\resource;

use Drupal;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\node\Entity\Node;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\Webform;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "list_webform_resource",
 *   label = @Translation("List Webforms"),
 *   uri_paths = {
 *     "canonical" = "/api/list-webform/{langcode}"
 *   }
 * )
 */
class ListWebformResource extends ResourceBase {
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
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   * @throws \Drupal\Core\Entity\EntityMalformedException Throws exception expected.
   */
  public function get(string $langcode): ResourceResponse {
    // Check if current user has permission to use the Rest API
    if (!Drupal::currentUser()->hasPermission('restful get list_webform_resource')) {
      throw new AccessDeniedHttpException('Access Denied. You do not have permission to access this resource!!!');
    }

    $return = [
      'total' => 0,
      'data'  => [],
    ];

    $published = $this->currentRequest->get('published') ?? 1;

    // fix until we update the HaloBeba APP to deal with new setting for Serbian language
    if ($langcode === 'sr') {
      $langcode_adjusted = 'rs-sr';
    } else {
      $langcode_adjusted = $langcode;
    }

    // get all webform nodes
    $total = Drupal::entityQuery('node')
                   ->condition('langcode', $langcode_adjusted)
                   ->condition('type', 'webform');
    if (1 === (int) $published) {
      $total = $total->condition('status', 1);
    }
    $total = $total->sort('changed', 'DESC')
                   ->count()
                   ->execute();

    if ($total) {
      $nids = Drupal::entityQuery('node')
                    ->condition('langcode', $langcode_adjusted)
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
          // if current node is in the same language as requested language use the already loaded node, if not translate it
          if ($node->get('langcode')->value === $langcode_adjusted) {
            $translated_node = $node;
          } else {
            $translated_node = $node->getTranslation($langcode_adjusted);
          }

          $webform_value = $translated_node->get('webform')->getValue();
          $poll_id = $webform_value[0]['target_id'];

          /** @var Webform $poll */
          $poll = Webform::load($poll_id);

          if ($poll !== NULL) {
            $current_langcode = $poll->getLangcode();

            // check if poll has a translation in langcode we are requesting
            if ($current_langcode === $langcode_adjusted) {
              $poll_link = $poll->toUrl('canonical', ['absolute' => TRUE])->toString(TRUE)->getGeneratedUrl();
            } else {
              /** @var \Drupal\locale\LocaleConfigManager $local_config_manager */
              $local_config_manager = Drupal::service('locale.config_manager');
              if ($local_config_manager->hasTranslation('webform.webform.' . $poll_id, $langcode_adjusted)) {
                $poll_link = $poll->toUrl('canonical', ['absolute' => TRUE])->toString(TRUE)->getGeneratedUrl();

                $poll_link = str_ireplace('/'. $current_langcode . '/', '/' . $langcode_adjusted . '/', $poll_link);
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

        $return['total'] = $counter;
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
