<?php

namespace Drupal\kumquat_kickstarter\Plugin\migrate\process;

use Drupal\Component\Uuid\Php;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @MigrateProcessPlugin(
 *   id = "pathauto_selection_criteria"
 * )
 */
class PathautoSelectionCriteria extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The uuid service.
   *
   * @var \Drupal\Component\Uuid\Php
   */
  protected $uuid;

  /**
   * The migration object.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migration;

  /**
   * Constructs a FieldType plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Component\Uuid\Php $uuid
   *   The uuid service.
   * @param \Drupal\migrate\Plugin\MigrationInterface|null $migration
   *   The migration being run.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Php $uuid, MigrationInterface $migration = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->uuid = $uuid;
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
      $container->get('uuid'),
      $migration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($this->configuration['entity_type']) || empty($this->configuration['bundle'])) {
      throw new MigrateException('PathautoSelectionCriteria plugin is missing entity_type or bundle configuration.');
    }

    $this->entityType = $row->get($this->configuration['entity_type']);
    $this->bundle = $row->get($this->configuration['bundle']);

    $uuid = $this->uuid->generate();
    return [
      $uuid => [
        'id' => 'entity_bundle:' . $this->entityType,
        'negate' => FALSE,
        'uuid' => $uuid,
        'context_mapping' => [
          $this->entityType => $this->entityType,
        ],
        'bundles' => [
          $this->bundle => $this->bundle,
        ],
      ],
    ];
  }

}
