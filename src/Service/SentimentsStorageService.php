<?php

declare(strict_types=1);

namespace Drupal\analyze_ai_sentiments\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Service for storing and retrieving sentiments analysis results.
 */
final class SentimentsStorageService {
  use DependencySerializationTrait;

  public function __construct(
    private readonly Connection $database,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RendererInterface $renderer,
    private readonly TimeInterface $time,
  ) {
  }

  /**
   * Gets the cached sentiments scores for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get the scores for.
   *
   * @return array<string, float>
   *   Array of sentiments_id => score pairs.
   */
  public function getScores(EntityInterface $entity): array {
    $content_hash = $this->generateContentHash($entity);
    $config_hash = $this->generateConfigHash();

    $results = $this->database->select('analyze_ai_sentiments_results', 'r')
      ->fields('r', ['sentiments_id', 'score'])
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->condition('langcode', $entity->language()->getId())
      ->condition('content_hash', $content_hash)
      ->condition('config_hash', $config_hash)
      ->execute()
      ->fetchAllKeyed();

    return array_map('floatval', $results);
  }

  /**
   * Saves sentiments scores for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the scores are for.
   * @param array<string, float> $scores
   *   Array of sentiments_id => score pairs.
   */
  public function saveScores(EntityInterface $entity, array $scores): void {
    $content_hash = $this->generateContentHash($entity);
    $config_hash = $this->generateConfigHash();

    // Delete existing scores for this entity/language combination.
    $this->database->delete('analyze_ai_sentiments_results')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->condition('langcode', $entity->language()->getId())
      ->execute();

    // Insert new scores.
    if (!empty($scores)) {
      $insert = $this->database->insert('analyze_ai_sentiments_results')
        ->fields([
          'entity_type', 'entity_id', 'entity_revision_id', 'langcode',
          'sentiments_id', 'score', 'content_hash', 'config_hash', 'analyzed_timestamp',
        ]);

      foreach ($scores as $sentiments_id => $score) {
        // Ensure score is within valid range.
        $score = max(-1.0, min(1.0, (float) $score));

        $insert->values([
          'entity_type' => $entity->getEntityTypeId(),
          'entity_id' => $entity->id(),
          'entity_revision_id' => method_exists($entity, 'getRevisionId') ? $entity->getRevisionId() : NULL,
          'langcode' => $entity->language()->getId(),
          'sentiments_id' => $sentiments_id,
          'score' => $score,
          'content_hash' => $content_hash,
          'config_hash' => $config_hash,
          'analyzed_timestamp' => $this->time->getRequestTime(),
        ]);
      }

      $insert->execute();
    }
  }

  /**
   * Deletes all stored scores for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to delete scores for.
   */
  public function deleteScores(EntityInterface $entity): void {
    $this->database->delete('analyze_ai_sentiments_results')
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->execute();
  }

  /**
   * Invalidates all cached results due to configuration changes.
   */
  public function invalidateConfigCache(): void {
    // Delete all records with old config hash.
    $current_hash = $this->generateConfigHash();
    $this->database->delete('analyze_ai_sentiments_results')
      ->condition('config_hash', $current_hash, '!=')
      ->execute();
  }

  /**
   * Gets statistics about stored analysis results.
   *
   * @return array<string, int>
   *   Array with count statistics.
   */
  public function getStatistics(): array {
    $query = $this->database->select('analyze_ai_sentiments_results', 'r');
    $query->addExpression('COUNT(*)', 'total_results');
    $query->addExpression('COUNT(DISTINCT entity_id)', 'unique_entities');
    $query->addExpression('COUNT(DISTINCT sentiments_id)', 'unique_sentiments');
    $query->addExpression('MIN(analyzed_timestamp)', 'oldest_analysis');
    $query->addExpression('MAX(analyzed_timestamp)', 'newest_analysis');

    $result = $query->execute()->fetchAssoc();

    return [
      'total_results' => (int) $result['total_results'],
      'unique_entities' => (int) $result['unique_entities'],
      'unique_sentiments' => (int) $result['unique_sentiments'],
      'oldest_analysis' => $result['oldest_analysis'] ? (int) $result['oldest_analysis'] : 0,
      'newest_analysis' => $result['newest_analysis'] ? (int) $result['newest_analysis'] : 0,
    ];
  }

  /**
   * Gets average scores by sentiments type.
   *
   * @return array<string, float>
   *   Array of sentiments_id => average_score pairs.
   */
  public function getAverageScores(): array {
    $query = $this->database->select('analyze_ai_sentiments_results', 'r');
    $query->addField('r', 'sentiments_id');
    $query->addExpression('AVG(score)', 'average_score');
    $query->groupBy('sentiments_id');
    $results = $query->execute()
      ->fetchAllKeyed();

    return array_map('floatval', $results);
  }

  /**
   * Generates a configuration hash for sentiments settings.
   *
   * @return string
   *   The MD5 hash of the sentiments configuration.
   */
  private function generateConfigHash(): string {
    $config = $this->configFactory->get('analyze_ai_sentiments.settings');
    $sentiments = $config->get('sentiments') ?? [];

    // Sort to ensure consistent hashing.
    ksort($sentiments);

    return hash('md5', serialize($sentiments));
  }

  /**
   * Generates a content hash for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to generate a hash for.
   *
   * @return string
   *   The SHA256 hash of the entity content.
   */
  private function generateContentHash(EntityInterface $entity): string {
    $content = $this->getEntityContent($entity);
    return hash('sha256', $content);
  }

  /**
   * Extracts clean text content from an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to extract content from.
   *
   * @return string
   *   The cleaned text content.
   */
  private function getEntityContent(EntityInterface $entity): string {
    // Use the entity's own language, not the current UI language.
    $langcode = $entity->language()->getId();

    // Render the entity in default view mode.
    $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
    $view = $view_builder->view($entity, 'default', $langcode);
    $rendered = $this->renderer->render($view);

    // Convert to string and clean up.
    $content = is_object($rendered) && method_exists($rendered, '__toString')
        ? $rendered->__toString()
        : (string) $rendered;

    // Strip HTML tags and normalize whitespace.
    $content = strip_tags($content);
    $content = str_replace('&nbsp;', ' ', $content);
    $content = preg_replace('/\s+/', ' ', $content);
    $content = trim($content);

    return $content;
  }

}
