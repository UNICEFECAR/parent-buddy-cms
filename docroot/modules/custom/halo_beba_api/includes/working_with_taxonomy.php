<?php

use Drupal\taxonomy\Entity\Term;

/**
 * @param int    $tid
 * @param string $langcode
 *
 * @return string
 */
function halo_beba_api_get_term_name_from_id($tid, $langcode = 'en') {
  /** @var Term $term */
  $term = Term::load($tid);

  if ($term !== NULL) {
    // if current term is in the same language and requested language just use the term
    if ($term->get('langcode')->value === $langcode) {
      $translated_term = $term;
    } else {
      $translated_term = $term->getTranslation($langcode);
    }

    return $translated_term->get('name')->value;
  }

  return '';
}

/**
 * @param string $name
 * @param string $type
 * @param int    $parent
 *
 * @return mixed
 */
function halo_beba_api_get_term_id_from_name($name, $type = '', $parent = 0) {
  $entity_query = Drupal::entityQuery('taxonomy_term');
  $entity_query->condition('name', $name);
  $entity_query->condition('parent', $parent);

  if (!empty($type)) {
    $entity_query->condition('vid', $type);
  }

  $tids = $entity_query->execute();

  return reset($tids);
}
