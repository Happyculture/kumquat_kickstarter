<?php

namespace Drupal\kumquat_kickstarter\Deriver;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Deriver for bundle migration per entity type.
 */
abstract class EntityTypeDeriverBase extends MigrationDeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->entityTypeManager->getDefinitions() as $entityType) {
      // We only keep entity types that are bundles of another entity type.
      if (NULL === $entityType->getBundleOf()) {
        continue;
      }

      // Ignore Media Types as they need a source plugin that we cannot provide
      // for now.
      // @TODO Find a way to migrate Media Types.
      if ($entityType->id() === 'media_type') {
        continue;
      }

      $derivative = $this->getDerivativeValues($base_plugin_definition, $entityType);
      $this->derivatives[strtr($entityType->id(), '-', '_')] = $derivative;
    }

    return $this->derivatives;
  }

  /**
   * Creates a derivative definition for each entity type.
   *
   * @param array $base_plugin_definition
   *   The definition of the base plugin from which the derivative plugin
   *   is derived. It is maybe an entire object or just some array, depending
   *   on the discovery mechanism.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return array
   *   return the definition of the base plugin.
   */
  protected function getDerivativeValues(array $base_plugin_definition, EntityTypeInterface $entityType) {
    $entity_type_id = $entityType->getBundleOf();

    $base_plugin_definition['source']['constants']['entity_type'] = $entityType->id();
    $base_plugin_definition['source']['constants']['bundle_entity_type'] = $entity_type_id;
    $base_plugin_definition['source']['constants']['langcode'] = $this->defaultLangcode;

    $label = $entityType->getLabel()->getUntranslatedString();
    $base_plugin_definition['process']['_exclude_other_type']['value'] = $label;
    $base_plugin_definition['process']['_exclude_other_type']['message'] = str_replace('ENTITY_TYPE', $label, $base_plugin_definition['process']['_exclude_other_type']['message']);

    foreach ($base_plugin_definition['migration_dependencies'] as $type => $dependencies) {
      foreach ($base_plugin_definition['migration_dependencies'][$type] as $delta => $dependency) {
        $dependency = str_replace('ENTITY_TYPE', strtr($entity_type_id, '-', '_'), $dependency);
        $base_plugin_definition['migration_dependencies'][$type][$delta] = $dependency;
      }
    }

    return $base_plugin_definition;
  }

}
