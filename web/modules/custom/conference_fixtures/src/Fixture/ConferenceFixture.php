<?php

namespace Drupal\conference_fixtures\Fixture;

use Drupal\content_fixtures\Fixture\AbstractFixture;
use Drupal\content_fixtures\Fixture\DependentFixtureInterface;
use Drupal\node\Entity\Node;

/**
 * Class ConferenceFixture.
 *
 * @package Drupal\conference_fixtures\Fixture
 */
class ConferenceFixture extends AbstractFixture implements DependentFixtureInterface {

  /**
   * {@inheritdoc}
   */
  public function load() {
    /** @var \Drupal\node\Entity\Node $conference */
    $conference = Node::create([
      'type' => 'conference',
      'title' => 'The first conference',
      'body' => <<<'BODY'
This is the first conference.

It'll be <strong>fun</strong>!
BODY,
    ]);
    $conference->setOwner($this->getReference('user:organizer'));

    $this->setReference('conference:001', $conference);

    $conference->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies() {
    return [
      UserFixture::class,
    ];
  }

}
