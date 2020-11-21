<?php

namespace Drupal\halo_beba_api\Plugin\rest\resource;

use Drupal;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\user\Entity\User;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "user_reset_password_resource",
 *   label = @Translation("User Reset Password"),
 *   uri_paths = {
 *     "canonical" = "/api/user/reset"
 *   }
 * )
 */
class UserResetPasswordResource extends ResourceBase {
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
      'message' => [],
    ];

    $username = $this->currentRequest->get('username');
    $langcode = $this->currentRequest->get('langcode');

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
          $new_password = $this->generateRandomString();

          $user->setPassword($new_password);
          $user->save();

          $return['message'][] = 'User Password successfully reseted';

          // send email with new password
          switch ($langcode) {
            case 'en':
              $subject = 'Replacement login information for ' . $user->getAccountName();
              $message = "
A request to reset the password for your account has been made for Parent Buddy Application.
                
You may now log-in into Application by using the following password:
                
{$new_password}
                
Please remember this password and use it to log-in in the future.
                
-- Parent Buddy Team
";
            break;

            case 'sr':
              $subject = 'Zamenske informacije za prijavu za nalog ' . $user->getAccountName();
              $message = "
Podnesen je zahtev za promenu lozinke za Halo Beba Aplikaciju.
                
Možete se prijaviti na Aplikaciju koristeći sledeću lozinku:
                
{$new_password}
                
Molimo Vas zapamtite ovu lozinku i koristite je za prijavu ubuduće.
                
-- Halo Beba Tim
";
            break;

            default:
              $subject = '';
              $message = '';
            break;
          }

          if (!empty($subject) && !empty($message)) {
            $mailManager = Drupal::service('plugin.manager.mail');
            $module = 'halo_beba_api';
            $key = 'user_reset_password';
            $to = $username;
            $params['subject'] = $subject;
            $params['message'] = $message;
            $send = true;

            $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

            if ($result['result'] !== true) {
              $return['message'][] = 'There was a problem sending Password Reset e-mail. Contact System Administrator to see on resolving the issue.';
            } else {
              $return['message'][] = 'Password Reset e-mail has been sent.';
            }
          }
        } else {
          $return['status'] = FALSE;
          $return['message'][] = 'There is no user that matches that username.';
        }
      } catch(Exception $e) {
        $return['status'] = FALSE;
        $return['message'][] = $e->getMessage();
      }
    } else {
      $return['status'] = FALSE;
      $return['message'][] = 'There is no user that matches that username.';
    }

    return $this->getResponse($return);
  }

  /**
   * @param int $length
   *
   * @return string
   * @throws \Exception
   */
  private function generateRandomString($length = 16): string {
    $possibleChars = 'abcefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';

    $possibleCharsNo = strlen($possibleChars);
    $string = '';

    for($i = 0; $i < $length; $i++) {
      $rand = random_int(0, $possibleCharsNo - 1);
      $string .= $possibleChars[$rand];
    }

    return $string;
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
