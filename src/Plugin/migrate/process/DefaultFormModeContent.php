<?php

namespace Drupal\kumquat_kickstarter\Plugin\migrate\process;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @MigrateProcessPlugin(
 *   id = "default_form_mode_content"
 * )
 */
class DefaultFormModeContent extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  const MODE = 'form';

  /**
   * The migration object.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migration;

  /**
   * The field widget plugin manager service.
   *
   * @var \Drupal\Core\Field\WidgetPluginManager
   */
  protected $widgetPluginManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var mixed[]
   */
  protected $fieldTypes;

  /**
   * @var mixed|null
   */
  protected $entityType;

  /**
   * @var mixed|null
   */
  protected $bundle;

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
   *   The field type plugin manager service.
   * @param \Drupal\Core\Field\WidgetPluginManager $widgetPluginManager
   *   The field widget plugin manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   * @param \Drupal\migrate\Plugin\MigrationInterface|null $migration
   *   The migration being run.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FieldTypePluginManagerInterface $fieldTypePluginManager, WidgetPluginManager $widgetPluginManager, EntityFieldManagerInterface $entityFieldManager, MigrationInterface $migration = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fieldTypes = $fieldTypePluginManager->getDefinitions();
    $this->widgetPluginManager = $widgetPluginManager;
    $this->entityFieldManager = $entityFieldManager;

    $this->migration = $migration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.field.field_type'),
      $container->get('plugin.manager.field.widget'),
      $container->get('entity_field.manager'),
      $migration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($this->configuration['entity_type']) || empty($this->configuration['bundle'])) {
      throw new MigrateException('DefaultFormModeContent plugin is missing entity_type or bundle configuration.');
    }

    $this->entityType = $row->get($this->configuration['entity_type']);
    $this->bundle = $row->get($this->configuration['bundle']);

    $field_definitions = $this->getFieldDefinitions();
    $extra_fields = $this->getExtraFields();

    $content = [];
    $weight = 0;

    // Sort fields by type.
    $sorted_fields = [
      'base' => [],
      'config' => [],
      'other' => [],
    ];
    foreach ($field_definitions as $field_name => $field_definition) {
      $key = 'other';
      if ($field_definition instanceof FieldConfig) {
        $key = 'config';
      }
      elseif ($field_definition instanceof BaseFieldOverride) {
        $key = 'base';
      }
      $sorted_fields[$key][$field_name] = $field_definition;
    }

    // Add fields to the results.
    foreach ($sorted_fields as $type => $definitions) {
      foreach ($definitions as $field_name => $field_definition) {
        $field_type = $field_definition->getType();
        $field_widget = $this->fieldTypes[$field_type]['default_widget'];
        $content[$field_name] = [
          'type' => $field_widget,
          'weight' => $weight++,
          'region' => 'content',
          'settings' => $this->widgetPluginManager->getDefaultSettings($field_widget),
          'third_party_settings' => [],
        ];
      }
    }

    // Add extra fields too.
    foreach ($extra_fields as $field_id => $extra_field) {
      $content[$field_id] = [
        'weight' => $weight++,
        'region' => 'content',
        'settings' => [],
        'third_party_settings' => [],
      ];
    }

    return $content;
  }

  /**
   * Collects the definitions of fields whose display is configurable.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   The array of field definitions
   */
  protected function getFieldDefinitions() {
    $context = static::MODE;
    return array_filter($this->entityFieldManager->getFieldDefinitions($this->entityType, $this->bundle), function (FieldDefinitionInterface $field_definition) use ($context) {
      return $field_definition->isDisplayConfigurable($context);
    });
  }

  /**
   * Returns the extra fields of the entity type and bundle used by this form.
   *
   * @return array
   *   An array of extra field info.
   *
   * @see \Drupal\Core\Entity\EntityFieldManagerInterface::getExtraFields()
   */
  protected function getExtraFields() {
    $context = static::MODE;
    $extra_fields = $this->entityFieldManager->getExtraFields($this->entityType, $this->bundle);
    return $extra_fields[$context] ?? [];
  }

}
