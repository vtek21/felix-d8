<?php

/**
 * @file
 * Contains \Drupal\felix_ui\Form\FelixUiRegionForm.
 */

namespace Drupal\felix_ui\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;

class FelixUiRegionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'felix_ui_region_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $region = NULL) {
    if ($region) {
      $region = db_select('felix_region', 'cbr')
        ->fields('cbr', [
        'name',
        'title',
        'region',
        'weight',
        'context',
        'block_set',
        'data',
      ])
        ->condition('cbr.name', $region)
        ->execute()
        ->fetchObject();
      if (!$region) {
        drupal_not_found();
        Drupal::moduleHandler()->invokeAll('exit');
        exit;
      }

      $region->data = unserialize($region->data);
      $form['#felix_region'] = $region;
    }

    if (!$form_state->getStorage()) {
      $values = [];
      $rehash = FALSE;
    }
    else {
      $values = $form_state->getStorage();
      $rehash = $form_state->getStorage();
    }

    if ($rehash) {
      // Build the second page (rehash review / options).

      $added = $form_state->getStorage();
      $removed = $form_state->getStorage();
      $form['info'] = [
        '#markup' => '<p>' . t('You have changed the differentiate content option. This requires us to update all blocks in a batch process. This proces will start after clicking the submit button. Please review the settings below.') . '</p>'
        ];
      $form['added'] = [
        '#markup' => '<p>' . t('Added') . ': ' . ($added ? Html::escape(implode(', ', $added)) : t('none')) . '</p>'
        ];
      $form['removed'] = [
        '#markup' => '<p>' . t('Removed') . ': ' . ($removed ? Html::escape(implode(', ', $removed)) : t('none')) . '</p>'
        ];
      if ($added) {
        $form['info_parts'] = [
          '#markup' => '<p>' . t('The information for the added parts is not available for existing blocks. You need to provide a default value for these fields.') . '</p>'
          ];
        $form['parts'] = ['#tree' => TRUE];
        foreach ($added as $part) {
          $form['parts'][$part] = [
            '#type' => 'textfield',
            '#title' => t('Default value for %part', [
              '%part' => $part
              ]),
            '#default_value' => !empty($values['parts']) && !empty($values['parts'][$part]) ? $values['parts'][$part] : '',
          ];
        }
      }

      $form['rehash'] = [
        '#type' => 'submit',
        '#value' => t('Save and rehash'),
      ];
    }
    else {
      // Build the first page.

      $block_sets = db_select('felix_block_set', 'cbs')
        ->fields('cbs', ['name', 'title'])
        ->orderBy('title')
        ->execute()
        ->fetchAllKeyed();
      if (!$block_sets) {
        $form['info'] = [
          '#markup' => '<p>' . t('You have to <a href="@url">create a block set</a> first before you can create a new region.', [
            '@url' => Url::fromRoute('felix_ui.create_set_form')->toString(true)->getGeneratedUrl()
            ]) . '</p>'
          ];
        return $form;
      }

      $contextItems = \Drupal::entityTypeManager()->getStorage('context')->loadMultiple();
      $contexts = [];
      foreach($contextItems as $context) {
        $contexts[$context->get('name')] = $context->get('name');
      }

      if (!$contexts) {
        $form['info'] = [
          '#markup' => '<p>' . t('You have to <a href="@url">create a context</a> before you can create a new region.', [
            '@url' => Url::fromRoute('entity.context.add_form')->toString(true)->getGeneratedUrl()
            ]) . '</p>'
          ];
        return $form;
      }

      $form['title'] = [
        '#type' => 'textfield',
        '#title' => t('Title'),
        '#default_value' => $region ? $region->title : '',
        '#required' => TRUE,
      ];

      $form['name'] = [
        '#type' => 'machine_name',
        '#title' => t('Machine name'),
        '#default_value' => $region ? $region->name : '',
        '#maxlength' => 64,
        '#required' => TRUE,
        '#machine_name' => [
          'exists' => 'felix_ui_region_name_exists',
          'source' => [
            'title'
            ],
          'label' => t('Name'),
          'replace_pattern' => '[^a-z0-9-]+',
          'replace' => '-',
        ],
        '#disabled' => empty($region) ? FALSE : TRUE,
      ];

      $options = [];
      $themes = system_get_info('theme');
      foreach ($themes as $theme) {
        $options[$theme['name']] = $theme['regions'];
      }
      $form['region'] = [
        '#type' => 'select',
        '#title' => t('Region'),
        '#options' => $options,
        '#default_value' => $region ? $region->region : '',
      ];

      $form['context'] = [
        '#type' => 'select',
        '#title' => t('Context'),
        '#options' => $contexts,
        '#default_value' => $region ? $region->context : '',
      ];

      $form['weight'] = [
        '#type' => 'textfield',
        '#title' => t('Weight'),
        '#size' => 6,
        '#default_value' => $region ? $region->weight : 0,
        '#required' => TRUE,
      ];

      $form['block_set'] = [
        '#type' => 'select',
        '#title' => t('Block set'),
        '#options' => $block_sets,
        '#default_value' => $region ? $region->block_set : '',
      ];

      $form['hash'] = [
        '#tree' => TRUE,
        '#type' => 'fieldset',
        '#title' => t('Differentiate content per'),
        '#description' => t('This setting allows you to stick blocks to pages matching specified criteria. For example, if you choose nodetype, all article pages will have the same blocks, but blog pages will have different blocks.'),
        '#collapsible' => FALSE,
      ];
      $options = \Drupal::moduleHandler()->invokeAll('felix_hash_options');
      foreach ($options as $name => $description) {
        $form['hash'][$name] = [
          '#prefix' => '<div class="felix-ui-hash">',
          '#suffix' => '</div>',
        ];
        $form['hash'][$name]['enabled'] = [
          '#type' => 'checkbox',
          '#title' => $description,
          '#default_value' => $region && in_array($name, $region->data['hash']),
        ];
        $values = $region && !empty($region->data['hash_config'][$name]) ? $region->data['hash_config'][$name] : [];
        $config = \Drupal::moduleHandler()->invokeAll('felix_hash_config', ['name' => $name, 'values' => $values]);
        if ($config) {
          $form['hash'][$name]['config'] = $config;
          $form['hash'][$name]['config']['#prefix'] = '<div class="felix-ui-hash-config">';
          $form['hash'][$name]['config']['#suffix'] = '</div>';
        }
      }

      $form['#attached']['library'][] = 'felix_ui/felix_ui';

      $form['submit'] = [
        '#type' => 'submit',
        '#value' => t('Save'),
      ];
    }

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue(['weight'])) {
      $weight = $form_state->getValue(['weight']);
      if (!is_numeric($weight) || $weight < -127 || $weight > 128) {
        $form_state->setErrorByName('weight', t('Weight must be a number between -127 and 128.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = !$form_state->getStorage() ? [] : $form_state->getStorage();
    $values += $form_state->getValues();

    $region = new \stdClass();
    $region->name = $values['name'];
    $region->title = $values['title'];
    $region->region = $values['region'];
    $region->weight = $values['weight'];
    $region->context = $values['context'];
    $region->block_set = $values['block_set'];

//    dump((array)$region); exit;

    $data = empty($form['#felix_region']) ? [] : $form['#felix_region']->data;
    $data['hash'] = [];
    $data['hash_config'] = [];
    foreach (Element::children($values['hash']) as $name) {
      $item = $values['hash'][$name];
      if ($item['enabled']) {
        $data['hash'][] = $name;
        if (!empty($item['config'])) {
          $data['hash_config'][$name] = $item['config'];
        }
      }
    }
    if (empty($data['hash_config'])) {
      // No need to save this value.
    // Remove it to keep consistence with old feature modules.
      unset($data['hash_config']);
    }
    $region->data = serialize($data);

    $rehash = FALSE;
    if (!empty($form['#felix_region'])) {
      $removed = array_values(array_diff($form['#felix_region']->data['hash'], $data['hash']));
      $added = array_values(array_diff($data['hash'], $form['#felix_region']->data['hash']));
      $rehash = $removed || $added;
    }

    if ($rehash) {
      if ($form_state->get(['clicked_button', '#value']) == t('Save and rehash')) {
        \Drupal::database()->merge('felix_region')
          ->key(['name' => $region->name])
          ->fields((array)$region)
          ->execute();
        // Save region and start batch to rehash blocks.
        module_load_include('inc', 'felix', 'felix.rehash');
        felix_batch_rehash_start($region, $added, $removed, isset($values['parts']) ? $values['parts'] : []);
      }
      else {
        // Rebuild the form for the second step.
        $form_state->setRebuild(TRUE);
        $form_state->setStorage([
          'values' => $form_state->getValues(),
          'rehash' => TRUE,
          'hash_added' => $added,
          'hash_removed' => $removed,
        ]);
      }
    }
    else {

      \Drupal::database()->merge('felix_region')
        ->key('name')
        ->fields((array)$region)
        ->execute();

      drupal_set_message(t('The region has been saved succesfully.'));
      $form_state->set(['redirect'], 'admin/structure/felix');
    }
  }

}
