<?php

namespace Drupal\halo_beba_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

class ProcessAustralianArticlesPrepare extends FormBase {

  /**
   * @inheritDoc
   */
  public function getFormId(): string {
    return 'process_australian_articles_prepare_form';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes'] = array('enctype' => 'multipart/form-data');

    $form['category'] = array(
      '#type'          => 'textfield',
      '#title'         => $this->t('Category'),
      '#description'   => $this->t('Enter Category from Taxonomy that matches this Import sheet'),
      '#default_value' => '',
    );

    $validators = [
      'file_validate_extensions' => ['csv'],
    ];
    $form['upload'] = [
      '#type'              => 'managed_file',
      '#name'              => 'upload',
      '#title'             => $this->t('Australian Article File'),
      '#description'       => $this->t('CSV file format only'),
      '#upload_validators' => $validators,
      '#upload_location'   => 'public://uploads/',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Upload CSV'),
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
    $category = $form_state->getValue('category');
    $file_data = $form_state->getValue('upload');

    /** @var File $new_file */
    $new_file = File::load(reset($file_data));
    $new_file->setPermanent();
    $new_file->save();

    $batch = array(
      'title'        => t('Importing Data...'),
      'operations'   => [['\Drupal\halo_beba_api\ImportCSVDataToDatabase::importAustralianArticles', [$new_file->getFileUri(), $category]]],
      'init_message' => t('Import is starting.'),
      'finished'     => '\Drupal\halo_beba_api\ImportCSVDataToDatabase::importAustralianArticlesFinished',
    );
    batch_set($batch);
  }
}
