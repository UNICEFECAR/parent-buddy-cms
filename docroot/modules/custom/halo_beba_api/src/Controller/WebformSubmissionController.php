<?php

namespace Drupal\halo_beba_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines WebformSubmissionController class.
 */
class WebformSubmissionController extends ControllerBase {

  /**
   * Display the markup.
   *
   * @param string                                    $langcode
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array
   *   Return markup array.
   */
  public function WebformSubmissionPage(string $langcode, Request $request): array {
    switch ($langcode) {
      case 'en':
        $submission_text = '';
      break;

      case 'sr':
        $submission_text = '';
      break;

      default:
        $submission_text = '';
      break;
    }

    return [
      '#type'   => 'markup',
      '#markup' => $submission_text,
    ];
  }
}
