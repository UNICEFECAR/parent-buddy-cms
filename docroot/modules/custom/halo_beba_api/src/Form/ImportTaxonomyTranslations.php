<?php

namespace Drupal\halo_beba_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

class ImportTaxonomyTranslations extends FormBase {

  /**
   * @inheritDoc
   */
  public function getFormId(): string {
    return 'import_taxonomy_translations_form';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes'] = array('enctype' => 'multipart/form-data');

    $form['langcode'] = array(
      '#type'          => 'select',
      '#title'         => t('Language Code'),
      '#description'   => t(''),
      '#default_value' => '',
      '#options'       => array(
        ''   => t('Select Value'),
        'en' => 'English',
        'sr' => 'Serbian',
      ),
      '#required'      => TRUE,
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
      '#value'       => $this->t('Import CSV'),
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
    $langcode = $form_state->getValue('langcode');
    $file_data = $form_state->getValue('upload');

    /** @var File $new_file */
    $new_file = File::load(reset($file_data));
    $new_file->setPermanent();
    $new_file->save();

    $batch = array(
      'title'        => t('Importing Data...'),
      'operations'   => [['\Drupal\halo_beba_api\ImportCSVDataToDatabase::importTaxonomyTranslations', [$new_file->getFileUri(), $langcode]]],
      'init_message' => t('Import is starting.'),
      'finished'     => '\Drupal\halo_beba_api\ImportCSVDataToDatabase::importTaxonomyTranslationsFinished',
    );
    batch_set($batch);
  }
}
