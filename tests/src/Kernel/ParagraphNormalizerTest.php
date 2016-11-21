<?php

namespace Drupal\Tests\entity_pilot_err\Kernel;

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests the paragraph parent id normalization.
 *
 * @group entity_pilot_err
 */
class ParagraphNormalizerTest extends EntityPilotErrKernelTestBase {

  /**
   * Tests the normalization of paragraph parent ids.
   */
  public function testParagraphNormalization() {
    // This will be saved when we save the node so the parent_id is set.
    $paragraph = Paragraph::create([
      'title' => 'Paragraph',
      'type' => self::PARAGRAPH_TYPE,
    ]);
    $clone = clone $paragraph;

    // Create a node with a paragraph.
    $node = Node::create([
      'title' => $this->randomMachineName(),
      'type' => 'article',
      self::NODE_PARA_FIELD => [$paragraph],
    ]);
    $node->save();

    // Load the paragraph again as the EntityReferenceRevisionsItem::postSave
    // hook adds the parent_id field.
    $paragraph = Paragraph::load($paragraph->id());

    // Normalize the paragraph.
    $serializer = $this->container->get('serializer');
    $normalized = $serializer->normalize($paragraph, 'hal_json');

    // Increment the parent_id so we are relying on uuid normalization.
    $normalized['parent_id'][0]['value'] += 1;

    // Add our unsaved entities manually.
    $unsaved_uuid_resolver = $this->container->get('entity_pilot.resolver.unsaved_uuid');
    $unsaved_uuid_resolver->add($clone);

    // Clean up.
    $paragraph->delete();

    /** @var \Drupal\paragraphs\Entity\Paragraph $denormalized */
    $denormalized = $serializer->denormalize($normalized, Paragraph::class, 'hal_json');
    $denormalized->save();

    // Test denormalization found the parent.
    $this->assertNotNull($denormalized->getParentEntity());
    $this->assertEquals($node->uuid(), $denormalized->getParentEntity()->uuid());
  }

}
