<?php

namespace Drupal\kumquat_kickstarter\Deriver;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Deriver for label overrides migration per bundle.
 */
class LabelOverrideDeriver extends FieldsDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function getDerivativeValues(array $base_plugin_definition, $entity_type_id, $bundle_name, $sheet_name, $label_key) {
    $base_plugin_definition = parent::getDerivativeValues($base_plugin_definition, $entity_type_id, $bundle_name, $sheet_name, $label_key);

    $base_plugin_definition['process']['_exclude']['value'] = $label_key;

    return $base_plugin_definition;
  }

  /**
   * {@inheritdoc}
   */
  protected function getBundlesToDerivate($source_file_path) {
    // Only keep entity types that have a label.
    $allowed_types = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $definition) {
      if ($definition->hasKey('label')) {
        $allowed_types[$entity_type_id] = TRUE;
      }
    }

    // Filter out items related to entity types without label.
    $bundles_to_derivate = parent::getBundlesToDerivate($source_file_path);
    return array_filter($bundles_to_derivate, function ($item) use ($allowed_types) {
      return !empty($allowed_types[$item['entity_type_id']]);
    });
  }

}
