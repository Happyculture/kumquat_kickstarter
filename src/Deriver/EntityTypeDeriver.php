<?php

namespace Drupal\kumquat_kickstarter\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver for bundle migration per entity type.
 */
class EntityTypeDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * EntityTypeDeriver constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->entityTypeManager->getDefinitions() as $entityType) {
      // We only keep entity types that are bundles of another entity type.
      if (NULL === $entityType->getBundleOf()) {
        continue;
      }

      $derivative = $this->getDerivativeValues($base_plugin_definition, $entityType);
      $this->derivatives[strtr($entityType->id(), '-', '_')] = $derivative;
    }

    return $this->derivatives;
  }

  /**
   * Creates a derivative definition for each available language.
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
    $label = $entityType->getLabel()->getUntranslatedString();
    $base_plugin_definition['process']['_exclude']['value'] = $label;
    $base_plugin_definition['process']['_exclude']['message'] = str_replace('ENTITY_TYPE', $label, $base_plugin_definition['process']['_exclude']['message']);

    $base_plugin_definition['process'][$entityType->getKey('id')] = $base_plugin_definition['process']['_machine_name'];
    unset($base_plugin_definition['process']['_machine_name']);

    $base_plugin_definition['destination']['plugin'] = 'entity:' . $entityType->id();

    return $base_plugin_definition;
  }

}
