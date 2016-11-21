<?php

namespace Drupal\Tests\entity_pilot_err\Kernel;

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests the ERR normalization via uuids.
 *
 * @group entity_pilot_err
 */
class EntityReferenceRevisionItemNormalizerTest extends EntityPilotErrKernelTestBase {

  /**
   * Tests the normalization of nodes with paragraph references.
   */
  public function testNormalization() {
    // Create paragraphs, cloning before saving so that when the unsaved uuid
    // resolver returns the clones, they are saved along with the node.
    $paragraph1 = Paragraph::create([
      'title' => 'Paragraph',
      'type' => self::PARAGRAPH_TYPE,
    ]);
    $clone_p_1 = clone $paragraph1;
    $paragraph1->save();
    $paragraph2 = Paragraph::create([
      'title' => 'Paragraph',
      'type' => self::PARAGRAPH_TYPE,
    ]);
    $clone_p_2 = clone $paragraph2;
    $paragraph2->save();
    $paragraph3 = Paragraph::create([
      'title' => 'Paragraph',
      'type' => self::PARAGRAPH_TYPE,
    ]);
    $clone_p_3 = clone $paragraph3;
    $paragraph3->save();

    // Create a node with two paragraphs.
    $node = Node::create([
      'title' => $this->randomMachineName(),
      'type' => 'article',
      self::NODE_PARA_FIELD => [$paragraph1, $paragraph2, $paragraph3],
    ]);
    $node->save();

    // Normalize the node and test we can denormalize.
    $serializer = $this->container->get('serializer');
    $link_manager = $this->container->get('rest.link_manager');
    $field_uri = $link_manager->getRelationUri('node', 'article', self::NODE_PARA_FIELD, []);

    $normalized = $serializer->normalize($node, 'hal_json');
    // Change the revision ids to something else to prove it is the uuid
    // normalization we are testing.
    $normalized['_embedded'][$field_uri][0]['target_revision_id'] = $paragraph1->getRevisionId() + 4;
    $normalized['_embedded'][$field_uri][1]['target_revision_id'] = $paragraph2->getRevisionId() + 5;
    $normalized['_embedded'][$field_uri][2]['target_revision_id'] = $paragraph3->getRevisionId() + 6;

    $unsaved_uuid_resolver = $this->container->get('entity_pilot.resolver.unsaved_uuid');

    // Add our unsaved entities manually.
    $unsaved_uuid_resolver->add($clone_p_1);
    $unsaved_uuid_resolver->add($clone_p_2);
    $unsaved_uuid_resolver->add($clone_p_3);
    $paragraph1_uuid = $paragraph1->uuid();
    $paragraph2_uuid = $paragraph2->uuid();
    $paragraph3_uuid = $paragraph3->uuid();

    // Clean up.
    $node->delete();
    $paragraph1->delete();
    $paragraph2->delete();
    $paragraph3->delete();

    /** @var \Drupal\node\NodeInterface $denormalized */
    $denormalized = $serializer->denormalize($normalized, Node::class, 'hal_json');
    $denormalized->save();
    $entities = $denormalized->{self::NODE_PARA_FIELD}->referencedEntities();

    $this->assertEquals($paragraph1_uuid, $entities[0]->uuid());
    $this->assertEquals($paragraph2_uuid, $entities[1]->uuid());
    $this->assertEquals($paragraph3_uuid, $entities[2]->uuid());
  }

}
