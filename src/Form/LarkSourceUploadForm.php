<?php

namespace Drupal\lark\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Form\FormStateInterface;

/**
 * Uploads an archive of lark exports.
 */
class LarkSourceUploadForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lark_source_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['file'] = [
      '#type' => 'file',
      '#title' => $this->t('Upload Archive'),
      '#description' => $this->t('Upload an archive of Lark exports.'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Upload'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \SplFileInfo[] $files */
    $files = $this->getRequest()->files->get('files', []);
    $archive_file = reset($files);
    $archive = new ArchiveTar($archive_file->getRealPath());
    /** @var \Drupal\lark\Entity\LarkSourceInterface $source */
    $source = $this->getRequest()->attributes->get('lark_source');

    if ($archive->extract($source->directoryProcessed())) {
      $this->messenger()->addMessage($this->t('Archive uploaded and extracted to Source: %source.', [
        '%source' => $source->label(),
      ]));
    }
    else {
      $this->messenger()->addError($this->t('Failed to extract archive.'));
    }

    $form_state->setRedirectUrl($source->toUrl());
  }

}
