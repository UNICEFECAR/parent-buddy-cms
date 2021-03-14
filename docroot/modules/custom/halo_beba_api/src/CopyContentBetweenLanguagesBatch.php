<?php

namespace Drupal\halo_beba_api;

use Drupal;
use Drupal\node\Entity\Node;

class CopyContentBetweenLanguagesBatch {

  /**
   * @param string $source_langcode
   * @param string $target_langcode
   * @param array  $context
   *
   * @throws \Exception
   */
  public static function copyContentBetweenLanguages(string $source_langcode, string $target_langcode, array &$context): void {
    $current_account = Drupal::currentUser();
    $connection = Drupal::database();
    $content_types = [
      'article',
      'daily_homescreen_messages',
      'faq',
      'milestone',
      'video_article',
    ];

    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current'] = 0;

      // count number of Content Items we need to Translate
      $sql = '
        SELECT
          COUNT(DISTINCT nfd.nid) AS totals
        FROM node_field_data AS nfd 
        WHERE nfd.type IN (:content_types[])
          AND nfd.langcode = :source_langcode
      ';
      $sql_query = $connection->query($sql, [':content_types[]' => $content_types, ':source_langcode' => $source_langcode]);

      $context['sandbox']['max'] = $sql_query->fetchField();

      unset($sql_query, $sql);
    }

    $sql = '
      SELECT
        nfd.nid
      FROM node_field_data AS nfd 
      WHERE nfd.type IN (:content_types[])
        AND nfd.langcode = :source_langcode
        AND nfd.nid > :current
      ORDER BY nfd.nid
    ';
    $sql_query = $connection->queryRange($sql, 0, 10, [':content_types[]' => $content_types, ':source_langcode' => $source_langcode, ':current' => $context['sandbox']['current']]);

    $content_nids = [];
    while ($row = $sql_query->fetchField()) {
      $content_nids[] = $row;
    }

    if (!empty($content_nids)) {
      foreach ($content_nids as $content_nid) {
        $context['sandbox']['progress']++;
        $context['sandbox']['current'] = $content_nid;

        /** @var Node $node */
        $node = Node::load($content_nid);

        // if we do not have translation in target language create one
        if ((NULL !== $node) && !$node->hasTranslation($target_langcode)) {
          if ($node->get('langcode')->value === $source_langcode) {
            $translated_node = $node;
          } else {
            $translated_node = Drupal::service('entity.repository')->getTranslationFromContext($node, $source_langcode);
          }
          unset($node);

          // add translation to target language
          $translated_node->addTranslation($target_langcode, $translated_node->toArray());
          $translated_node->save();
          unset($translated_node);

          // get translated node and set status and moderation state
          $node = Node::load($content_nid);
          $use_node = Drupal::service('entity.repository')->getTranslationFromContext($node, $target_langcode);

          $use_node->setUnpublished();
          $use_node->setNewRevision();
          $use_node->setRevisionCreationTime(time());
          $use_node->setRevisionAuthorId($current_account->id());
          $use_node->set('moderation_state', 'review_after_translation');
          $use_node->set('status', 0);
          $use_node->setOwnerId($current_account->id());

          $use_node->save();
          unset($use_node);
        }

        $context['message'] = t('Now processing %progress out of %max items.', array('%progress' => $context['sandbox']['progress'], '%max' => $context['sandbox']['max']));

        if ($context['sandbox']['progress'] !== $context['sandbox']['max']) {
          $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
        }
      }
    } else {
      $context['finished'] = 1;
    }
  }

  /**
   * @param bool  $success
   * @param array $results
   * @param array $operations
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function copyContentBetweenLanguagesFinished(bool $success, array $results, array $operations): void {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = 'Successfully copied Content between languages.';
    } else {
      $message = t('Finished with an error.');
    }

    Drupal::messenger()->addStatus($message);
  }

}
