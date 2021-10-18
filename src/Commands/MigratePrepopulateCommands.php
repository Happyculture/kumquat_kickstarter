<?php

namespace Drupal\kumquat_kickstarter\Commands;

use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\migrate\destination\EntityConfigBase;
use Drupal\migrate\Plugin\MigrateDestinationInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\Plugin\RequirementsInterface;
use Drupal\migrate\Row;
use Drush\Commands\DrushCommands;

class MigratePrepopulateCommands extends DrushCommands {

  /**
   * The migration manager service.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected MigrationPluginManagerInterface $migrationManager;

  public function __construct(MigrationPluginManagerInterface $migrationManager) {
    $this->migrationManager = $migrationManager;
  }

  /**
   * Drush command that prepopulates the migrate maps with existing content.
   *
   * @param string $migration
   *   Optional migration name. If empty, all migrations that match the options.
   *
   * @command kumquat_kickstarter:prepropulate
   * @aliases kkp
   * @option group
   *   The migration group to match.
   * @option tag
   *   The migration tag to match.
   *
   * @usage kkp kumquat_kickstarter_entity_bundles:node_type
   *   Only populates the map of one migration.
   * @usage kkp --group kumquat_kickstarter_fields
   *   Populates maps of all migrations of the group.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\migrate\MigrateSkipRowException
   */
  public function prepropulate($migration = NULL, $options = ['group' => '', 'tag' => '']) {
    if (!empty($migration)) {
      $migrations = [$migration => $this->migrationManager->getDefinition($migration)];
    }
    else {
      $migrations = $this->getFilteredMigrations($options['group'], $options['tag']);
    }

    foreach ($migrations as $migration_name => $definition) {
      /** @var \Drupal\migrate\Plugin\Migration $migration */
      $migration = $this->migrationManager->createInstance($migration_name);
      $executable = new MigrateExecutable($migration);
      [, $entity_type] = explode(':', $definition['destination']['plugin'], 2);
      $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
      $id_map = $migration->getIdMap();
      $source = $migration->getSourcePlugin();
      $destination = $migration->getDestinationPlugin();

      // Check that the migration would be able to run before trying to
      // populate its map.
      if ($source instanceof RequirementsInterface) {
        try {
          $source->checkRequirements();
        } catch (RequirementsException $exception) {
          continue;
        }
      }

      // Initialize source.
      $source->rewind();
      while ($source->valid()) {
        $row = $source->current();

        try {
          // Process row so destination is calculated.
          $executable->processRow($row);

          // Check if the row is already in the map.
          $ids = $id_map->lookupDestinationIds($row->getSourceIdValues());
          if (empty($ids)) {
            // Generate the row ids and load the destination entity to see
            // if it exists.
            $ids = $this->getRowIds($row, $destination);
            $id = $this->getDestinationIdFromRowIds($ids, $destination);
            $entity = $storage->load($id);
            if (!empty($entity)) {
              $id_map->saveIdMapping($row, $ids, MigrateIdMapInterface::STATUS_IMPORTED);
            }
          }
        }
        catch (MigrateException $e) {
          $id_map->saveIdMapping($row, [], $e->getStatus());
        }
        catch (MigrateSkipRowException $e) {
          if ($e->getSaveToMap()) {
            $id_map->saveIdMapping($row, [], MigrateIdMapInterface::STATUS_IGNORED);
          }
        }

        $source->next();
      }
    }
  }

  /**
   * Extract IDs from the row.
   *
   * @param \Drupal\migrate\Row $row
   *   The row.
   * @param \Drupal\migrate\Plugin\MigrateDestinationInterface $destination
   *   The destination plugin.
   *
   * @return array
   *   The row IDs.
   */
  protected function getRowIds(Row $row, MigrateDestinationInterface $destination) {
    $row_ids = [];
    foreach ($destination->getIds() as $key => $definition) {
      $row_ids[$key] = $row->getDestinationProperty($key);
    }

    return array_filter($row_ids);
  }

  /**
   * Build the entity ID from the row IDs.
   *
   * @param array $row_ids
   *   The row IDs.
   * @param \Drupal\migrate\Plugin\MigrateDestinationInterface $destination
   *   The destination plugin.
   *
   * @return false|mixed|string
   *   The entity ID.
   */
  protected function getDestinationIdFromRowIds(array $row_ids, MigrateDestinationInterface $destination) {
    if (count($row_ids) === 1) {
      $id = current($row_ids);
    }
    elseif ($destination instanceof EntityConfigBase) {
      $id = implode('.', $row_ids);
    }
    else {
      $id = $row_ids;
    }

    return $id;
  }

  /**
   * Get all migrations of a group or a tag.
   *
   * @param string $group
   *   The group to filter on.
   * @param string $tag
   *   The tag to filter on.
   *
   * @return array
   *   The filtered migration definitions.
   */
  protected function getFilteredMigrations($group = '', $tag = '') {
    $migrations = $this->migrationManager->getDefinitions();
    if (!empty($group)) {
      $migrations = array_filter($migrations, function ($item) use ($group) {
        return $item['migration_group'] == $group;
      });
    }
    if (!empty($tag)) {
      $migrations = array_filter($migrations, function ($item) use ($tag) {
        return in_array($tag, $item['migration_tags']);
      });
    }
    return $migrations;
  }

}
