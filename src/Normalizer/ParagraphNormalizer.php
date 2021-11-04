<?php

namespace Drupal\entity_pilot_err\Normalizer;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\entity_pilot\EntityResolver\UnsavedUuidResolverInterface;
use Drupal\hal\Normalizer\ContentEntityNormalizer;
use Drupal\hal\LinkManager\LinkManagerInterface;
use Drupal\serialization\EntityResolver\UuidReferenceInterface;

/**
 * A normalizer to handle Paragraph entities with serial parent_ids.
 */
class ParagraphNormalizer extends ContentEntityNormalizer {

  /**
   * Psuedo field name for embedding target entity.
   *
   * @var string
   */
  const PSUEDO_FIELD_NAME = 'paragraph_parent_entity';

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = 'Drupal\paragraphs\ParagraphInterface';

  /**
   * Unsaved UUID resolver.
   *
   * @var \Drupal\entity_pilot\EntityResolver\UnsavedUuidResolverInterface
   */
  protected $unsavedUuid;

  /**
   * UUID Reference resolver.
   *
   * @var \Drupal\serialization\EntityResolver\UuidReferenceInterface
   */
  protected $uuidReference;

  /**
   * {@inheritdoc}
   */
  public function __construct(LinkManagerInterface $link_manager, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, EntityTypeRepositoryInterface $entity_type_repository, EntityFieldManagerInterface $entity_field_manager, UnsavedUuidResolverInterface $unsaved_uuid, UuidReferenceInterface $uuid_reference) {
    parent::__construct($link_manager, $entity_type_manager, $module_handler, $entity_type_repository, $entity_field_manager);
    $this->unsavedUuid = $unsaved_uuid;
    $this->uuidReference = $uuid_reference;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = array()) {
    $normalized = parent::normalize($entity, $format, $context);
    if (isset($normalized['parent_id']) && is_array($normalized['parent_id'])) {
      foreach ($normalized['parent_id'] as $key => $value) {
        try {
          if ($target_entity = $this->entityTypeManager->getStorage($normalized['parent_type'][$key]['value'])->load($value['value'])) {
            $normalized['parent_id'][$key] += [
              'target_uuid' => $target_entity->uuid(),
            ];
          }
          else {
            // Entity ID no longer exists.
            continue;
          }
        }
        catch (PluginNotFoundException $e) {
          // Entity-type not found.
          continue;
        }
      }
    }
    return $normalized;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {
    if (isset($data['parent_id']) && is_array($data['parent_id'])) {
      foreach ($data['parent_id'] as $key => $value) {
        $entity_type_id = $data['parent_type'][$key]['value'];
        if (isset($value['target_uuid'])) {
          $uuid = ['uuid' => $value['target_uuid']];
          if ($entity = $this->unsavedUuid->resolve($this->uuidReference, $uuid, $entity_type_id)) {
            $data['parent_id'][$key]['value'] = $entity->id();
          }
          if ($entity = $this->entityTypeManager->loadEntityByUuid($entity_type_id, $value['target_uuid'])) {
            $data['parent_id'][$key]['value'] = $entity->id();
          }
        }
      }
    }
    $entity = parent::denormalize($data, $class, $format, $context);
    return $entity;
  }

}
