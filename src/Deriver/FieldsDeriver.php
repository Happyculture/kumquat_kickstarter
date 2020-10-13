<?php

namespace Drupal\kumquat_kickstarter\Deriver;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Deriver for fields migration per bundle.
 */
class FieldsDeriver extends FieldsDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function getDerivativeValues(array $base_plugin_definition, $entity_type_id, $bundle_name, $sheet_name, $label_key) {
    $base_plugin_definition = parent::getDerivativeValues($base_plugin_definition, $entity_type_id, $bundle_name, $sheet_name, $label_key);

    // Skip rows that are concerning the label base field.
    array_splice($base_plugin_definition['process'], 0, 0, [
      '_exclude_label' => [
        'plugin' => 'skip_on_value',
        'source' => 'Machine name',
        'value' => $label_key,
        'method' => 'row',
      ]
    ]);

    return $base_plugin_definition;
  }

}
