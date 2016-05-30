<?php

namespace Drupal\node_duplicate\Plugin\Action;


use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Duplicates a node.
 *
 * @Action(
 *   id = "node_duplicate_action",
 *   label = @Translation("Duplicate node"),
 *   type = "node"
 * )
 */
class DuplicateNodeAction extends ActionBase {

  /**
   * Duplicates a node.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   Node to duplicate.
   *
   * @return \Drupal\node\NodeInterface
   *   Duplicated node.
   */
  public function execute($entity = NULL) {
    $duplicated_entity = $entity->createDuplicate();
    $duplicated_entity->status = NODE_NOT_PUBLISHED;
    $duplicated_entity->save();
    return $duplicated_entity;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\node\NodeInterface $object */
    return $object->access('update', $account, $return_as_object);
  }
}
