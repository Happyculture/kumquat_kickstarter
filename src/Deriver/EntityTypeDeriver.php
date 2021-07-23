<?php

namespace Drupal\kumquat_kickstarter\Deriver;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Deriver for bundle migration per entity type.
 */
class EntityTypeDeriver extends EntityTypeDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function getDerivativeValues(array $base_plugin_definition, EntityTypeInterface $entityType) {
    $base_plugin_definition = parent::getDerivativeValues($base_plugin_definition, $entityType);

    $base_plugin_definition['process'][$entityType->getKey('id')] = $base_plugin_definition['process']['_machine_name'];
    unset($base_plugin_definition['process']['_machine_name']);

    $base_plugin_definition['process'][$entityType->getKey('label')] = $base_plugin_definition['process']['_label'];
    unset($base_plugin_definition['process']['_label']);

    $base_plugin_definition['destination']['plugin'] = 'entity:' . $entityType->id();

    return $base_plugin_definition;
  }

}
