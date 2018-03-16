<?php /**
 * @file
 * Contains \Drupal\felix\Controller\DefaultController.
 */

namespace Drupal\felix\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the felix module.
 */
class DefaultController extends ControllerBase {

  public function felix_block_access($op = 'add', $region = NULL, $block = NULL, Drupal\Core\Session\AccountInterface $account) {
    $user = \Drupal::currentUser();
    if (!user_access('manage felix blocks')) {
      return FALSE;
    }
    if ($user->uid == 1) {
      return TRUE;
    }
    if (empty($region) && !empty($_GET['region'])) {
      $region = $_GET['region'];
    }
    if (empty($block) && !empty($_GET['region']) && !empty($_GET['module']) && !empty($_GET['delta']) && !empty($_GET['hash'])) {
      $block = db_select('felix_block', 'cb')
        ->fields('cb', [
        'fbid',
        'region',
        'weight',
        'hash',
        'module',
        'delta',
        'data',
      ])
        ->condition('cb.region', $_GET['region'])
        ->condition('cb.module', $_GET['module'])
        ->condition('cb.delta', $_GET['delta'])
        ->condition('cb.hash', $_GET['hash'])
        ->execute()
        ->fetchObject();
    }
    $allow = TRUE;
    foreach (\Drupal::moduleHandler()->getImplementations('felix_access') as $module) {
      switch (module_invoke($module, 'felix_access', $op, $block)) {
        case FELIX_ALLOW:
          // Do nothing.
          break;
        case FELIX_DENY:
          return FALSE;
      }
    }
    return $allow;
  }

  public function felix_add_block_page() {
    if (empty($_GET['region']) || empty($_GET['path'])) {
      return MENU_NOT_FOUND;
    }

    if (!$region = _felix_region_by_name($_GET['region'])) {
      return MENU_NOT_FOUND;
    }

    if (!empty($_GET['nodetype'])) {
      $salt = $_GET['nodetype'] . $region->name;
      if (drupal_get_token($salt) != $_GET['token']) {
        return MENU_ACCESS_DENIED;
      }

      if (!empty($_GET['viewmode'])) {
        return _felix_add_block_nodetype_page($region, $_GET['nodetype'], $_GET['path'], $_GET['viewmode']);
      }
      else {
        $query = db_select('felix_region', 'r')->condition('r.name', $region->name);
        $query->join('felix_block_set_viewmode', 'vm', 'r.block_set = vm.block_set');
        $query->fields('vm', ['viewmode']);
        $viewmodes = $query->execute()->fetchCol();
        if (count($viewmodes) == 1) {
          // Skip the viewmodes page if there is only one viewmode to choose from.
          return _felix_add_block_nodetype_page($region, $_GET['nodetype'], $_GET['path'], $viewmodes[0]);
        }
        else {
          return _felix_add_block_viewmode_page($region, $_GET['nodetype'], $_GET['path'], $_GET['token']);
        }
      }
    }
    elseif (!empty($_GET['module']) && !empty($_GET['delta'])) {
      $salt = $_GET['module'] . $_GET['delta'] . $region->name;
      if (drupal_get_token($salt) != $_GET['token']) {
        return MENU_ACCESS_DENIED;
      }
      $block = new stdClass();
      $block->module = $_GET['module'];
      $block->delta = $_GET['delta'];
      $block->region = $_GET['region'];
      $block->path = $_GET['path'];
      $block->data = [];
      $config = module_invoke_all('felix_block_config', $block);
      if ($config || TRUE) {
        $block = new stdClass();
        $block->region = $_GET['region'];
        $block->module = $_GET['module'];
        $block->delta = $_GET['delta'];
        $block->data = [];
        return \Drupal::formBuilder()->getForm('felix_attributes_form', $region, $block, $_GET['path']);
      }
      else {
        return _felix_add_block($region, $_GET['path'], $_GET['module'], $_GET['delta']);
      }
    }
    elseif (!empty($_GET['nid'])) {
      $salt = $_GET['nid'] . $region->name;
      if (drupal_get_token($salt) != $_GET['token']) {
        return MENU_ACCESS_DENIED;
      }
      $data = ['viewmode' => $_GET['viewmode']];
      return _felix_add_block($region, $_GET['path'], 'node', $_GET['nid'], 'node/' . $_GET['nid'], $data);
    }
    else {
      // Show the list of possible blocks and nodetypes.
      return _felix_add_block_list_page($region, $_GET['path']);
    }
  }

  public function felix_block_action($action) {
    if (empty($_GET['region']) || empty($_GET['fbid']) || empty($_GET['path']) || empty($_GET['module']) || empty($_GET['delta']) || !isset($_GET['hash']) || empty($_GET['token'])) {
      return MENU_NOT_FOUND;
    }

    if (!$region = _felix_region_by_name($_GET['region'])) {
      return MENU_NOT_FOUND;
    }

    $block = db_select('felix_block', 'cb')
      ->fields('cb')
      ->condition('cb.fbid', $_GET['fbid'])
      ->execute()
      ->fetchObject();
    if (!$block) {
      return MENU_NOT_FOUND;
    }

    $block->data = unserialize($block->data);
    $block->hash_parts = unserialize($block->hash_parts);
    $block->exclude = unserialize($block->exclude);

    $token = drupal_get_token($_GET['fbid'] . $_GET['region'] . $_GET['module'] . $_GET['delta'] . $_GET['hash']);

    if ($token !== $_GET['token']) {
      return MENU_ACCESS_DENIED;
    }

    switch ($action) {
      case 'move-up':
      case 'move-down':
        return _felix_block_action_move($action, $block, $_GET['path']);
      case 'attributes':
        return \Drupal::formBuilder()->getForm('felix_attributes_form', $region, $block, $_GET['path']);
      case 'hide':
      case 'show':
        return _felix_block_action_hide($action, $block, $_GET['path']);
      case 'remove':
        return \Drupal::formBuilder()->getForm('felix_remove_form', $region, $block, $_GET['path']);
    }
  }

  public function felix_autocomplete_node($nodetype, $text) {
    $language = \Drupal::languageManager()->getCurrentLanguage();
    $matches = [];
    $language_condition = db_or()
      ->condition('n.language', \Drupal\Core\Language\Language::LANGCODE_NOT_SPECIFIED)
      ->condition('n.language', $language->language);
    $nodes = db_select('node', 'n')
      ->fields('n', ['nid', 'title'])
      ->condition('n.type', $nodetype)
      ->condition('n.title', db_like($text) . '%', 'LIKE')
      ->condition($language_condition)
      ->orderBy('n.changed', 'DESC')
      ->range(0, 10)
      ->addTag('node_access')
      ->execute()
      ->fetchAll();
    foreach ($nodes as $node) {
      $matches["{$node->title} (#{$node->nid})"] = \Drupal\Component\Utility\Html::escape($node->title);
    }
    drupal_json_output($matches);
  }

  public function _felix_autocomplete($type) {
    $search = $_GET['s'];

    $results = [];
    $nodes = db_select('node', 'n')
      ->fields('n', ['nid', 'title', 'changed'])
      ->condition('n.type', $type)
      ->condition('n.title', "$search%", 'LIKE')
      ->orderBy('n.changed', 'desc')
      ->range(0, 10)
      ->addTag('node_access')
      ->execute()
      ->fetchAll();
    foreach ($nodes as $node) {
      $results[] = [
        'nid' => $node->nid,
        'title' => \Drupal\Component\Utility\Html::escape($node->title),
        'value' => $node->title . ' (#' . $node->nid . ')',
        'description' => t('Last modified: @date', [
          '@date' => format_date($node->changed)
          ]),
        'addClass' => NULL,
      ];
    }

    // Let other modules alter the autocomplete results.
    \Drupal::moduleHandler()->alter('felix_autocomplete', $results);

    if (empty($results)) {
      $results = [
        [
          'title' => t('No results'),
          'addClass' => 'status-notice',
          'disabled' => TRUE,
        ]
        ];
    }

    drupal_json_output($results);
    drupal_exit();
  }

}
