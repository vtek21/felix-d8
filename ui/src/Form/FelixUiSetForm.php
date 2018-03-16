<?php

/**
 * @file
 * Contains \Drupal\felix_ui\Form\FelixUiSetForm.
 */

namespace Drupal\felix_ui\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class FelixUiSetForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'felix_ui_set_form';
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state, $set = NULL) {
    if ($set) {
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

      $res = db_select('felix_block_set_block', 'cbsb')
        ->fields('cbsb', ['module', 'delta'])
        ->condition('block_set', $set->name)
        ->execute();
      $set->blocks = [];
      while ($block = $res->fetchObject()) {
        $set->blocks[$block->module][] = $block->delta;
      }

      $set->nodetypes = db_select('felix_block_set_nodetype', 'cbsn')
        ->fields('cbsn', ['nodetype'])
        ->condition('block_set', $set->name)
        ->execute()
        ->fetchCol();

      $set->viewmodes = db_select('felix_block_set_viewmode', 'cbsv')
        ->fields('cbsv', ['viewmode'])
        ->condition('block_set', $set->name)
        ->execute()
        ->fetchCol();

      $form['#felix_set'] = $set;
    }

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => t('Title'),
      '#default_value' => $set ? $set->title : '',
      '#required' => TRUE,
    ];

    $form['name'] = [
      '#type' => 'machine_name',
      '#title' => t('Machine name'),
      '#default_value' => $set ? $set->name : '',
      '#maxlength' => 64,
      '#required' => TRUE,
      '#machine_name' => [
        'exists' => 'felix_ui_set_name_exists',
        'source' => [
          'title'
          ],
        'label' => t('Name'),
        'replace_pattern' => '[^a-z0-9-]+',
        'replace' => '-',
      ],
      '#disabled' => empty($set) ? FALSE : TRUE,
    ];

    foreach (\Drupal::moduleHandler()->getImplementations('block_info') as $module) {
      $info = system_get_info('module', $module);
      $name = $info['name'];
      $package = $info['package'];

      if (!isset($form['packages'][$package])) {
        $form['packages'][$package] = [
          '#type' => 'fieldset',
          '#title' => t($package),
          '#collapsible' => TRUE,
          '#collapsed' => TRUE,
        ];
      }

      $options = ['*' => '<em>' . t('All') . '</em>'];
      $blocks = module_invoke($module, 'block_info');
      foreach ($blocks as $delta => $block) {
        $options[$delta] = $block['info'];
      }

      $form['packages'][$package]["module_$module"] = [
        '#type' => 'checkboxes',
        '#title' => Html::escape($name),
        '#options' => $options,
        '#default_value' => empty($set) || empty($set->blocks[$module]) ? [] : drupal_map_assoc($set->blocks[$module]),
      ];
    }


    @ksort($form['packages']);


    $options = [];
    foreach (node_type_get_types() as $name => $type) {
      $options[$name] = $type->get('name');
    }
    asort($options);
    $form['nodetypes'] = [
      '#type' => 'checkboxes',
      '#title' => t('Node types'),
      '#options' => $options,
      '#default_value' => empty($set) ? [] : drupal_map_assoc($set->nodetypes),
      '#description' => t('Check the nodetypes which can be used in this set.'),
    ];

    $options = [];

    $view_modes = \Drupal::entityManager()->getViewModes('node');
    foreach ($view_modes as $view_mode => $info) {
      $options[$view_mode] = $info['label'];
    }
    $form['viewmodes'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => t('View modes'),
      '#default_value' => empty($set) ? [
        'felix' => 'felix'
        ] : drupal_map_assoc($set->viewmodes),
      '#description' => t('Check the view modes which can be used in this set.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
    ];

    return $form;
  }

  public function validateForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // If you have selected 1+ content types, you should also have enabled at least one view mode.
    $content_types_selected = array_filter($form_state->getValue([
      'nodetypes'
      ]));
    $view_modes_selected = array_filter($form_state->getValue(['viewmodes']));
    if ($content_types_selected && !$view_modes_selected) {
      $form_state->setErrorByName('viewmodes', t('If you enable content types for this block set, you should enable at least one view mode to render the content types in.'));
    }
  }

  public function submitForm(array &$form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $set = new \stdClass();
    $set->name = $form_state->getValue(['name']);
    $set->title = $form_state->getValue(['title']);

//    drupal_write_record('felix_block_set', $set, empty($form['#felix_set']) ? [] : [
//      'name'
//      ]);

    \Drupal::database()->merge('felix_block_set')
      ->key(['name' =>$form_state->getValue(['name'])])
      ->fields(array('title' => $form_state->getValue(['title'])))
      ->execute();

    if (!empty($form['#felix_set'])) {
      db_delete('felix_block_set_block')->condition('block_set', $form_state->getValue(['name']))->execute();
      db_delete('felix_block_set_nodetype')->condition('block_set', $form_state->getValue(['name']))->execute();
      db_delete('felix_block_set_viewmode')->condition('block_set', $form_state->getValue(['name']))->execute();
    }

    foreach ($form_state->getValues() as $name => $value) {
      if (!preg_match('/^module_(.+)$/s', $name, $match)) {
        continue;
      }
      foreach ($value as $delta => $checked) {
        if (!$checked) {
          continue;
        }
//        drupal_write_record('felix_block_set_block', $block);
        \Drupal::database()->merge('felix_block_set_block')
          ->key(array('block_set' => $form_state->getValue(['name'])))
          ->fields(array('delta' => $delta, 'module' => $match[1]))
          ->execute();
      }
    }

    foreach ($form_state->getValue(['nodetypes']) as $name => $value) {
      if (!$value) {
        continue;
      }
//      drupal_write_record('felix_block_set_nodetype', $nodetype);
      \Drupal::database()->merge('felix_block_set_nodetype')
        ->key(array('block_set' => $form_state->getValue(['name'])))
        ->fields(array('nodetype' => $name))
        ->execute();
    }

    foreach ($form_state->getValue(['viewmodes']) as $name => $value) {
      if (!$value) {
        continue;
      }
//      drupal_write_record('felix_block_set_viewmode', $viewmode);
      \Drupal::database()->merge('felix_block_set_viewmode')
        ->key(array('block_set' => $form_state->getValue(['name'])))
        ->fields(array('viewmode' => $name))
        ->execute();
    }

    drupal_set_message(t('The block set was saved succesfully.'));
    $form_state->set(['redirect'], 'admin/structure/felix/sets');
  }

}
?>
