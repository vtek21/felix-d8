<?php

/**
 * @file
 * Contains \Drupal\felix_ui\Form\FelixUiDeleteSetForm.
 */

namespace Drupal\felix_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class FelixUiDeleteSetForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'felix_ui_delete_set_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $set = NULL) {
    $set = db_select('felix_block_set', 'cbs')
      ->fields('cbs', ['name', 'title'])
      ->condition('cbs.name', $set)
      ->execute()
      ->fetchObject();
    if (!$set) {
      drupal_not_found();
      module_invoke_all('exit');
      exit;
    }

    $form['#felix_set'] = $set;

    $regions = db_select('felix_region', 'cr')
      ->fields('cr', ['title'])
      ->condition('cr.block_set', $set->name)
      ->orderBy('title')
      ->execute()
      ->fetchCol();

    if ($regions) {
      $info = '<p>' . t('You cannot delete this set because it is in use for the following set(s):') . '</p>';
      $info .= theme('item_list', $regions);
    }
    else {
      $info = '<p>' . t('Are you sure you want to delete the block set %title?', [
        '%title' => $set->title
        ]) . '</p>';
    }

    $form['info'] = [
      '#type' => 'markup',
      '#markup' => $info,
    ];

    if (!$regions) {
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => t('Delete'),
      ];
    }

    $form['cancel'] = [
      '#type' => 'markup',
      '#markup' => l(t('cancel'), 'admin/structure/felix/sets'),
    ];

    return $form;
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    db_delete('felix_block_set')->condition('name', $form['#felix_set']->name)->execute();
    db_delete('felix_block_set_block')->condition('block_set', $form['#felix_set']->name)->execute();
    db_delete('felix_block_set_nodetype')->condition('block_set', $form['#felix_set']->name)->execute();
    db_delete('felix_block_set_viewmode')->condition('block_set', $form['#felix_set']->name)->execute();

    drupal_set_message(t('The block set %title has been deleted.', [
      '%title' => $form['#felix_set']->title
      ]));
    $form_state->set(['redirect'], 'admin/structure/felix/sets');
  }

}
?>
