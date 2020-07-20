<?php

namespace Drupal\hc_site_builder\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver for fields migration per bundle.
 */
class FieldsDeriver extends DeriverBase implements ContainerDeriverInterface {

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
   * EntityTypeDeriver constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity bundle info service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, FileSystemInterface $fileSystem) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach ($this->entityTypeManager->getDefinitions() as $entityType) {
      // We only keep entity types that are bundles of another entity type.
      $entity_type_id = $entityType->getBundleOf();
      if (NULL === $entity_type_id) {
        continue;
      }

      $file_path = $this->fileSystem->realpath($base_plugin_definition['source']['file']);
      /** @var \PhpOffice\PhpSpreadsheet\Reader\BaseReader $reader */
      $reader = IOFactory::createReader(IOFactory::identify($file_path));
      $reader->setLoadAllSheets();
      /** @var \PhpOffice\PhpSpreadsheet\Spreadsheet $workbook */
      $workbook = $reader->load($file_path);

      foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type_id) as $bundle_name => $bundle) {
        $bundle_label = $bundle['label'];

        $sheet_name = 'Fields: ' . $bundle_label;
        if (!($sheet = $workbook->getSheetByName($sheet_name))) {
          // Some XLS versions does not allow colons in sheet names.
          $sheet_name = 'Fields ' . $bundle_label;
          $sheet = $workbook->getSheetByName($sheet_name);
        }
        if (NULL !== $sheet) {
          $derivative = $this->getDerivativeValues($base_plugin_definition, $entity_type_id, $bundle_name, $bundle_label, $sheet_name);
          $this->derivatives[strtr($entity_type_id, '-', '_') . '__' . strtr($bundle_name, '-', '_')] = $derivative;
        }
      }
    }

    return $this->derivatives;
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
   * @param string $bundle_label
   *   The bundle label.
   * @param string $sheet_name
   *   The XLS worksheet name to use.
   *
   * @return array
   *   return the definition of the base plugin.
   */
  protected function getDerivativeValues(array $base_plugin_definition, $entity_type_id, $bundle_name, $bundle_label, $sheet_name) {
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
