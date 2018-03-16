<?php /**
 * @file
 * Contains \Drupal\felix\EventSubscriber\InitSubscriber.
 */

namespace Drupal\felix\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InitSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::REQUEST => ['onEvent', 0]];
  }

  public function onEvent() {
    module_load_include('inc', 'context', 'plugins/context_reaction_block');
  }

}
