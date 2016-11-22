<?php

namespace Drupal\entity_pilot_err\Normalizer;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\entity_pilot\EntityResolver\UnsavedUuidResolverInterface;
use Drupal\hal\Normalizer\ContentEntityNormalizer;
use Drupal\rest\LinkManager\LinkManagerInterface;
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
  public function __construct(LinkManagerInterface $link_manager, EntityManagerInterface $entity_manager, ModuleHandlerInterface $module_handler, UnsavedUuidResolverInterface $unsaved_uuid, UuidReferenceInterface $uuid_reference) {
    parent::__construct($link_manager, $entity_manager, $module_handler);
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
          if ($target_entity = $this->entityManager->getStorage($normalized['parent_type'][$key]['value'])->load($value['value'])) {
            $normalized = $this->embedEntity($entity, $format, $context, $target_entity, $normalized, self::PSUEDO_FIELD_NAME);
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
          if ($entity = $this->entityManager->loadEntityByUuid($entity_type_id, $value['target_uuid'])) {
            $data['parent_id'][$key]['value'] = $entity->id();
          }
        }
      }
    }
    $entity = parent::denormalize($data, $class, $format, $context);
    return $entity;
  }

  /**
   * Embeds an entity in the normalized data.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being serialized.
   * @param string $format
   *   The serialization format.
   * @param array $context
   *   Serializer context.
   * @param \Drupal\Core\Entity\EntityInterface $target_entity
   *   Entity being embedded.
   * @param array $normalized
   *   Current normalized values.
   * @param string $embedded_field_name
   *   Field name to embed the entity using.
   *
   * @return array
   *   Updated normalized values.
   */
  protected function embedEntity(EntityInterface $entity, $format, array $context, EntityInterface $target_entity, array $normalized, $embedded_field_name) {
    // If the parent entity passed in a langcode, unset it before
    // normalizing the target entity. Otherwise, untranslatable fields
    // of the target entity will include the langcode.
    $langcode = isset($context['langcode']) ? $context['langcode'] : NULL;
    unset($context['langcode']);
    $context['included_fields'] = ['uuid'];

    // Normalize the target entity.
    $embedded = $this->serializer->normalize($target_entity, $format, $context);
    $link = $embedded['_links']['self'];
    // If the field is translatable, add the langcode to the link
    // relation object. This does not indicate the language of the
    // target entity.
    if ($langcode) {
      $embedded['lang'] = $link['lang'] = $langcode;
    }

    // The returned structure will be recursively merged into the
    // normalized entity so that the items are properly added to the
    // _links and _embedded objects.
    $embedded_field_uri = $this->linkManager->getRelationUri($entity->getEntityTypeId(), $entity->bundle(), $embedded_field_name, $context);
    $normalized['_links'][$embedded_field_uri] = [$link];
    $normalized['_embedded'][$embedded_field_uri] = [$embedded];
    return $normalized;
  }

}
