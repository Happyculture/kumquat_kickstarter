<?php

namespace Drupal\kumquat_kickstarter\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base deriver for fields migration per bundle.
 */
abstract class FieldsDeriverBase extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * EntityTypeDeriver constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity bundle info service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, FileSystemInterface $fileSystem, CacheBackendInterface $cache) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->fileSystem = $fileSystem;
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('file_system'),
      $container->get('cache.default')
    );
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
    if (($cache = $cache->data) && $cache['filemtime'] >= filemtime($source_file_path)) {
      $bundles_to_derivate = $cache['bundles_to_derivate'];
    }
    else {
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

        // Get the label base field machine name to ignore it in the migration.
        $label_key = $this->entityTypeManager->getDefinition($entity_type_id)->getKey('label');

        foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type_id) as $bundle_name => $bundle) {
          $bundle_label = $bundle['label'];

          $sheet_name = 'Fields: ' . $bundle_label;
          if (!($sheet = $workbook->getSheetByName($sheet_name))) {
            // Clean some special chars in the sheet name for older XLS standards.
            $toClean = ';:/';
            $sheet_name = strtr($sheet_name, array_fill_keys(str_split($toClean), ''));
            $sheet = $workbook->getSheetByName($sheet_name);
          }
          if (NULL !== $sheet) {
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
        ['entity_types']
      );
    }

    return $bundles_to_derivate;
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
