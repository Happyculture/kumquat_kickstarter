<?php

namespace Drupal\kumquat_kickstarter\Deriver;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Deriver for fields migration per bundle.
 */
class FieldsInstanceDeriver extends FieldsStorageDeriver {

  /**
   * {@inheritdoc}
   */
  protected $translatableFields = ['label', 'description'];

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $current_derivatives = parent::getDerivativeDefinitions($base_plugin_definition);
    return $this->getTranslationDerivativeDefinitions($current_derivatives, $this->additionalLangcodes);
  }

}
