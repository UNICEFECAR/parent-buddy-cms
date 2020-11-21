<?php

namespace Drupal\halo_beba_api\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

class ProcessAustralianArticlesExecute extends FormBase {

  /**
   * @inheritDoc
   */
  public function getFormId(): string {
    return 'process_australian_articles_execute_form';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state):array {
    $form['article_id'] = array(
      '#type'          => 'textfield',
      '#title'         => $this->t('Article ID'),
      '#description'   => $this->t(''),
      '#default_value' => '',
    );

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Process Articles'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * @inheritDoc
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {

  }

  /**
   * @inheritDoc
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    require_once HALO_BEBA_MODULE_PATH . '/includes/working_with_taxonomy.php';

    $counter = 0;

    $article_id = $form_state->getValue('article_id');
    $articles = self::get_articles($article_id);

    foreach ($articles as $article) {
      $id = $article['id'];

      $category = $article['category'];
      $category_tid = halo_beba_api_get_term_id_from_name($category, 'categories');

      $article_link = $article['article_link'] . '?SQ_DESIGN_NAME=unicef';

      $child_age = $article['child_age'];
      $child_gender = $article['child_gender'];
      $parent_gender = $article['parent_gender'];
      $season = $article['season'];

      $predefined_tags = [];
      if (!empty($child_age)) {
        // get Child’s Age term_id
        $term_parent_id = halo_beba_api_get_term_id_from_name("Child’s Age", 'predefined_tags');

        $predefined_tags[] = halo_beba_api_get_term_id_from_name($child_age, 'predefined_tags', $term_parent_id);
      }
      if (!empty($child_gender)) {
        // get Child’s Gender term_id
        $term_parent_id = halo_beba_api_get_term_id_from_name("Child’s Gender", 'predefined_tags');

        $predefined_tags[] = halo_beba_api_get_term_id_from_name($child_gender, 'predefined_tags', $term_parent_id);
      }
      if (!empty($parent_gender)) {
        // get Parent’s Gender term_id
        $term_parent_id = halo_beba_api_get_term_id_from_name("Parent’s Gender", 'predefined_tags');

        $predefined_tags[] = halo_beba_api_get_term_id_from_name($parent_gender, 'predefined_tags', $term_parent_id);
      }
      if (!empty($season)) {
        // get Season term_id
        $term_parent_id = halo_beba_api_get_term_id_from_name("Season", 'predefined_tags');

        $predefined_tags[] = halo_beba_api_get_term_id_from_name($season, 'predefined_tags', $term_parent_id);
      }

      $connection = curl_init();

      curl_setopt($connection, CURLOPT_URL, $article_link);
      curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($connection, CURLOPT_HEADER, 0);
      curl_setopt($connection, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($connection, CURLOPT_CONNECTTIMEOUT, 30);
      curl_setopt($connection, CURLOPT_FAILONERROR, true);

      $response = curl_exec($connection);

      // get article metadata
      preg_match_all('/<dd>(.*?)<\/dd>/i', $response, $metadata_sections_array);

      if (!empty($metadata_sections_array)) {
        $article_title = html_entity_decode(trim($metadata_sections_array[1][1]));
        $keywords = html_entity_decode(trim($metadata_sections_array[1][3]));
        $summary = html_entity_decode(strip_tags(trim($metadata_sections_array[1][4])));
        $summary = str_ireplace('Key points', '', $summary);

        // see if we are working with 'video article' or 'article'
        if (stripos($article_link, '/videos/') !== FALSE) {
          /**
           * VIDEO ARTICLE
           */
          $article_keywords = [];
          if (!empty($keywords)) {
            $keywords_array = explode(',', $keywords);

            foreach ($keywords_array as $one_keyword) {
              $one_keyword = trim($one_keyword);

              // check if we already have this term
              $ktid = halo_beba_api_get_term_id_from_name($one_keyword, 'keywords');

              if (empty($ktid)) {
                // we do not have term create it
                $term = Term::create([
                  'vid'      => 'keywords',
                  'langcode' => 'en',
                  'name'     => $one_keyword,
                  'parent'   => [0],
                ]);
                $term->save();

                $ktid = $term->id();
              }

              $article_keywords[] = $ktid;
            }
          }

          $creation_time = time();

          $node = Node::create([
            'type'                          => 'video_article',
            'langcode'                      => 'en',
            'created'                       => $creation_time,
            'changed'                       => $creation_time,
            'title'                         => $article_title,
            'body'                          => [
              'summary' => '',
              'value'   => '',
              'format'  => 'rich_text',
            ],
            'field_australian_article'      => 1,
            'field_content_category'        => [$category_tid],
            'field_content_predefined_tags' => $predefined_tags,
            'field_keywords'                => $article_keywords,
            'field_cover_video'             => 111,
            'field_references_and_comments' => [
              'value'   => 'Imported Raising Children Australia Article: ' . $article['article_link'],
              'format'  => 'rich_text',
            ],
          ]);

          $node->save();

          // mark article as processed
          $connection = Drupal::database();
          $connection
            ->update('australian_articles_data')
            ->fields([
              'processed' => 1,
            ])
            ->condition('id', $id)
          ->execute();

          $counter++;
        } else {
          /**
           * ARTICLE
           */
          // count content containers
          $container_count = substr_count($response, '<div id="content_container');

          $body = '';
          if ($container_count > 0) {
            // get content containers
            $process_body = $response;

            // get all content containers
            $start = stripos($process_body, '<div id="content_container_');

            $process_body = substr($process_body, $start);

            $end = stripos($process_body, '</main>');

            $process_body = trim(substr($process_body, 0, $end));

            // remove all id attributes
            $process_body = preg_replace('/( id="[\w[:blank:]_-]+")/i', '', $process_body);

            // remove all class attributes
            $process_body = preg_replace('/( class="[\w[:blank:]_-]+")/i', '', $process_body);

            // remove all links and keep link text
            $process_body = preg_replace('/<a (.*?)>(.*?)<\/a>/i', "\\2", $process_body);

            $embedded_img_count = substr_count($process_body, '<img');

            if ($embedded_img_count > 0) {
              for ($i = 0; $i < $embedded_img_count; $i++) {
                // find the start of img tag
                $start = strpos($process_body, '<img');

                // cut the content from this point so we can find the position of '>' for the image tag and calculate the length of img tag so we can cut it and replace it
                $temp = substr($process_body, $start);
                $end = strpos($temp, '/>');
                $img_length = $end + 2;

                $img_tag = substr($process_body, $start, $img_length);

                if (!empty($img_tag)) {
                  // get alt text for image
                  preg_match('/alt="([\w[:blank:].,\'?!;:#\$%&()*+\-\/<>=@\[\]^_{}|~]+)"/i', $img_tag, $img_alt_matches);
                  // get src for image
                  preg_match('/src="([\w[:blank:].,\'?!;:#\$%&()*+\-\/<>=@\[\]^_{}|~]+)"/i', $img_tag, $img_src_matches);

                  if (!empty($img_src_matches)) {
                    $src = $img_src_matches[1];
                    if (!empty($img_alt_matches)) {
                      $alt = $img_alt_matches[1];
                    } else {
                      $alt = '';
                    }

                    $replace_text = "
                      <h2>
                        <div>Please check the image on Raising Children site and see if it can be embedded.</div>
                        <div>The link for the image is '{$src}' and the alt text for the image is '{$alt}'.</div>
                      </h2>
                    ";
                    $replace_text = trim(preg_replace('/\s+/', ' ', $replace_text));
                    $replace_text = trim(preg_replace('/> </', '><', $replace_text));

                    // replace embedded code with the HTML img tag
                    $process_body = str_ireplace($img_tag, $replace_text, $process_body);
                  }
                }
              }
            }

            $body = $process_body;

            $article_keywords = [];
            if (!empty($keywords)) {
              $keywords_array = explode(',', $keywords);

              foreach ($keywords_array as $one_keyword) {
                $one_keyword = trim($one_keyword);

                // check if we already have this term
                $ktid = halo_beba_api_get_term_id_from_name($one_keyword, 'keywords');

                if (empty($ktid)) {
                  // we do not have term create it
                  $term = Term::create([
                    'vid'      => 'keywords',
                    'langcode' => 'en',
                    'name'     => $one_keyword,
                    'parent'   => [0],
                  ]);
                  $term->save();

                  $ktid = $term->id();
                }

                $article_keywords[] = $ktid;
              }
            }

            $creation_time = time();

            $node = Node::create([
              'type'                          => 'article',
              'langcode'                      => 'en',
              'created'                       => $creation_time,
              'changed'                       => $creation_time,
              'title'                         => $article_title,
              'body'                          => [
                'summary' => $summary,
                'value'   => $body,
                'format'  => 'rich_text',
              ],
              'field_australian_article'      => 1,
              'field_content_category'        => [$category_tid],
              'field_content_predefined_tags' => $predefined_tags,
              'field_keywords'                => $article_keywords,
              'field_cover_image'             => 110,
              'field_references_and_comments' => [
                'value'   => 'Imported Raising Children Australia Article: ' . $article['article_link'],
                'format'  => 'rich_text',
              ],
            ]);

            $node->save();

            // mark article as processed
            $connection = Drupal::database();
            $connection
              ->update('australian_articles_data')
              ->fields([
                'processed' => 1,
              ])
              ->condition('id', $id)
            ->execute();

            $counter++;
          } else {
            Drupal::logger('AustralianArticleImport')->error("Couldn't process Australian Article: {$id}. Error 2.");
          }
        }
      } else {
        Drupal::logger('AustralianArticleImport')->error("Couldn't process Australian Article: {$id}. Error 1.");
      }
    }

    $this->messenger()->addStatus('Added ' . $counter . ' Australian Article(s)!!!');
  }

  /**
   * @param int $article_id
   *
   * @return array
   */
  private static function get_articles($article_id): array {
    $articles = [];

    $connection = Drupal::database();
    $query = $connection->select('australian_articles_data', 'aad');

    if (!empty($article_id)) {
      $query->condition('id', $article_id);
    } else {
      $query->condition('processed', 0);
      $query->range(0, 10);
    }

    $query->fields('aad');
    $sql_query = $query->execute();

    while ($article_data = (NULL !== $sql_query) ? $sql_query->fetchAssoc() : array()) {
      $articles[] = $article_data;
    }

    return $articles;
  }

}
