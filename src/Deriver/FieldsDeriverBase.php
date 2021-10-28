<?php

namespace Drupal\kumquat_kickstarter\Deriver;

use Drupal\Core\Cache\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base deriver for fields migration per bundle.
 */
abstract class FieldsDeriverBase extends MigrationDeriverBase {

  /**
   * The entity bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $instance = parent::create($container, $base_plugin_id);
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    $instance->cache = $container->get('cache.default');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $file_path = $this->fileSystem->realpath($base_plugin_definition['source']['file']);

    foreach ($this->getBundlesToDerivate($file_path) as $key => $values) {
      $this->derivatives[$key] = $this->getDerivativeValues(
        $base_plugin_definition,
        $values['entity_type_id'],
        $values['bundle_name'],
        $values['sheet_name'],
        $values['label_key']
      );
      $this->derivatives[$key]['migration_tags'][] = $this->derivatives[$key]['migration_group'] . ':' . $key;
    }

    return $this->derivatives;
  }

  /**
   * Get bundles that can be derivated and data to create derivatives.
   *
   * This method uses the cache to make it faster for successive calls.
   *
   * @param string $source_file_path
   *   The absolute path of the file used as a source for the migrations.
   *
   * @return array
   *   An array keyed by a combination of the entity type ID and the bundle,
   *   which values are to be used by the getDerivativeValues() method later.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
   */
  protected function getBundlesToDerivate($source_file_path) {
    $bundles_to_derivate = [];

    $cache = $this->cache->get(__METHOD__);
    if (!empty($cache) && $cache->data['filemtime'] >= filemtime($source_file_path)) {
      $bundles_to_derivate = $cache->data['bundles_to_derivate'];
    }
    else {
      $cacheTags = ['entity_types'];

      /** @var \PhpOffice\PhpSpreadsheet\Reader\BaseReader $reader */
      $reader = IOFactory::createReader(IOFactory::identify($source_file_path));
      $reader->setLoadAllSheets();
      /** @var \PhpOffice\PhpSpreadsheet\Spreadsheet $workbook */
      $workbook = $reader->load($source_file_path);

      foreach ($this->entityTypeManager->getDefinitions() as $entityType) {
        // We only keep entity types that are bundles of another entity type.
        $entity_type_id = $entityType->getBundleOf();
        if (NULL === $entity_type_id) {
          continue;
        }

        // Add the generic ENTITY_TYPE_list cache tag to invalidate caches when
        // bundles are added, edited or deleted.
        $cacheTags[] = $entity_type_id . '_list';

        // Get the label base field machine name to ignore it in the migration.
        $label_key = $this->entityTypeManager->getDefinition($entity_type_id)->getKey('label');

        foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type_id) as $bundle_name => $bundle) {
          $bundle_label = $bundle['label'];

          $sheet_name = $this->getWorksheetRealName($workbook, 'Fields: ' . $bundle_label);
          if (!empty($sheet_name)) {
            $bundles_to_derivate[strtr($entity_type_id, '-', '_') . '__' . strtr($bundle_name, '-', '_')] = [
              'entity_type_id' => $entity_type_id,
              'bundle_name' => $bundle_name,
              'sheet_name' => $sheet_name,
              'label_key' => $label_key,
            ];
          }
        }
      }

      $this->cache->set(
        __METHOD__,
        [
          'filemtime' => filemtime($source_file_path),
          'bundles_to_derivate' => $bundles_to_derivate,
        ],
        Cache::PERMANENT,
        $cacheTags
      );
    }

    return $bundles_to_derivate;
  }

  /**
   * Gets the real name of a worksheet from its expected name.
   *
   * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $workbook
   *   The spreadsheet object.
   * @param $expected_name
   *   The sheet expected base name.
   *
   * @return false|string
   *   The sheet real name or FALSE if not found.
   */
  protected function getWorksheetRealName(Spreadsheet $workbook, $expected_name) {
    // Remove some chars that are usually dropped during the GCalc => XLS
    // conversion.
    $cleaned_name = strtr($expected_name, array_fill_keys(str_split("[]*?;:/'"), ''));

    // Create variations of the name between 33 and 30 characters because GCalc
    // often crops the sheet name around this length.
    $tries = [$expected_name, $cleaned_name];
    if (strlen($expected_name) >= 30) {
      for ($i = 33 ; $i >= 30 ; --$i) {
        $tries[] = substr($expected_name, 0, $i);
        $tries[] = substr($cleaned_name, 0, $i);
      }
    }
    $tries = array_unique($tries);

    foreach ($tries as $sheet_name) {
      if ($workbook->getSheetByName($sheet_name)) {
        return $sheet_name;
      }
    }

    return FALSE;
  }

  /**
   * Creates a derivative definition for each available bundle.
   *
   * @param array $base_plugin_definition
   *   The definition of the base plugin from which the derivative plugin
   *   is derived. It is maybe an entire object or just some array, depending
   *   on the discovery mechanism.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle_name
   *   The bundle machine name.
   * @param string $sheet_name
   *   The XLS worksheet name to use.
   *
   * @return array
   *   return the definition of the base plugin.
   */
  protected function getDerivativeValues(array $base_plugin_definition, $entity_type_id, $bundle_name, $sheet_name, $label_key) {
    $base_plugin_definition['source']['worksheet'] = $sheet_name;

    $base_plugin_definition['source']['constants']['langcode'] = $this->defaultLangcode;
    $base_plugin_definition['source']['constants']['entity_type'] = $entity_type_id;
    $base_plugin_definition['source']['constants']['bundle'] = $bundle_name;

    foreach ($base_plugin_definition['migration_dependencies'] as $type => $dependencies) {
      foreach ($base_plugin_definition['migration_dependencies'][$type] as $delta => $dependency) {
        $dependency = str_replace('ENTITY_TYPE', strtr($entity_type_id, '-', '_'), $dependency);
        $dependency = str_replace('BUNDLE', strtr($bundle_name, '-', '_'), $dependency);
        $base_plugin_definition['migration_dependencies'][$type][$delta] = $dependency;
      }
    }

    return $base_plugin_definition;
  }

}
