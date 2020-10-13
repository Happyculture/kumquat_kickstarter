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

}
