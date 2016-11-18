<?php

namespace Drupal\Tests\entity_pilot_err\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the ERR composite relationship upgrade path.
 *
 * @group paragraphs
 */
class EntityReferenceRevisionItemNormalizerTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'entity_pilot_err',
    'entity_pilot',
    'paragraphs',
    'entity_reference_revisions',
    'serialization',
    'rest',
    'hal',
    'node',
    'user',
    'system',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    // Create paragraphs and article content types.
    $values = ['type' => 'article', 'name' => 'Article'];
    $node_type = NodeType::create($values);
    $node_type->save();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
  }

  /**
   * Tests the revision of paragraphs.
   */
  public function testParagraphsRevisions() {
    // Create the paragraph type.
    $paragraph_id = 'test_text';
    $paragraph_type = ParagraphsType::create([
      'label' => 'Test text',
      'id' => $paragraph_id,
    ]);
    $paragraph_type->save();

    // Add a paragraph field to the article.
    $node_para_field = 'node_paragraph_field';
    $field_storage = FieldStorageConfig::create([
      'field_name' => $node_para_field,
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'cardinality' => '-1',
      'settings' => [
        'target_type' => 'paragraph',
      ],
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'article',
    ]);
    $field->save();

    $paragraph1 = Paragraph::create([
      'title' => 'Paragraph',
      'type' => $paragraph_id,
    ]);
    $paragraph1->save();
    $paragraph2 = Paragraph::create([
      'title' => 'Paragraph',
      'type' => $paragraph_id,
    ]);
    $paragraph2->save();
    $paragraph3 = Paragraph::create([
      'title' => 'Paragraph',
      'type' => $paragraph_id,
    ]);
    $paragraph3->save();

    // Create a node with two paragraphs.
    $node = Node::create([
      'title' => $this->randomMachineName(),
      'type' => 'article',
      $node_para_field => [$paragraph1, $paragraph2, $paragraph3],
    ]);
    $node->save();

    // Normalize the node and test we can denormalize.
    $serializer = $this->container->get('serializer');
    $context['included_fields'] = ['uuid'];

    $normalized = $serializer->normalize($node, 'hal_json', $context);
    // Now we switch the URI to something else but it should still go back to
    // the same node.
    $normalized['link'][0]['link'] = 'entity:node/' . ($node->id() + 1);
    $unsaved_uuid_resolver = $this->container->get('entity_pilot.resolver.unsaved_uuid');
    $clone_p_1 = clone $paragraph1;
    $clone_p_2 = clone $paragraph1;
    $clone_p_3 = clone $paragraph3;
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
    $denormalized = $serializer->denormalize($normalized, NodeInterface::class, 'hal_json');
    $entities = $denormalized->{$node_para_field}->referencedEntities();
    $this->assertEquals($paragraph1_uuid, $entities[0]->uuid());
    $this->assertEquals($paragraph2_uuid, $entities[1]->uuid());
    $this->assertEquals($paragraph3_uuid, $entities[2]->uuid());
  }
}
