<?php

namespace Drupal\kumquat_kickstarter\Plugin\migrate\process;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Component\Utility\NestedArray;
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
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $yaml;

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
   * @param \Drupal\Component\Serialization\SerializationInterface $yaml
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FieldTypePluginManagerInterface $fieldTypePluginManager, SerializationInterface $yaml) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fieldTypePluginManager = $fieldTypePluginManager;
    $this->yaml = $yaml;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.field.field_type'),
      $container->get('serialization.yaml')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    switch ($this->configuration['type']) {
      case 'storage':
        $settings = $this->fieldTypePluginManager->getDefaultStorageSettings($value);
        break;

      case 'field':
        $settings = $this->fieldTypePluginManager->getDefaultFieldSettings($value);
        break;

      default:
        throw new BadPluginDefinitionException('field_default_settings', 'type');
    }

    if ($this->configuration['override']) {
      $overrides = $row->get($this->configuration['override']);
      if (!empty(trim($overrides))) {
        $settings = NestedArray::mergeDeep($settings, $this->yaml->decode($overrides));
      }
    }

    return $settings;
  }

}
