<?php

namespace Drupal\node_duplicate\Plugin\Action;


use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\filter\Render\FilteredMarkup;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

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

    // Add the "Clone of " prefix to the entity label.
    if ($duplicated_entity instanceof TranslatableInterface) {
      if ($label_key = $duplicated_entity->getEntityType()->getKey('label')) {
        foreach ($duplicated_entity->getTranslationLanguages() as $language) {
          $langcode = $language->getId();
          $duplicated_entity = $duplicated_entity->getTranslation($langcode);
          $new_label = $this->t('Clone of @label', [
            '@label' => FilteredMarkup::create($duplicated_entity->label()),
          ], [
            'langcode' => $langcode,
          ]);
          $duplicated_entity->set($label_key, $new_label);
        }
      }

    }
    $this->_entity_reference_recursive_clone($duplicated_entity);

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

  /**
   * Duplicate the entities which are referenced in the target node
   *
   * @param \Drupal\node\NodeInterface $target_entity
   *   Node to duplicate.
   */
  private function _entity_reference_recursive_clone($target_entity) {
    if (!$this->is_clonable($target_entity)) {
      return;
    }
    foreach ($target_entity->getFieldDefinitions() as $key => $fieldDefinition) {
      if (
        ($fieldDefinition->getType() === 'entity_reference_revisions' || $fieldDefinition->getType() === 'entity_reference') &&
        !$target_entity->{$key}->isEmpty() &&
        $entity_type = $fieldDefinition->getFieldStorageDefinition()->getSettings()['target_type']
      ) {

        if ($entity_type !== 'user' && $key !== 'type' && $entity_type !== 'node') {
          $references = [];
          foreach ($target_entity->{$key}->getValue() as $k => $fieldValue) {
            $reference_id = isset($fieldValue['target_id']) ? $fieldValue['target_id'] : $target_entity->{$key}->getValue()[$k]['target_id'];
            $referenced_entity = \Drupal::entityTypeManager()
              ->getStorage($entity_type)
              ->load($reference_id);

            if ($referenced_entity) {
              $duplicated_referenced_entity = $referenced_entity->createDuplicate();
              $references[] = $duplicated_referenced_entity;

              $this->_rename_duplicated_entity_label($duplicated_referenced_entity);
              $this->_entity_reference_recursive_clone($duplicated_referenced_entity);
            }
          }
          $target_entity->set($key, $references);
          $target_entity->save();
        }
      }
    }
  }

  /**
   * @param $duplicated_entity
   */
  private function _rename_duplicated_entity_label($duplicated_entity) {

    if ($duplicated_entity instanceof TranslatableInterface) {
      // Is the entity is translatable and there is a label field?
      if ($label_key = $duplicated_entity->getEntityType()->getKey('label')) {
        // $is_translatable will be true if the label field is translatable
        $is_translatable = $duplicated_entity->getFieldDefinitions()[$label_key]->isTranslatable();
        $original_label = NULL;

        foreach ($duplicated_entity->getTranslationLanguages() as $language) {
          $langcode = $language->getId();
          $duplicated_entity = $duplicated_entity->getTranslation($langcode);

          if (!$is_translatable && $original_label === NULL) {
            // The label field value is not translatable so we keep the first value
            $original_label = $duplicated_entity->label();
          } else if ($is_translatable) {
            // The label is translatable so we will use the label for each language
            $original_label = $duplicated_entity->label();
          }

          // @TODO: Truncate $new_label value
          $new_label = $this->t('Clone of @label', [
            '@label' => FilteredMarkup::create(substr($original_label, 0, 13)),
          ], [
            'langcode' => $langcode,
          ]);

          $duplicated_entity->set($label_key, $new_label);
          $duplicated_entity->save();
        }
      }
    }
  }

  /**
   * @param $entity
   * @return bool
   */
  private function is_clonable($entity) {
    return (property_exists($entity, 'fieldDefinitions') && !$entity instanceof Term && !$entity instanceof Vocabulary);
  }

}
