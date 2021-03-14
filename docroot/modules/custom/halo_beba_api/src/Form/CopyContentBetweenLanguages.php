<?php

namespace Drupal\halo_beba_api\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class CopyContentBetweenLanguages extends FormBase {

  /**
   * @inheritDoc
   */
  public function getFormId(): string {
    return 'copy_content_between_languages_form';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $language_options = [
      ''       => t('Select Value'),
      'en'     => 'English',
      'sq'     => 'Albanian',
      'ru'     => 'Russian',
      'al-sq'  => 'Albania-Albanian',
      'bg-bg'  => 'Bulgaria-Bulgarian',
      'gr-arb' => 'Greece-Arab',
      'gr-el'  => 'Greece-Greek',
      'gr-fa'  => 'Greece-Persian',
      'xk-sq'  => 'Kosovo-Albanian',
      'xk-rs'  => 'Kosovo-Serbian',
      'kg-ky'  => 'Kyrgyzstan-Kyrgyz',
      'kg-ru'  => 'Kyrgyzstan-Russian',
      'me-cnr' => 'Montenegro-Montenegrin',
      'mk-sq'  => 'North Macedonia-Albanian',
      'mk-mk'  => 'North Macedonia-Macedonian',
      'rs-sr'  => 'Serbia-Serbian',
      'tj-ru'  => 'Tajikistan-Russian',
      'tj-tg'  => 'Tajikistan-Tajik',
      'uz-ru'  => 'Uzbekistan-Russian',
      'uz-uz'  => 'Uzbekistan-Uzbek',
    ];

    $form['source_langcode'] = [
      '#type'          => 'select',
      '#title'         => t('Source Language'),
      '#description'   => t(''),
      '#default_value' => '',
      '#options'       => $language_options,
      '#required'      => TRUE,
    ];
    $form['target_langcode'] = [
      '#type'          => 'select',
      '#title'         => t('Target Language'),
      '#description'   => t(''),
      '#default_value' => '',
      '#options'       => $language_options,
      '#required'      => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Copy Content'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * @inheritDoc
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $source_langcode = $form_state->getValue('source_langcode');
    $target_langcode = $form_state->getValue('target_langcode');

    if ($source_langcode === $target_langcode) {
      $form_state->setErrorByName('target_langcode', $this->t('Target Language must be different then Source Language.'));
    }

    // check if we have Content available in the Source Language
    $connection = Drupal::database();

    $content_types = [
      'article',
      'daily_homescreen_messages',
      'faq',
      'milestone',
      'video_article',
    ];
    $sql = '
      SELECT
        COUNT(DISTINCT nfd.nid) AS totals
      FROM node_field_data AS nfd 
      WHERE nfd.type IN (:content_types[])
        AND nfd.langcode = :source_langcode
    ';
    $sql_query = $connection->query($sql, [':content_types[]' => $content_types, ':source_langcode' => $source_langcode]);
    $count = $sql_query->fetchField();

    if (empty($count)) {
      $form_state->setErrorByName('source_langcode', $this->t('There is no Content available in the selected Source Language.'));
    }
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $source_langcode = $form_state->getValue('source_langcode');
    $target_langcode = $form_state->getValue('target_langcode');

    $batch = [
      'title'        => t('Copying Data...'),
      'operations'   => [
        ['\Drupal\halo_beba_api\CopyContentBetweenLanguagesBatch::copyContentBetweenLanguages', [$source_langcode, $target_langcode]],
      ],
      'init_message' => t('Copy Process is starting.'),
      'finished'     => '\Drupal\halo_beba_api\CopyContentBetweenLanguagesBatch::copyContentBetweenLanguagesFinished',
    ];

    batch_set($batch);
  }
}
