<?php /**
 * @file
 * Contains \Drupal\felix_ui\Controller\DefaultController.
 */

namespace Drupal\felix_ui\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;


/**
 * Default controller for the felix_ui module.
 */
class DefaultController extends ControllerBase {

  public function felix_ui_regions_page() {

    $header = [t('Title'), t('Context'), t('Region'), t('Operations')];
    $rows = [];

    $regions = db_select('felix_region', 'cbr')
      ->fields('cbr', ['name', 'title', 'context', 'region'])
      ->orderBy('title')
      ->execute();
    while ($region = $regions->fetchObject()) {
      $operations = [
        Link::fromTextAndUrl(t('Edit'), Url::fromRoute('felix_ui.region_form', ['region' => $region->name])),
        Link::fromTextAndUrl(t('Delete'), Url::fromRoute('felix_ui.region_delete_form', ['region' => $region->name])),
      ];
      $rows[] = [
        Html::escape($region->title),
        Html::escape($region->context),
        Html::escape($region->region),
        implode(' ', $operations),
      ];
    }

    if (!$rows) {
      return ['#markup' => t('<p>There are no regions defined yet.</p>')];
    }
    return ['#theme' => 'table', 'header' => $header, 'rows' => $rows];
  }

  public function felix_ui_sets_page() {

    $header = [t('Title'), t('Operations')];
    $rows = [];

    $sets = db_select('felix_block_set', 'cbs')
      ->fields('cbs', ['name', 'title'])
      ->orderBy('title')
      ->execute();
    while ($set = $sets->fetchObject()) {
      $operations = [
        Link::fromTextAndUrl(t('Edit'), Url::fromRoute('felix_ui.set_form', ['set' => $set->name]))->toString(),
        Link::fromTextAndUrl(t('Delete'), Url::fromRoute('felix_ui.delete_set_form', ['set' => $set->name]))->toString(),
      ];
      $rows[] = [
        Html::escape($set->title),
        implode(' ', $operations),
      ];
    }

    if (!$rows) {
      return ['#markup' => '<p>' . t('There are no block sets defined yet.') . '</p>'];
    }
    return [
      '#theme' => 'table',
      'header' => $header,
      'rows' => $rows,
    ];

  }

}
