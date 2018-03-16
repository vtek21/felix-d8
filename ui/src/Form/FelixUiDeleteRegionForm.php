<?php

/**
 * @file
 * Contains \Drupal\felix_ui\Form\FelixUiDeleteRegionForm.
 */

namespace Drupal\felix_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class FelixUiDeleteRegionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'felix_ui_delete_region_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $region = NULL) {
    $region = db_select('felix_region', 'cbr')
      ->fields('cbr', ['name', 'title'])
      ->condition('cbr.name', $region)
      ->execute()
      ->fetchObject();
    if (!$region) {
      drupal_not_found();
      module_invoke_all('exit');
      exit;
    }

    $form['#felix_region'] = $region;

    $info = '<p>' . t('Are you sure you want to delete the region %title?', [
      '%title' => $region->title
      ]) . '</p>';

    $form['info'] = [
      '#type' => 'markup',
      '#markup' => $info,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Delete'),
    ];

    $form['cancel'] = [
      '#type' => 'markup',
      '#markup' => l(t('cancel'), 'admin/structure/felix'),
    ];

    return $form;
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    db_delete('felix_region')->condition('name', $form['#felix_region']->name)->execute();
    db_delete('felix_block')->condition('region', $form['#felix_region']->name)->execute();
    drupal_set_message(t('The region %title has been deleted.', [
      '%title' => $form['#felix_region']->title
      ]));
    $form_state->set(['redirect'], 'admin/structure/felix');
  }

}
?>
