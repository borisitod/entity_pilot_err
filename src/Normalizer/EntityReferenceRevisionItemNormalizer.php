<?php

/**
 * @file
 * Contains \Drupal\entity_pilot\Normalizer\EntityReferenceItemNormalizer.
 */

namespace Drupal\entity_pilot_err\Normalizer;

use Drupal\entity_reference_revisions\Normalizer\EntityReferenceRevisionItemNormalizer as BaseEntityReferenceRevisionItemNormalizer;
use Drupal\rest\LinkManager\LinkManagerInterface;
use Drupal\serialization\EntityResolver\EntityResolverInterface;

/**
 * Lets the entity-reference-revision resolver work with unsaved entities.
 */
class EntityReferenceRevisionItemNormalizer extends BaseEntityReferenceRevisionItemNormalizer {

  /**
   * Parent normalizer.
   *
   * @var \Drupal\entity_reference_revisions\Normalizer\EntityReferenceRevisionItemNormalizer
   */
  protected $parentNormalizer;

  /**
   * {@inheritdoc}
   */
  public function __construct(LinkManagerInterface $link_manager, EntityResolverInterface $entity_resolver, BaseEntityReferenceRevisionItemNormalizer $parent_normalizer) {
    parent::__construct($link_manager, $entity_resolver);
    $this->parentNormalizer = $parent_normalizer;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function constructValue($data, $context) {
    $field_item = $context['target_instance'];
    $field_definition = $field_item->getFieldDefinition();
    $target_type = $field_definition->getSetting('target_type');
    if ($entity = $this->entityResolver->resolve($this, $data, $target_type)) {
      // The exists plugin manager may nominate an existing entity to use here.
      if ($id = $entity->id()) {
        return [
          'target_id' => $id,
          'target_revision_id' => $entity->getRevisionId(),
        ];
      }
      return ['entity' => $entity];
    }
    return $this->parentNormalizer->constructValue($data, $context);
  }

}
