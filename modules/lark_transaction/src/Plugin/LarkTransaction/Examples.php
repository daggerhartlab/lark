<?php

declare(strict_types=1);

namespace Drupal\lark_transaction\Plugin\LarkTransaction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lark_transaction\Plugin\LarkTransactionPluginBase;
use Drupal\lark_transaction\Attribute\LarkTransaction;

/**
 * Plugin implementation of the lark transaction.
 */
#[LarkTransaction(
  id: 'examples',
  label: new TranslatableMarkup('Examples'),
  description: new TranslatableMarkup('Examples for how to import various content.'),
  weight: -1,
  enabled: FALSE,
  repeatable: FALSE,
)]
class Examples extends LarkTransactionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $source_directory = $this->sourceDirectory('lark.example_lark_source');
    /*
     * Files & Images.
     */
    // Importing an image is probably the most common need.
    $media_entity = $this->sideLoadImage(
      'My Example Image media entity',
      'This will be the alt text for the image',
      $source_directory . '/examples/FINDME.jpg',
      'public://content-imported/examples'
    );

    // We can also import a file entity if needed. This would be used on fields
    // that are for images (field_image) as opposed to fields that are for
    // media items.
    $file_entity = $this->sideLoadFile(
      $source_directory . '/examples/crow.png',
      'public://content-imported/examples'
    );

    /*
     * Paragraphs: Create some paragraphs and a node that they are assigned to.
     */
    $paragraphs = [];
    // Create paragraph that has examples for many types of fields.
    $paragraphs[] = $this->entityTypeManager->getStorage('paragraph')->create([
      'type' => 'example_do_not_use',
      'langcode' => 'en',
      // Simple text.
      'field_headline' => 'Simple text field with no content format.',
      // Formatted text.
      'field_content' => [
        'value' => '<p>This is long content that has a content format (input filter).</p>',
        'format' => 'full_html',
      ],
      // File entity (like the older image fields).
      'field_image' => $file_entity,
      // Media entity (like new image fields).
      'field_example_media_do_not_use' => [
        $media_entity,
      ],
      // Simple link field. Note: This is not the same as a menu link.
      'field_link' => [
        'title' => 'This is a link title',
        'uri' => 'entity:node/1',
        //'options' => [],
      ],
    ]);

    // Must save the paragraph instances before they will have an ID.
    foreach ($paragraphs as $paragraph) {
      $paragraph->save();
    }

    /**
     * Node: Simple node that has paragraphs as components.
     */
    $node = $this->entityTypeManager->getStorage('node')->create([
      'title' => 'Example node created',
      'type' => 'landing_page',
      'langcode' => 'en',
      'status' => 1, // Published.
      'uid' => 1, // Owner is user id 1.
      'field_components' => $paragraphs,
    ]);
    $node->save();

    /**
     * Blocks: Content blocks that aren't exported correctly can be created
     * after the fact by giving them the same uuid that was originally created
     * for the block.
     *
     * The block's expected UUID can be found in the block's configuration yaml.
     */

    // Since we are forcing the UUID, we need to check that the blocks don't
    // exist or else the creation will fatally fail.
    $exists = $this->entityTypeManager->getStorage('block_content')->loadByProperties([
      'uuid' => '3dd21587-c8c5-4366-9e60-c54cf762ecdf',
    ]);

    // Alternatively, you could load and delete the old one before creating a
    // new block with the same uuid.
    foreach ($exists as $exist) {
      //$exist->delete();
    }

    if (!$exists) {
      // Create the missing footer logo block content.
      $block = $this->entityTypeManager->getStorage('block_content')->create([
        'uuid' => '3dd21587-c8c5-4366-9e60-c54cf762ecdf',
        'langcode' => 'en',
        'type' => 'basic',
        'info' => 'Footer Logo',
        'body' => [
          'value' => '<p><a href="http://www.communitytissue.org" target="_blank"><img src="/themes/custom/maxxeus_theme/images/footer-logo.png" alt="logo" loading="lazy" width="400" height="117"></a></p>',
          'format' => 'full_html',
        ],
      ]);
      $block->save();
    }

    $exists = $this->entityTypeManager->getStorage('block_content')->loadByProperties([
      'uuid' => 'e0ed6866-5a9f-47be-b006-c945d7e14d64',
    ]);
    if (!$exists) {
      // Create the missing copyright block content.
      $block = $this->entityTypeManager->getStorage('block_content')->create([
        'uuid' => 'e0ed6866-5a9f-47be-b006-c945d7e14d64',
        'langcode' => 'en',
        'type' => 'basic',
        'info' => 'Copyright',
        'body' => [
          'value' => '<p>&copy; Copyright 2024 - Maxxeus is a brand of Solvita. All Rights Reserved. <a href="/privacy-policy">Privacy Policy</a>.</p>',
          'format' => 'full_html',
        ],
      ]);
      $block->save();
    }

    /**
     * Menu link content: Create menu links assigned to specific menus.
     */
    $menu_links = [];
    $menu_links[] = $this->entityTypeManager->getStorage('menu_link_content')->create([
      // Name of the menu this link belongs to.
      'menu_name' => 'footer',
      // Set the language for this menu link to english.
      'langcode' => 'en',
      // Title is the text displayed for the link.
      'title' => 'Example link to Entity',
      // The "link" field is for the link's uri and options.
      'link' => [
        // The "entity:" scheme is for linking to entities.
        // This doesn't start with a slash.
        'uri' => 'entity:node/1',
        // Output options for the link are here within the Link field.
        'options' => [
          'container_attributes' => [
            'class' => [
              'FINDME-NEW',
            ],
          ],
        ]
      ],
    ]);
    $menu_links[] = $this->entityTypeManager->getStorage('menu_link_content')->create([
      'menu_name' => 'footer',
      'langcode' => 'en',
      'title' => 'Example link to non-entity page',
      'link' => [
        // The "internal:" scheme is for linking to non-entity urls.
        // This example starts with a slash so that it's an absolute url.
        'uri' => 'internal:/some-random-internal-url',
      ],
    ]);

    // Need to save our new entities.
    foreach ($menu_links as $menu_link) {
      $menu_link->save();
    }

    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $parent_menu_link */
    $parent_menu_link = $this->entityTypeManager->getStorage('menu_link_content')->create([
      'menu_name' => 'footer',
      'langcode' => 'en',
      'title' => 'Parent menu link',
      'link' => [
        'uri' => 'entity:node/1',
      ],
    ]);
    $parent_menu_link->save();

    $child_menu_link = $this->entityTypeManager->getStorage('menu_link_content')->create([
      'menu_name' => 'footer',
      'langcode' => 'en',
      'title' => 'Child menu link',
      'link' => [
        'uri' => 'entity:node/2',
      ],
      'parent' => $parent_menu_link->getPluginId(),
    ]);
    $child_menu_link->save();
  }

}
