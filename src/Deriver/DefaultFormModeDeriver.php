<?php

namespace Drupal\kumquat_kickstarter\Deriver;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Deriver for default form mode.
 */
class DefaultFormModeDeriver extends EntityTypeDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function getDerivativeValues(array $base_plugin_definition, EntityTypeInterface $entityType) {
    $entity_type_id = $entityType->getBundleOf();

    $base_plugin_definition['source']['constants']['entity_type'] = $entity_type_id;

    $label = $entityType->getLabel()->getUntranslatedString();
    $base_plugin_definition['process']['_exclude']['value'] = $label;
    $base_plugin_definition['process']['_exclude']['message'] = str_replace('ENTITY_TYPE', $label, $base_plugin_definition['process']['_exclude']['message']);

    foreach ($base_plugin_definition['migration_dependencies'] as $type => $dependencies) {
      foreach ($base_plugin_definition['migration_dependencies'][$type] as $delta => $dependency) {
        $dependency = str_replace('ENTITY_TYPE', strtr($entity_type_id, '-', '_'), $dependency);
        $base_plugin_definition['migration_dependencies'][$type][$delta] = $dependency;
      }
    }

    return $base_plugin_definition;
  }

}
