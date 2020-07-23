<?php

namespace Drupal\kumquat_kickstarter\Plugin\migrate\process;

use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\Exception\BadPluginDefinitionException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @MigrateProcessPlugin(
 *   id = "field_default_settings"
 * )
 */
class FieldDefaultSettings extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypePluginManager;

  /**
   * Constructs a FieldType plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $fieldTypePluginManager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FieldTypePluginManagerInterface $fieldTypePluginManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fieldTypePluginManager = $fieldTypePluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.field.field_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    switch ($this->configuration['type']) {
      case 'storage':
        return $this->fieldTypePluginManager->getDefaultStorageSettings($value);

      case 'field':
        return $this->fieldTypePluginManager->getDefaultFieldSettings($value);

      default:
        throw new BadPluginDefinitionException('field_default_settings', 'type');
    }
  }

}
