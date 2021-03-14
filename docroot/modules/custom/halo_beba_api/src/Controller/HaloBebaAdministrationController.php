<?php

namespace Drupal\halo_beba_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines HaloBebaAdministrationController class.
 */
class HaloBebaAdministrationController extends ControllerBase {
  /**
   * Display the markup.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array
   *   Return markup array.
   */
  public function AdministrationPage(Request $request): array {
    $link_import_taxonomy_translations = Link::fromTextAndUrl('Import Taxonomy Translations', Url::fromRoute('halo_beba_api.administration_page.import_taxonomy_translations'));
    $link_copy_content_between_languages = Link::fromTextAndUrl('Copy Content Between Languages', Url::fromRoute('halo_beba_api.administration_page.copy_content_between_languages'));

    $links = [
      $link_import_taxonomy_translations->toString(),
      $link_copy_content_between_languages->toString(),
    ];

    return [
      '#type'   => 'markup',
      '#markup' => implode('<br />', $links),
    ];
  }
}
