<?php

namespace Drupal\halo_beba_api;

use Drupal;
use Drupal\taxonomy\Entity\Term;

class ImportCSVDataToDatabase {
  /**
   * @param string $file_path
   * @param string $category
   * @param array  $context
   *
   * @throws \Exception
   */
  public static function importAustralianArticles($file_path, $category, &$context): void {
    require_once HALO_BEBA_MODULE_PATH . '/includes/clean_string.php';

    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['added'] = time();

      $fh = fopen($file_path, 'rb');
      $counter = 0;
      if ($fh) {
        //remove header
        fgetcsv($fh);
        $context['sandbox']['current_position'] = ftell($fh);

        while(!feof($fh)) {
          $content = fgetcsv($fh);

          if (!empty(trim($content[2]))) {
            $counter++;
          }
        }
        fclose($fh);
        unset($fh);
      }
      $context['sandbox']['max'] = $counter;

      $context['results']['file'] = $file_path;
    }

    if (file_exists($file_path) && is_readable($file_path)) {
      $f = fopen($file_path, 'rb');
      // move to the line we need to process
      fseek($f, $context['sandbox']['current_position']);

      $counter = 0;
      $article_data = [];
      while ($row = fgetcsv($f)) {
        if (!empty(trim($row[2]))) {
          $context['sandbox']['progress']++;
          $counter++;

          $article_data[] = [
            'category'         => $category,
            'finally_selected' => !empty(trim($row[0])) ? 1 : 0,
            'article_title'    => halo_beba_api_clean_string(trim($row[1])),
            'article_link'     => trim($row[2]),
            'child_age'        => !empty(trim($row[4])) ? trim($row[4]) : NULL,
            'child_gender'     => !empty(trim($row[5])) ? trim($row[5]) : NULL,
            'parent_gender'    => !empty(trim($row[6])) ? trim($row[6]) : NULL,
            'season'           => !empty(trim($row[7])) ? trim($row[7]) : NULL,
            'tag_1'            => NULL,
            'tag_2'            => NULL,
            'tag_3'            => NULL,
            'processed'        => 0,
          ];

          if (($counter === 10) && !empty($article_data)) {
            self::flush_data('australian_articles_data', $article_data);
            $article_data = [];

            break;
          }
        }
      }

      $context['sandbox']['current_position'] = ftell($f);

      fclose($f);
      unset($f);

      if (!empty($article_data)) {
        self::flush_data('australian_articles_data', $article_data);
      }

      $context['message'] = t('Now processing %progress out of %max items.', array('%progress' => $context['sandbox']['progress'], '%max' => $context['sandbox']['max']));

      if ($context['sandbox']['progress'] !== $context['sandbox']['max']) {
        $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
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
  public static function importAustralianArticlesFinished($success, $results, $operations): void {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = 'Successfully Prepared Australian Articles.';

      $fids = Drupal::entityQuery('file')->condition('uri', $results['file'])->execute();
      if (!empty($fids)) {
        $file = Drupal\file\Entity\File::load(reset($fids));
        if ($file !== NULL) {
          $file->delete();
        }
      }
    } else {
      $message = t('Finished with an error.');
    }

    Drupal::messenger()->addStatus($message);
  }

  /**
   * @param string $database
   * @param array  $rows
   *
   * @throws \Exception
   */
  private static function flush_data($database, $rows): void {
    $insert_fields = array_keys($rows[0]);

    $connection = Drupal::database();
    $query = $connection->insert($database)->fields($insert_fields);

    foreach ($rows as $row) {
      $query->values($row);
    }

    $query->execute();
  }

  /**
   * @param string $file_path
   * @param string $langcode
   * @param array  $context
   *
   * @throws \Exception
   */
  public static function importTaxonomyTranslations($file_path, $langcode, &$context): void {
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['added'] = time();

      $fh = fopen($file_path, 'rb');
      $counter = 0;
      if ($fh) {
        $context['sandbox']['current_position'] = ftell($fh);

        while(!feof($fh)) {
          $content = fgetcsv($fh);

          if (!empty(trim($content[2]))) {
            $counter++;
          }
        }
        fclose($fh);
        unset($fh);
      }
      $context['sandbox']['max'] = $counter;

      $context['results']['file'] = $file_path;
    }

    if (file_exists($file_path) && is_readable($file_path)) {
      $f = fopen($file_path, 'rb');
      // move to the line we need to process
      fseek($f, $context['sandbox']['current_position']);

      $counter = 0;
      while ($row = fgetcsv($f)) {
        if (!empty(trim($row[2]))) {
          $context['sandbox']['progress']++;
          $counter++;

          $tid = trim($row[0]);
          $term_translation = trim($row[2]);

          /** @var Term $term */
          $term = Term::load($tid);

          if ((NULL !== $term) && !$term->hasTranslation($langcode)) {
            $term->addTranslation($langcode, [
              'name'      => $term_translation,
              'published' => 1,
            ])->save();
          }

          if ($counter === 10) {
            break;
          }
        }
      }

      $context['sandbox']['current_position'] = ftell($f);

      fclose($f);
      unset($f);

      $context['message'] = t('Now processing %progress out of %max items.', array('%progress' => $context['sandbox']['progress'], '%max' => $context['sandbox']['max']));

      if ($context['sandbox']['progress'] !== $context['sandbox']['max']) {
        $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
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
  public static function importTaxonomyTranslationsFinished($success, $results, $operations): void {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = 'Successfully Imported Taxonomy Translations.';

      $fids = Drupal::entityQuery('file')->condition('uri', $results['file'])->execute();
      if (!empty($fids)) {
        $file = Drupal\file\Entity\File::load(reset($fids));
        if ($file !== NULL) {
          $file->delete();
        }
      }
    } else {
      $message = t('Finished with an error.');
    }

    Drupal::messenger()->addStatus($message);
  }
}
